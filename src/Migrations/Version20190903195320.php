<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190903195320 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add organizationId to TravelExpenseBundle';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE travel_expense_bundle ADD organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE travel_expense_bundle ADD CONSTRAINT FK_7410FCA532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('CREATE INDEX IDX_7410FCA532C8A3DE ON travel_expense_bundle (organization_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE travel_expense_bundle DROP FOREIGN KEY FK_7410FCA532C8A3DE');
        $this->addSql('DROP INDEX IDX_7410FCA532C8A3DE ON travel_expense_bundle');
        $this->addSql('ALTER TABLE travel_expense_bundle DROP organization_id');
    }
}
