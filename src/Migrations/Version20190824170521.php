<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190824170521 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
    	$this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    	
    	$this->addSql('ALTER TABLE user_settings DROP COLUMN id');
    	$this->addSql('ALTER TABLE user_settings ADD PRIMARY KEY (user_id)');
    	$this->addSql('ALTER TABLE organization_settings DROP COLUMN id');
    	$this->addSql('ALTER TABLE organization_settings ADD PRIMARY KEY (organization_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    	$this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    	
    	$this->addSql('ALTER TABLE user_settings DROP PRIMARY KEY'); 
    	$this->addSql('ALTER TABLE user_settings ADD id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' FIRST');
    	$this->addSql('UPDATE user_settings SET id = user_id');
    	$this->addSql('ALTER TABLE user_settings ADD PRIMARY KEY (id)');
    	
    	$this->addSql('ALTER TABLE organization_settings DROP PRIMARY KEY');    	
    	$this->addSql('ALTER TABLE organization_settings ADD id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' FIRST');
    	$this->addSql('UPDATE organization_settings SET id = organization_id');
    	$this->addSql('ALTER TABLE organization_settings ADD PRIMARY KEY (id)');
    }
}
