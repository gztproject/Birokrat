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
final class Version20190923191631 extends AbstractMigration implements ContainerAwareInterface
{
	
	use ContainerAwareTrait;
	private $dbMigratorId;
	
    public function getDescription() : string
    {
        return 'Add IncomingInvoice related entities';
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

        $this->addSql('CREATE TABLE incoming_invoice (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', issuer_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', recepient_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', date_of_issue DATE NOT NULL, number VARCHAR(255) NOT NULL, price NUMERIC(15, 2) NOT NULL, reference_number VARCHAR(50) NOT NULL, state INT NOT NULL, due_date DATE NOT NULL, date_paid DATE DEFAULT NULL, date_rejected DATE DEFAULT NULL, rejected_reason VARCHAR(100) DEFAULT NULL, date_refunded DATE DEFAULT NULL, refund_reason VARCHAR(100) DEFAULT NULL, note VARCHAR(512) NOT NULL, scan_filename VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_95B404A6BB9D6FEE (issuer_id), INDEX IDX_95B404A6F1B7C6C (recepient_id), INDEX IDX_95B404A6B03A8386 (created_by_id), INDEX IDX_95B404A6896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE incoming_invoice ADD CONSTRAINT FK_95B404A6BB9D6FEE FOREIGN KEY (issuer_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE incoming_invoice ADD CONSTRAINT FK_95B404A6F1B7C6C FOREIGN KEY (recepient_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE incoming_invoice ADD CONSTRAINT FK_95B404A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE incoming_invoice ADD CONSTRAINT FK_95B404A6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE transaction ADD incoming_invoice_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D113D456E6 FOREIGN KEY (incoming_invoice_id) REFERENCES incoming_invoice (id)');
        $this->addSql('CREATE INDEX IDX_723705D113D456E6 ON transaction (incoming_invoice_id)');
        $this->addSql('ALTER TABLE organization_settings ADD received_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD received_incoming_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD paid_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD paid_incoming_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD refunded_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD refunded_incoming_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD rejected_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD rejected_incoming_invoice_debit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62675161DF40 FOREIGN KEY (received_incoming_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62679C5A9EA8 FOREIGN KEY (received_incoming_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267AC174C0E FOREIGN KEY (paid_incoming_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62678830AAB2 FOREIGN KEY (paid_incoming_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267819A02B3 FOREIGN KEY (refunded_incoming_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62677919A4D FOREIGN KEY (refunded_incoming_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626719479F50 FOREIGN KEY (rejected_incoming_invoice_credit_id) REFERENCES konto (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626782CC50F FOREIGN KEY (rejected_incoming_invoice_debit_id) REFERENCES konto (id)');
        $this->addSql('CREATE INDEX IDX_A5D62675161DF40 ON organization_settings (received_incoming_invoice_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62679C5A9EA8 ON organization_settings (received_incoming_invoice_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267AC174C0E ON organization_settings (paid_incoming_invoice_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62678830AAB2 ON organization_settings (paid_incoming_invoice_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267819A02B3 ON organization_settings (refunded_incoming_invoice_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D62677919A4D ON organization_settings (refunded_incoming_invoice_debit_id)');
        $this->addSql('CREATE INDEX IDX_A5D626719479F50 ON organization_settings (rejected_incoming_invoice_credit_id)');
        $this->addSql('CREATE INDEX IDX_A5D626782CC50F ON organization_settings (rejected_incoming_invoice_debit_id)');
    }
    
    public function postUp(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$kontos = $this->getKontos([400,220,110]);
    	$date = date('Y-m-d H:i:s');
    	$sql = "SELECT o.* FROM organization_settings AS o WHERE 1";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	$em->flush();
    	if($res != null)
    		foreach($res as $os)
    		{   $sql = "UPDATE organization_settings SET
						received_incoming_invoice_credit_id='".$kontos['400']."',
						received_incoming_invoice_debit_id='".$kontos['220']."',
						paid_incoming_invoice_credit_id='".$kontos['220']."',
						paid_incoming_invoice_debit_id='".$kontos['110']."',
						refunded_incoming_invoice_credit_id='".$kontos['110']."',
						refunded_incoming_invoice_debit_id='".$kontos['400']."',
						rejected_incoming_invoice_credit_id='".$kontos['220']."',
						rejected_incoming_invoice_debit_id='".$kontos['400']."',
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

        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D113D456E6');
        $this->addSql('DROP TABLE incoming_invoice');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62675161DF40');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62679C5A9EA8');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267AC174C0E');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62678830AAB2');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267819A02B3');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62677919A4D');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626719479F50');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626782CC50F');
        $this->addSql('DROP INDEX IDX_A5D62675161DF40 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62679C5A9EA8 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267AC174C0E ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62678830AAB2 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267819A02B3 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D62677919A4D ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D626719479F50 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D626782CC50F ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings DROP received_incoming_invoice_credit_id, DROP received_incoming_invoice_debit_id, DROP paid_incoming_invoice_credit_id, DROP paid_incoming_invoice_debit_id, DROP refunded_incoming_invoice_credit_id, DROP refunded_incoming_invoice_debit_id, DROP rejected_incoming_invoice_credit_id, DROP rejected_incoming_invoice_debit_id');
        $this->addSql('DROP INDEX IDX_723705D113D456E6 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP incoming_invoice_id');
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
