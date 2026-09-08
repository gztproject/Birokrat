<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen legal-entity email for recipient lists and add partner extra emails.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partner CHANGE email email VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization CHANGE email email VARCHAR(2048) DEFAULT NULL');
        $this->addSql('CREATE TABLE partner_email (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', partner_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', email VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, role VARCHAR(255) DEFAULT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_PARTNER_EMAIL_PARTNER (partner_id), INDEX IDX_PARTNER_EMAIL_CREATED (created_by_id), INDEX IDX_PARTNER_EMAIL_UPDATED (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE partner_email ADD CONSTRAINT FK_PARTNER_EMAIL_PARTNER FOREIGN KEY (partner_id) REFERENCES partner (id)');
        $this->addSql('ALTER TABLE partner_email ADD CONSTRAINT FK_PARTNER_EMAIL_CREATED FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE partner_email ADD CONSTRAINT FK_PARTNER_EMAIL_UPDATED FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partner_email DROP FOREIGN KEY FK_PARTNER_EMAIL_PARTNER');
        $this->addSql('ALTER TABLE partner_email DROP FOREIGN KEY FK_PARTNER_EMAIL_CREATED');
        $this->addSql('ALTER TABLE partner_email DROP FOREIGN KEY FK_PARTNER_EMAIL_UPDATED');
        $this->addSql('DROP TABLE partner_email');
        $this->addSql('ALTER TABLE partner CHANGE email email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization CHANGE email email VARCHAR(255) DEFAULT NULL');
    }
}
