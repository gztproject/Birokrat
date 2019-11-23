<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191123201853 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add LunchExpenseBundle';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE lunch_expense_bundle (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_491603EC32C8A3DE (organization_id), INDEX IDX_491603ECB03A8386 (created_by_id), INDEX IDX_491603EC896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lunch_expense_bundle ADD CONSTRAINT FK_491603EC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE lunch_expense_bundle ADD CONSTRAINT FK_491603ECB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE lunch_expense_bundle ADD CONSTRAINT FK_491603EC896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE transaction ADD lunch_expense_bundle_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D119400A2F FOREIGN KEY (lunch_expense_bundle_id) REFERENCES lunch_expense_bundle (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_723705D119400A2F ON transaction (lunch_expense_bundle_id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE lunch_expense ADD lunch_expense_bundle_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE lunch_expense ADD CONSTRAINT FK_C44D418019400A2F FOREIGN KEY (lunch_expense_bundle_id) REFERENCES lunch_expense_bundle (id)');
        $this->addSql('CREATE INDEX IDX_C44D418019400A2F ON lunch_expense (lunch_expense_bundle_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D119400A2F');
        $this->addSql('ALTER TABLE lunch_expense DROP FOREIGN KEY FK_C44D418019400A2F');
        $this->addSql('DROP TABLE lunch_expense_bundle');
        $this->addSql('DROP INDEX IDX_C44D418019400A2F ON lunch_expense');
        $this->addSql('ALTER TABLE lunch_expense DROP lunch_expense_bundle_id');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626732C8A3DE');
        $this->addSql('DROP INDEX UNIQ_723705D119400A2F ON transaction');
        $this->addSql('ALTER TABLE transaction DROP lunch_expense_bundle_id');
    }
}
