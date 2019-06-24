<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190624102453 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	private $dbMigratorId;
	private $organization;
	
    public function getDescription() : string
    {
        return 'Added Organization to Transaction and TE';
    }
    
    public function preUp(Schema $schema) : void
    {
    	//Get EntityManager
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	
    	{
    		//Check if MigrationUser already exists and create it if not. We have to do it manually as we don't have the new fields yet.
	    	$sql = "SELECT * FROM `app_users` WHERE username = 'dbMigrator' ";
	    	
	    	$stmt = $em->getConnection()->prepare($sql);
	    	$stmt->execute();
	    	$dbMigratorUser = $stmt->fetchAll();
	    	
	    	if($dbMigratorUser==null)
	    	{
	    		//System users are created directly in DB as we need an existing user to do it utherwise.
	    		$this->dbMigratorId = Uuid::uuid1();
	    		$sql = "INSERT INTO `app_users` (`id`, `username`, `first_name`, `last_name`, `password`, `roles`, `email`, `mobile`, `phone`, `is_active`)
					VALUES ('$this->dbMigratorId','DbMigrator','Database','Migrator','','','','','',0)";
	    		$stmt = $em->getConnection()->prepare($sql);
	    		$stmt->execute();
	    		$em->flush();
	    	}
	    	else
	    	{
	    		$this->dbMigratorId = $dbMigratorUser[0]["id"];
	    	}
    	}
    	{
	    	$sql = "SELECT o.id, o.name FROM organization AS o";
	    	$stmt = $em->getConnection()->prepare($sql);
	    	$stmt->execute();
	    	$res = $stmt->fetchAll();	    	
	    	
	    	if($res==null)
	    	{
	    		$this->organization = '00000000-0000-0000-0000-000000000000';
	    	}
	    	else
	    	{
	    		$this->organization = $res[0]["id"];
	    	}
    	}
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction ADD organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->organization.'\'');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D132C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('CREATE INDEX IDX_723705D132C8A3DE ON transaction (organization_id)');
        $this->addSql('ALTER TABLE travel_expense ADD organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->organization.'\'');
        $this->addSql('ALTER TABLE travel_expense ADD CONSTRAINT FK_EC793AB732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('CREATE INDEX IDX_EC793AB732C8A3DE ON travel_expense (organization_id)');
        
        $this->addSql("UPDATE transaction t 
						INNER JOIN invoice AS i ON i.id = t.invoice_id 
						SET t.organization_id = i.issuer_id 
						WHERE t.invoice_id IS NOT NULL;");
        
        $this->addSql("UPDATE travel_expense tr 
						INNER JOIN app_users AS u ON u.id = tr.employee_id 
						INNER JOIN (SELECT * FROM organization_user LIMIT 1) ou 
							ON ou.user_id = u.id 
						SET tr.organization_id = ou.organization_id;");
        
        $this->addSql("UPDATE transaction t
						INNER JOIN travel_expense AS te ON te.id = t.travel_expense_id
						SET t.organization_id = te.organization_id
						WHERE t.travel_expense_id IS NOT NULL;");
        
        $this->addSql('ALTER TABLE organization_settings ADD travel_expense_rate DOUBLE PRECISION DEFAULT 0.37');
    }
    
    public function postUp(Schema $schema) : void
    {	
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	{
	    	$sql = "ALTER TABLE transaction ALTER organization_id DROP DEFAULT;";
	    	$stmt = $em->getConnection()->prepare($sql);
	    	$stmt->execute();
	    	$sql = "ALTER TABLE travel_expense ALTER organization_id DROP DEFAULT;";
	    	$stmt = $em->getConnection()->prepare($sql);
	    	$stmt->execute();
	    	
	    	$em->flush(); 
    	}
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D132C8A3DE');
        $this->addSql('DROP INDEX IDX_723705D132C8A3DE ON transaction');
        $this->addSql('ALTER TABLE transaction DROP organization_id');
        $this->addSql('ALTER TABLE travel_expense DROP FOREIGN KEY FK_EC793AB732C8A3DE');
        $this->addSql('DROP INDEX IDX_EC793AB732C8A3DE ON travel_expense');
        $this->addSql('ALTER TABLE travel_expense DROP organization_id');
        $this->addSql('ALTER TABLE organization_settings DROP travel_expense_rate');
    }
}
