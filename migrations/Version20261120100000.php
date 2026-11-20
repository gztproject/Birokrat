<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track used TOTP time slices and failed TOTP codes per user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_users ADD totp_last_used_slice INT DEFAULT NULL, ADD totp_failed_attempts INT DEFAULT 0 NOT NULL, ADD totp_last_failure_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_users DROP totp_last_used_slice, DROP totp_failed_attempts, DROP totp_last_failure_at');
    }
}
