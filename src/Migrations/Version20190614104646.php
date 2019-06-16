<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190614104646 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE travel_expense_bundle (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE travel_expense ADD travel_expense_bundle_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE travel_expense ADD CONSTRAINT FK_EC793AB71C4CE41A FOREIGN KEY (travel_expense_bundle_id) REFERENCES travel_expense_bundle (id)');
        $this->addSql('CREATE INDEX IDX_EC793AB71C4CE41A ON travel_expense (travel_expense_bundle_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE travel_expense DROP FOREIGN KEY FK_EC793AB71C4CE41A');
        $this->addSql('DROP TABLE travel_expense_bundle');
        $this->addSql('DROP INDEX IDX_EC793AB71C4CE41A ON travel_expense');
        $this->addSql('ALTER TABLE travel_expense DROP travel_expense_bundle_id');
    }
}
