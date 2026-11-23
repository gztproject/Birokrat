<?php

namespace App\Tests\Invoice;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Test-only driver wrapper. Worker A pauses after the invoice number has been
 * chosen and before that number is written, so a second issue can run in the
 * gap the controller leaves open.
 */
final class RaceDelayMiddleware implements Middleware
{
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new RaceDelayDriver($driver);
    }
}

final class RaceDelayDriver extends AbstractDriverMiddleware
{
    public function connect(array $params): DriverConnection
    {
        return new RaceDelayConnection(parent::connect($params));
    }
}

final class RaceDelayConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        return new RaceDelayStatement(parent::prepare($sql), $sql);
    }

    public function query(string $sql): Result
    {
        RaceDelayGate::beforeQuery($sql);
        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        RaceDelayGate::beforeQuery($sql);
        return parent::exec($sql);
    }
}

final class RaceDelayStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private readonly string $sql)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        RaceDelayGate::beforeQuery($this->sql);

        return parent::execute();
    }
}

final class RaceDelayGate
{
    private static bool $held = false;

    public static function beforeQuery(string $sql): void
    {
        $dir = getenv('INVOICE_RACE_DIR') ?: '';
        $role = getenv('INVOICE_RACE_ROLE') ?: '';
        if ($dir !== '') {
            file_put_contents(
                $dir.'/sql-'.$role.'.log',
                $sql."\n----\n",
                FILE_APPEND | LOCK_EX
            );
        }

        if ($role !== 'A' || self::$held || $dir === '') {
            return;
        }
        if (!preg_match('/update\s+`?invoice`?\s+set\b/i', $sql)) {
            return;
        }

        self::$held = true;
        file_put_contents($dir.'/a-holding-write', (string) microtime(true));
        sleep(5);
        file_put_contents($dir.'/a-released', (string) microtime(true));
    }
}
