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
final class Version20191029162237 extends AbstractMigration implements ContainerAwareInterface
{
	
	use ContainerAwareTrait;
	private $dbMigratorId;
	
    public function getDescription() : string
    {
        return 'Add LunchExpense to Transaction';
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

        $this->addSql('CREATE TABLE lunch_expense (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', date DATE NOT NULL, sum NUMERIC(15, 2) NOT NULL, state INT NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_C44D418032C8A3DE (organization_id), INDEX IDX_C44D4180B03A8386 (created_by_id), INDEX IDX_C44D4180896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lunch_expense ADD CONSTRAINT FK_C44D418032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE lunch_expense ADD CONSTRAINT FK_C44D4180B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE lunch_expense ADD CONSTRAINT FK_C44D4180896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE transaction ADD lunch_expense_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD description VARCHAR(511) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1667B9D1D FOREIGN KEY (lunch_expense_id) REFERENCES lunch_expense (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_723705D1667B9D1D ON transaction (lunch_expense_id)');
        $this->addSql('ALTER TABLE organization_settings ADD auto_create_per_diem TINYINT(1) NOT NULL, ADD per_diem_value DOUBLE PRECISION DEFAULT NULL, ADD auto_create_lunch TINYINT(1) NOT NULL, ADD lunch_value DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE organization_settings DROP INDEX idx_a5d6267ac174c0e, ADD INDEX IDX_A5D62679F31953D (paid_cash_incoming_invoice_credit_id)');
        $this->addSql('ALTER TABLE organization_settings DROP INDEX idx_a5d6267cb76466a, ADD INDEX IDX_A5D62678FEC03C6 (paid_transaction_incoming_invoice_credit_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_c7440455f5b7af75, ADD INDEX IDX_312B3E16F5B7AF75 (address_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_c7440455b03a8386, ADD INDEX IDX_312B3E16B03A8386 (created_by_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_c7440455896dbbde, ADD INDEX IDX_312B3E16896DBBDE (updated_by_id)');
    }
    
    public function postUp(Schema $schema) : void
    {
    	//As of 2019 in Slovenia
    	$lunchValue = 6.12;
    	$perDiemValue = 21.39;
    	
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$kontos = $this->getKontos([486,285,919]);
    	$date = date('Y-m-d H:i:s');
    	
    	$sql = "UPDATE organization_settings SET auto_create_per_diem = 0, auto_create_lunch = 1, per_diem_value = $perDiemValue, lunch_value = $lunchValue, updated_on = '$date', updated_by_id = '$this->dbMigratorId' WHERE 1";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();
    	
    	$sql = "SELECT te.date, te.organization_id FROM travel_expense AS te WHERE te.state = 10 GROUP BY te.date, te.organization_id";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	$em->flush();
    	if($res != null)
    	{
    		foreach($res as $te)
    		{   
    			$leId = Uuid::uuid1();
    			//ToDo: Maybe import the actual state?
    			$state = 10;
    			$sql = "INSERT INTO lunch_expense (id, date, sum, organization_id, state, created_by_id, created_on, updated_by_id, updated_on)
							VALUES ('$leId', '".$te['date']."', $lunchValue, '".$te['organization_id']."', ".$state.", '".$this->dbMigratorId."', '$date', '".$this->dbMigratorId."', '$date');";
    			
    			$sql .= "INSERT INTO transaction	(id, date, sum, credit_konto_id, invoice_id, travel_expense_id, travel_expense_bundle_id, created_by_id, updated_by_id, created_on, updated_on, debit_konto_id, organization_id, incoming_invoice_id, description, lunch_expense_id)
						 VALUES ('".Uuid::uuid1()."', '".$te['date']."', ".$lunchValue." ,'".$kontos['285']."', NULL, NULL, NULL, '".$this->dbMigratorId."', NULL, '$date', NULL, '".$kontos['486']."', '".$te['organization_id']."', NULL, NULL, '$leId');";
    		
    		
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    		}
    	}
    }
    
    public function preDown(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	
    	$sql = "DELETE FROM transaction WHERE lunch_expense_id IS NOT NULL AND created_by_id = '".$this->dbMigratorId."'";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1667B9D1D');
        $this->addSql('DROP TABLE lunch_expense');
        $this->addSql('ALTER TABLE organization_settings DROP auto_create_per_diem, DROP per_diem_value, DROP auto_create_lunch, DROP lunch_value');
        $this->addSql('ALTER TABLE organization_settings DROP INDEX idx_a5d62678fec03c6, ADD INDEX IDX_A5D6267CB76466A (paid_transaction_incoming_invoice_credit_id)');
        $this->addSql('ALTER TABLE organization_settings DROP INDEX idx_a5d62679f31953d, ADD INDEX IDX_A5D6267AC174C0E (paid_cash_incoming_invoice_credit_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_312b3e16b03a8386, ADD INDEX IDX_C7440455B03A8386 (created_by_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_312b3e16896dbbde, ADD INDEX IDX_C7440455896DBBDE (updated_by_id)');
        $this->addSql('ALTER TABLE partner DROP INDEX idx_312b3e16f5b7af75, ADD INDEX IDX_C7440455F5B7AF75 (address_id)');
        $this->addSql('DROP INDEX UNIQ_723705D1667B9D1D ON transaction');
        $this->addSql('ALTER TABLE transaction DROP lunch_expense_id, DROP description');
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
