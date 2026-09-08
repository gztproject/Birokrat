<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Companion transactions, travel-expense PDF fields, user position and optional TOTP.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction ADD role VARCHAR(32) DEFAULT NULL, ADD related_transaction_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1B8C987A5 FOREIGN KEY (related_transaction_id) REFERENCES transaction (id)');
        $this->addSql('CREATE INDEX IDX_723705D1B8C987A5 ON transaction (related_transaction_id)');
        $this->addSql('ALTER TABLE organization_settings ADD bank_fee_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD bank_fee_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267BANKFE1 FOREIGN KEY (bank_fee_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267BANKFE2 FOREIGN KEY (bank_fee_credit_id) REFERENCES konto (id)');
        $this->addSql('CREATE INDEX IDX_A5D6267BANKFE1 ON organization_settings (bank_fee_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267BANKFE2 ON organization_settings (bank_fee_credit_id)');
        $this->addSql('ALTER TABLE travel_expense ADD reason VARCHAR(255) DEFAULT NULL, ADD number VARCHAR(50) DEFAULT NULL, ADD advance NUMERIC(15, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_users ADD position VARCHAR(255) DEFAULT NULL, ADD totp_secret VARCHAR(64) DEFAULT NULL, ADD totp_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1B8C987A5');
        $this->addSql('DROP INDEX IDX_723705D1B8C987A5 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP role, DROP related_transaction_id');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267BANKFE1');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267BANKFE2');
        $this->addSql('DROP INDEX IDX_A5D6267BANKFE1 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267BANKFE2 ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings DROP bank_fee_debit_id, DROP bank_fee_credit_id');
        $this->addSql('ALTER TABLE travel_expense DROP reason, DROP number, DROP advance');
        $this->addSql('ALTER TABLE app_users DROP position, DROP totp_secret, DROP totp_enabled');
    }
}
