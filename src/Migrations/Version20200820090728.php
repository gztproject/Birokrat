<?php
declare ( strict_types = 1 )
	;

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200820090728 extends AbstractMigration implements ContainerAwareInterface {
	use ContainerAwareTrait;
	private $konto221id;
	public function getDescription(): string {
		return 'Separate credit kontos for home and foreign incoming invoices';
	}
	public function preUp(Schema $schema): void {
		// Get EntityManager
		$em = $this->container->get ( 'doctrine.orm.entity_manager' );

		$sql = "SELECT k.id FROM konto AS k WHERE k.number = 221";
		$stmt = $em->getConnection ()->prepare ( $sql );
		$stmt->execute ();
		$res = $stmt->fetchAll ();
		if ($res != null)
			$this->konto221id = $res [0] ['id'];
		$em->flush ();		
	}
	
	public function up(Schema $schema): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf ( $this->connection->getDatabasePlatform ()->getName () !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql ( 'ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D62675161DF40' );
		$this->addSql ( 'DROP INDEX IDX_A5D62675161DF40 ON organization_settings' );
		$this->addSql ( 'ALTER TABLE organization_settings ADD received_foreign_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE received_incoming_invoice_credit_id received_home_incoming_invoice_credit_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'' );
		$this->addSql ( 'ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626740F2E438 FOREIGN KEY (received_home_incoming_invoice_credit_id) REFERENCES konto (id)' );
		$this->addSql ( 'ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D626786AFABE0 FOREIGN KEY (received_foreign_incoming_invoice_credit_id) REFERENCES konto (id)' );
		$this->addSql ( 'CREATE INDEX IDX_A5D626740F2E438 ON organization_settings (received_home_incoming_invoice_credit_id)' );
		$this->addSql ( 'CREATE INDEX IDX_A5D626786AFABE0 ON organization_settings (received_foreign_incoming_invoice_credit_id)' );
		$this->addSql ( 'UPDATE organization_settings SET received_foreign_incoming_invoice_credit_id = "'.$this->konto221id.'" WHERE 1' );
	}
	
	public function down(Schema $schema): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf ( $this->connection->getDatabasePlatform ()->getName () !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql ( 'ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626740F2E438' );
		$this->addSql ( 'ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D626786AFABE0' );
		$this->addSql ( 'DROP INDEX IDX_A5D626740F2E438 ON organization_settings' );
		$this->addSql ( 'DROP INDEX IDX_A5D626786AFABE0 ON organization_settings' );
		$this->addSql ( 'ALTER TABLE organization_settings ADD received_incoming_invoice_credit_id CHAR(36) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:uuid)\', DROP received_home_incoming_invoice_credit_id, DROP received_foreign_incoming_invoice_credit_id' );
		$this->addSql ( 'ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D62675161DF40 FOREIGN KEY (received_incoming_invoice_credit_id) REFERENCES konto (id)' );
		$this->addSql ( 'CREATE INDEX IDX_A5D62675161DF40 ON organization_settings (received_incoming_invoice_credit_id)' );
	}
}
