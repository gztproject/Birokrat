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
final class Version20190622112701 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;	
	private $dbMigratorId;
	
    public function getDescription() : string
    {
        return 'Added KontoPreferences on OrganizationSettings';
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

        $this->addSql('CREATE TABLE konto_preference (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', issue_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', issue_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', invoice_paid_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', invoice_paid_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', incurred_travel_expense_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', incurred_travel_expense_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', paid_travel_expense_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', paid_travel_expense_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_ABFFA51442D7E17E (issue_invoice_debit_id), INDEX IDX_ABFFA514DE910D54 (issue_invoice_credit_id), INDEX IDX_ABFFA5142EB2B7FC (invoice_paid_debit_id), INDEX IDX_ABFFA5143DD5A284 (invoice_paid_credit_id), INDEX IDX_ABFFA51498B8BBDD (incurred_travel_expense_debit_id), INDEX IDX_ABFFA514710AB8D6 (incurred_travel_expense_credit_id), INDEX IDX_ABFFA5143B5EA62B (paid_travel_expense_debit_id), INDEX IDX_ABFFA514257709E2 (paid_travel_expense_credit_id), INDEX IDX_ABFFA514B03A8386 (created_by_id), INDEX IDX_ABFFA514896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA51442D7E17E FOREIGN KEY (issue_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA514DE910D54 FOREIGN KEY (issue_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA5142EB2B7FC FOREIGN KEY (invoice_paid_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA5143DD5A284 FOREIGN KEY (invoice_paid_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA51498B8BBDD FOREIGN KEY (incurred_travel_expense_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA514710AB8D6 FOREIGN KEY (incurred_travel_expense_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA5143B5EA62B FOREIGN KEY (paid_travel_expense_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA514257709E2 FOREIGN KEY (paid_travel_expense_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA514B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE konto_preference ADD CONSTRAINT FK_ABFFA514896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE organization_settings ADD konto_preference_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267831C6916 FOREIGN KEY (konto_preference_id) REFERENCES konto_preference (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A5D6267831C6916 ON organization_settings (konto_preference_id)');
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
    		{
    			$id = Uuid::uuid1();
    			$sql = "INSERT INTO konto_preference(id, issue_invoice_debit_id, issue_invoice_credit_id, invoice_paid_debit_id, invoice_paid_credit_id, incurred_travel_expense_debit_id, incurred_travel_expense_credit_id, paid_travel_expense_debit_id, paid_travel_expense_credit_id, created_by_id, created_on) 
					VALUES ('$id', '".$kontos['120']."', '".$kontos['760']."', '".$kontos['110']."', '".$kontos['120']."', '".$kontos['486']."', '".$kontos['285']."', '".$kontos['285']."', '".$kontos['919']."', '$this->dbMigratorId', '$date');";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();    			
    			$em->flush();
    			$sql = "UPDATE organization_settings SET konto_preference_id = '$id', updated_by_id='$this->dbMigratorId', updated_on='$date' WHERE id='".$os['id']."'";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    		}
    	
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267831C6916');
        $this->addSql('DROP TABLE konto_preference');
        $this->addSql('DROP INDEX UNIQ_A5D6267831C6916 ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings DROP konto_preference_id');
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
