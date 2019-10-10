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
final class Version20190903173730 extends AbstractMigration implements ContainerAwareInterface
{
	
	use ContainerAwareTrait;
	private $dbMigratorId;
	
	
    public function getDescription() : string
    {
        return 'Add konto preferences to OrganizationSettings';
    }
    
    public function preUp(Schema $schema) : void
    {
    	//Get EntityManager
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	
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

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        
        $this->addSql('DROP INDEX UNIQ_5C844C5A76ED395 ON user_settings');
        $this->addSql('DROP INDEX UNIQ_A5D626732C8A3DE ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings ADD issue_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', 
			ADD issue_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD invoice_paid_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', 
			ADD invoice_paid_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD incurred_travel_expense_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', 
			ADD incurred_travel_expense_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD paid_travel_expense_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', 
			ADD paid_travel_expense_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE travel_expense_rate travel_expense_rate DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62679167574 FOREIGN KEY (issue_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267DE910D54 FOREIGN KEY (issue_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62672EB2B7FC FOREIGN KEY (invoice_paid_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62673DD5A284 FOREIGN KEY (invoice_paid_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626798B8BBDD FOREIGN KEY (incurred_travel_expense_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267710AB8D6 FOREIGN KEY (incurred_travel_expense_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62673B5EA62B FOREIGN KEY (paid_travel_expense_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267257709E2 FOREIGN KEY (paid_travel_expense_credit_id) REFERENCES konto (id)');
        $this->addSql('CREATE INDEX IDX_A5D62679167574 ON organization_settings (issue_invoice_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267DE910D54 ON organization_settings (issue_invoice_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62672EB2B7FC ON organization_settings (invoice_paid_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62673DD5A284 ON organization_settings (invoice_paid_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D626798B8BBDD ON organization_settings (incurred_travel_expense_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267710AB8D6 ON organization_settings (incurred_travel_expense_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62673B5EA62B ON organization_settings (paid_travel_expense_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267257709E2 ON organization_settings (paid_travel_expense_credit_id)');
    }
    
    public function postUp(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$kontos = $this->getKontos([120,760,110,486,285,919]);
    	$date = date('Y-m-d H:i:s');
    	$sql = "SELECT o.* FROM organization_settings AS o WHERE 1";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	$em->flush();
    	if($res != null)
    		foreach($res as $os)
    		{   $sql = "UPDATE organization_settings SET 
						issue_invoice_debit_id='".$kontos['120']."',
						issue_invoice_credit_id='".$kontos['760']."',
						invoice_paid_debit_id='".$kontos['110']."',
						invoice_paid_credit_id='".$kontos['120']."',
						incurred_travel_expense_debit_id='".$kontos['486']."',
						incurred_travel_expense_credit_id='".$kontos['285']."',
						paid_travel_expense_debit_id='".$kontos['285']."',
						paid_travel_expense_credit_id='".$kontos['919']."',
						updated_by_id='$this->dbMigratorId', updated_on='$date' WHERE organization_id='".$os['organization_id']."'";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    		}
    	
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62679167574');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267DE910D54');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62672EB2B7FC');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62673DD5A284');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626798B8BBDD');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267710AB8D6');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62673B5EA62B');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267257709E2');
        $this->addSql('DROP INDEX IDX_A5D62679167574 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267DE910D54 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62672EB2B7FC ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62673DD5A284 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D626798B8BBDD ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267710AB8D6 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62673B5EA62B ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267257709E2 ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings DROP issue_invoice_debit_id, DROP issue_invoice_credit_id, DROP invoice_paid_debit_id, DROP invoice_paid_credit_id, DROP incurred_travel_expense_debit_id, DROP incurred_travel_expense_credit_id, DROP paid_travel_expense_debit_id, DROP paid_travel_expense_credit_id, CHANGE travel_expense_rate travel_expense_rate DOUBLE PRECISION DEFAULT \'0.37\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A5D626732C8A3DE ON organization_settings (organization_id)');        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C844C5A76ED395 ON user_settings (user_id)');
    }
    
    private function getKontos(array $numbers): array
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$kontos = [];
    	foreach ($numbers as $number)
    	{
    		$sql = "SELECT k.id FROM konto AS k WHERE k.number = $number";
    		$stmt = $em->getConnection()->prepare($sql);
    		$stmt->execute();
    		$res = $stmt->fetchAll();
    		if($res != null)
    			$kontos[$number] = $res[0]['id'];
    			$em->flush();
    	}
    	return $kontos;
    }
}
