<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Ramsey\Uuid\Uuid;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190619180140 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	private $dbMigratorId;
	private $konto110id;
	private $konto120id;
	private $konto760id;
	private $konto285id;
	
	public function getDescription() : string
	{
		return 'Added Credit and Debit fields to Kontos';
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
		
		$sql = "SELECT k.id FROM konto AS k WHERE k.number = 110";
		$stmt = $em->getConnection()->prepare($sql);
		$stmt->execute();
		$res = $stmt->fetchAll();
		if($res != null)
			$this->konto110id = $res[0]['id'];
		$em->flush();
		
		$sql = "SELECT k.id FROM konto AS k WHERE k.number = 120";
		$stmt = $em->getConnection()->prepare($sql);
		$stmt->execute();
		$res = $stmt->fetchAll();
		if($res != null)
			$this->konto120id = $res[0]['id'];
		$em->flush();
		
		$sql = "SELECT k.id FROM konto AS k WHERE k.number = 760";
		$stmt = $em->getConnection()->prepare($sql);
		$stmt->execute();
		$res = $stmt->fetchAll();
		if($res != null)
			$this->konto760id = $res[0]['id'];
		$em->flush();
	}

    public function up(Schema $schema) : void
    {
    	
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        
        $this->addSql('ALTER TABLE transaction ADD debit_konto_id CHAR(36) COMMENT \'(DC2Type:uuid)\' DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D11D53F7D8 FOREIGN KEY (debit_konto_id) REFERENCES konto (id)');
        $this->addSql('CREATE INDEX IDX_723705D1DE39B264 ON transaction (debit_konto_id)');
        $this->addSql('ALTER TABLE transaction CHANGE konto_id credit_konto_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'(DC2Type:uuid)\';');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d151b48cda TO IDX_723705D1FA5F35E4');
        
        $this->addSql('ALTER TABLE konto_category ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE konto ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE konto_class ADD debit DOUBLE PRECISION NOT NULL, ADD credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX UNIQ_723705D1AA203AA8, ADD INDEX IDX_723705D1AA203AA8 (travel_expense_id)');        
		$this->addSql('ALTER TABLE transaction DROP INDEX UNIQ_723705D12989F1FD, ADD INDEX IDX_723705D12989F1FD (invoice_id)');
		
		
    }
    
    public function postUp(Schema $schema) : void
    {
    	//Get EntityManager
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	    	
    		
    		$sql = "SELECT t.id, t.sum, t.konto_id, t.date, t.invoice_id, i.state, i.date_paid FROM transaction AS t LEFT OUTER JOIN invoice as i ON i.id = t.invoice_id WHERE t.invoice_id IS NOT NULL";
    		$stmt = $em->getConnection()->prepare($sql);
    		$stmt->execute();
    		$transactions = $stmt->fetchAll();
    		$em->flush();
    		$debit110 = 0.0;
    		$credit120 = 0.0;
    		$credit760 = 0.0;
    		$debit120 = 0.0;
    		
    		if($transactions!=null)
    		{
    			foreach($transactions as $t)
    			{
    				$id = $t['id'];
    				$sum = $t['sum']*1;
    				$konto = $this->konto760id;
    				$counterKonto = $this->konto120id;
    				$date = date('Y-m-d H:i:s', strtotime($t['date']));
    				$sql = "UPDATE transaction SET counter_konto_id = '$counterKonto', created_on = '$date' WHERE `transaction`.`id` = '$id';";
    				$debit120 += $sum;
    				$credit760 += $sum;
    				
    				$stmt = $em->getConnection()->prepare($sql);
    				$stmt->execute();
    				$em->flush();
    				
    				if($t['date_paid'] != null)
    				{
    					$id = Uuid::uuid1();
    					$datePaid = date('Y-m-d H:i:s', strtotime($t['date_paid']));
    					$konto = $this->konto120id;
    					$counterKonto = $this->konto110id;
    					    					
    					$inv = $t['invoice_id'];
    					$sql = "INSERT INTO transaction (id, date, sum, konto_id, counter_konto_id, invoice_id, travel_expense_id, travel_expense_bundle_id, created_by_id, updated_by_id, created_on, updated_on)
						 VALUES ('$id', '$datePaid', '$sum', '$konto', '$counterKonto', '$inv', NULL, NULL, '$this->dbMigratorId', NULL, '$datePaid', NULL);";
    					$debit110 += $sum;
    					$credit120 += $sum;
    					$stmt = $em->getConnection()->prepare($sql);
    					$stmt->execute();
    					$em->flush();
    				}
    				
    			}
    			$sql = "UPDATE konto SET debit = $debit110, updated_on = '$datePaid', updated_by_id = '$this->dbMigratorId' WHERE id = '$this->konto110id';";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    			
    			$sql .= "UPDATE konto SET credit = $credit120, debit = $debit120, updated_on = '$datePaid', updated_by_id = '$this->dbMigratorId' WHERE id = '$this->konto120id';";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    			
    			$sql = "UPDATE konto SET credit = $credit760, updated_on = '$datePaid', updated_by_id = '$this->dbMigratorId' WHERE id = '$this->konto760id';";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    			
    			$sql = "SELECT k.id FROM konto AS k WHERE k.number = 486";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$res = $stmt->fetchAll();
    			if($res != null)
    				$konto486id = $res[0]['id'];
    			$em->flush();
    			
    			$sql = "SELECT k.id FROM konto AS k WHERE k.number = 285";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$res = $stmt->fetchAll();
    			if($res != null)
    				$konto285id = $res[0]['id'];
    			$em->flush();
    			
    			$sql = "SELECT te.*, (te.total_distance * te.rate) AS sum FROM travel_expense AS te WHERE te.state=10";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$travelExpenses = $stmt->fetchAll();
    			$em->flush();
    			$debit486 = 0.0;
    			$credit285 = 0.0;
    			    			
    			if($travelExpenses!=null)
    			{
    				foreach($travelExpenses as $te)
    				{
    					$id = Uuid::uuid1();
    					$date = $te['date'];
    					$konto = $konto285id;
    					$counterKonto = $konto486id;
    					$sum = $te['sum'];
    					
    					$expense = $te['id'];
    					$sql = "INSERT INTO transaction (id, date, sum, konto_id, counter_konto_id, invoice_id, travel_expense_id, travel_expense_bundle_id, created_by_id, updated_by_id, created_on, updated_on)
						 VALUES ('$id', '$date', '$sum', '$konto', '$counterKonto', NULL, '$expense', NULL, '$this->dbMigratorId', NULL, '$date', NULL);";
    					$debit486 += $sum;
    					$credit285 += $sum;
    					$stmt = $em->getConnection()->prepare($sql);
    					$stmt->execute();
    					$em->flush();
    				}
    			}
    			
    			$sql = "UPDATE konto SET debit = $debit486, updated_on = '$date', updated_by_id = '$this->dbMigratorId' WHERE id = '$konto486id';";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    			
    			$sql = "UPDATE konto SET credit = $credit285, updated_on = '$date', updated_by_id = '$this->dbMigratorId' WHERE id = '$konto285id';";
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    		}
    		$sql ='ALTER TABLE transaction CHANGE counter_konto_id counter_konto_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'';
    		$stmt = $em->getConnection()->prepare($sql);
    		$stmt->execute();
    		$em->flush();
    }

    public function preDown(Schema $schema) : void
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
    		
    	$sql = "SELECT k.id FROM konto AS k WHERE k.number = 120";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	if($res != null)
    		$this->konto120id = $res[0]['id'];
    	$em->flush();
    			
    	$sql = "SELECT k.id FROM konto AS k WHERE k.number = 285";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	if($res != null)
    		$this->konto285id = $res[0]['id'];
    	$em->flush();
    	
    	$sql = "DELETE FROM transaction where credit_konto_id = '$this->konto120id'";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();
    	
    	$sql = "DELETE FROM transaction where credit_konto_id = '$this->konto285id'";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();
    }
    
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE konto DROP debit, DROP credit');
        $this->addSql('ALTER TABLE konto_category DROP debit, DROP credit');
        $this->addSql('ALTER TABLE konto_class DROP debit, DROP credit');        
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D11D53F7D8');
        $this->addSql('DROP INDEX IDX_723705D1DE39B264 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP debit_konto_id'); 
        $this->addSql('ALTER TABLE transaction DROP INDEX IDX_723705D1AA203AA8, ADD UNIQUE INDEX UNIQ_723705D1AA203AA8 (travel_expense_id)');
        $this->addSql('ALTER TABLE transaction DROP INDEX IDX_723705D12989F1FD, ADD UNIQUE INDEX UNIQ_723705D12989F1FD (invoice_id)');
        $this->addSql('ALTER TABLE transaction CHANGE credit_konto_id konto_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'(DC2Type:uuid)\';');
    }
}
