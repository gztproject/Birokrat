<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191123115239 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove credit && debit fields from konto';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE konto_category DROP debit, DROP credit');
        $this->addSql('ALTER TABLE konto DROP debit, DROP credit');
        $this->addSql('ALTER TABLE konto_class DROP debit, DROP credit');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE konto ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE konto_category ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE konto_class ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
    }
}
