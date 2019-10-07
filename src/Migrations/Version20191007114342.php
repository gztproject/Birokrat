<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191007114342 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	
    public function getDescription() : string
    {
        return 'Refactored client -> partner';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        
        $this->addSql('RENAME TABLE client TO partner');   
        $this->addSql('ALTER TABLE partner ADD is_supplier TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE partner ADD is_client TINYINT(1) NOT NULL');
    }
    
    public function postUp(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$sql = "UPDATE partner SET is_supplier = 0, is_client = 1 WHERE 1";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();    	    	
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE partner DROP COLUMN is_supplier, is_client');
        $this->addSql('RENAME TABLE partner TO client');        
        
    }
}
