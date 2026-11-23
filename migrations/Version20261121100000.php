<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make app_users.username unique so a username always resolves to one account at sign-in.';
    }

    public function up(Schema $schema): void
    {
        $duplicates = $this->connection->fetchFirstColumn(
            'SELECT username FROM app_users GROUP BY username HAVING COUNT(*) > 1'
        );
        $this->abortIf(
            $duplicates !== [],
            'Rename these duplicate usernames in app_users before migrating: '.implode(', ', $duplicates)
        );

        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USERS_USERNAME ON app_users (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_APP_USERS_USERNAME ON app_users');
    }
}
