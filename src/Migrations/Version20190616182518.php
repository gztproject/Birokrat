<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use App\Entity\Geography\Country;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\User\CreateUserCommand;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190616182518 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	private $dbMigratorId;
	
    public function getDescription() : string
    {
        return 'Added createdOn, createdBy, updatedOn and updatedBy fields to entities';
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

        $this->addSql('ALTER TABLE country ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE country ADD CONSTRAINT FK_5373C966B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE country ADD CONSTRAINT FK_5373C966896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_5373C966B03A8386 ON country (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5373C966896DBBDE ON country (updated_by_id)');
        $this->addSql('ALTER TABLE post ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DB03A8386 ON post (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D896DBBDE ON post (updated_by_id)');
        $this->addSql('ALTER TABLE address ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_D4E6F81B03A8386 ON address (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D4E6F81896DBBDE ON address (updated_by_id)');
        $this->addSql('ALTER TABLE transaction ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_723705D1B03A8386 ON transaction (created_by_id)');
        $this->addSql('CREATE INDEX IDX_723705D1896DBBDE ON transaction (updated_by_id)');
        $this->addSql('ALTER TABLE user_settings ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT FK_5C844C5B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT FK_5C844C5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_5C844C5B03A8386 ON user_settings (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5C844C5896DBBDE ON user_settings (updated_by_id)');
        $this->addSql('ALTER TABLE organization_settings ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE organization_settings ADD CONSTRAINT FK_A5D6267896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_A5D6267B03A8386 ON organization_settings (created_by_id)');
        $this->addSql('CREATE INDEX IDX_A5D6267896DBBDE ON organization_settings (updated_by_id)');
        $this->addSql('ALTER TABLE invoice ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_90651744B03A8386 ON invoice (created_by_id)');
        $this->addSql('CREATE INDEX IDX_90651744896DBBDE ON invoice (updated_by_id)');
        $this->addSql('ALTER TABLE invoice_item ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477BB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_1DDE477BB03A8386 ON invoice_item (created_by_id)');
        $this->addSql('CREATE INDEX IDX_1DDE477B896DBBDE ON invoice_item (updated_by_id)');
        $this->addSql('ALTER TABLE app_users ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE app_users ADD CONSTRAINT FK_C2502824B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE app_users ADD CONSTRAINT FK_C2502824896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_C2502824B03A8386 ON app_users (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C2502824896DBBDE ON app_users (updated_by_id)');
        $this->addSql('ALTER TABLE organization ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C1EE637C896DBBDE ON organization (updated_by_id)');
        $this->addSql('ALTER TABLE client ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_C7440455B03A8386 ON client (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C7440455896DBBDE ON client (updated_by_id)');
        $this->addSql('ALTER TABLE konto_category ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE konto_category ADD CONSTRAINT FK_5A2C4BFDB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE konto_category ADD CONSTRAINT FK_5A2C4BFD896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_5A2C4BFDB03A8386 ON konto_category (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5A2C4BFD896DBBDE ON konto_category (updated_by_id)');
        $this->addSql('ALTER TABLE konto ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE konto ADD CONSTRAINT FK_9F927005B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE konto ADD CONSTRAINT FK_9F927005896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_9F927005B03A8386 ON konto (created_by_id)');
        $this->addSql('CREATE INDEX IDX_9F927005896DBBDE ON konto (updated_by_id)');
        $this->addSql('ALTER TABLE konto_class ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE konto_class ADD CONSTRAINT FK_2F6A529DB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE konto_class ADD CONSTRAINT FK_2F6A529D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_2F6A529DB03A8386 ON konto_class (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2F6A529D896DBBDE ON konto_class (updated_by_id)');
        $this->addSql('ALTER TABLE travel_stop ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE travel_stop ADD CONSTRAINT FK_720EF51BB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE travel_stop ADD CONSTRAINT FK_720EF51B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_720EF51BB03A8386 ON travel_stop (created_by_id)');
        $this->addSql('CREATE INDEX IDX_720EF51B896DBBDE ON travel_stop (updated_by_id)');
        $this->addSql('ALTER TABLE travel_expense ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE travel_expense ADD CONSTRAINT FK_EC793AB7B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE travel_expense ADD CONSTRAINT FK_EC793AB7896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_EC793AB7B03A8386 ON travel_expense (created_by_id)');
        $this->addSql('CREATE INDEX IDX_EC793AB7896DBBDE ON travel_expense (updated_by_id)');
        $this->addSql('ALTER TABLE travel_expense_bundle ADD created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\' DEFAULT \''.$this->dbMigratorId.'\', ADD updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD created_on DATETIME NOT NULL DEFAULT \'1970-01-01 12:00:00\', ADD updated_on DATETIME');
        $this->addSql('ALTER TABLE travel_expense_bundle ADD CONSTRAINT FK_7410FCA5B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE travel_expense_bundle ADD CONSTRAINT FK_7410FCA5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('CREATE INDEX IDX_7410FCA5B03A8386 ON travel_expense_bundle (created_by_id)');
        $this->addSql('CREATE INDEX IDX_7410FCA5896DBBDE ON travel_expense_bundle (updated_by_id)');
        $this->addSql('ALTER TABLE app_users CHANGE mobile mobile VARCHAR(20) NULL, CHANGE phone phone VARCHAR(20) NULL ');
        $this->addSql('UPDATE invoice SET created_by_id = issued_by_id; UPDATE invoice SET created_on = date_of_issue; ALTER TABLE invoice DROP FOREIGN KEY FK_90651744784BB717; ALTER TABLE invoice DROP COLUMN issued_by_id;');
    }
    
    public function postUp(Schema $schema) : void
    {
    	//Get EntityManager
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	
    	//Drop the default values so we don't have problems later...
    	$sql = "ALTER TABLE country ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE post ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE address ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE transaction ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE user_settings ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE organization_settings ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE invoice ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE invoice_item ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE app_users ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE organization ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE client ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE konto_category ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE konto ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE konto_class ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE travel_stop ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE travel_expense ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$sql = "ALTER TABLE travel_expense_bundle ALTER created_by_id DROP DEFAULT, ALTER created_on DROP DEFAULT;";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();    	
    	
    	$em->flush();   	
    	
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE invoice ADD issued_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\';');
        $this->addSql('UPDATE invoice SET issued_by_id = created_by_id;');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744784BB717 FOREIGN KEY (issued_by_id) REFERENCES app_users(id);');       
        
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81B03A8386');
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81896DBBDE');
        $this->addSql('DROP INDEX IDX_D4E6F81B03A8386 ON address');
        $this->addSql('DROP INDEX IDX_D4E6F81896DBBDE ON address');
        $this->addSql('ALTER TABLE address DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE app_users DROP FOREIGN KEY FK_C2502824B03A8386');
        $this->addSql('ALTER TABLE app_users DROP FOREIGN KEY FK_C2502824896DBBDE');
        $this->addSql('DROP INDEX IDX_C2502824B03A8386 ON app_users');
        $this->addSql('DROP INDEX IDX_C2502824896DBBDE ON app_users');
        $this->addSql('ALTER TABLE app_users DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455B03A8386');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455896DBBDE');
        $this->addSql('DROP INDEX IDX_C7440455B03A8386 ON client');
        $this->addSql('DROP INDEX IDX_C7440455896DBBDE ON client');
        $this->addSql('ALTER TABLE client DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE country DROP FOREIGN KEY FK_5373C966B03A8386');
        $this->addSql('ALTER TABLE country DROP FOREIGN KEY FK_5373C966896DBBDE');
        $this->addSql('DROP INDEX IDX_5373C966B03A8386 ON country');
        $this->addSql('DROP INDEX IDX_5373C966896DBBDE ON country');
        $this->addSql('ALTER TABLE country DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B03A8386');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744896DBBDE');
        $this->addSql('DROP INDEX IDX_90651744B03A8386 ON invoice');
        $this->addSql('DROP INDEX IDX_90651744896DBBDE ON invoice');
        $this->addSql('ALTER TABLE invoice DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477BB03A8386');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477B896DBBDE');
        $this->addSql('DROP INDEX IDX_1DDE477BB03A8386 ON invoice_item');
        $this->addSql('DROP INDEX IDX_1DDE477B896DBBDE ON invoice_item');
        $this->addSql('ALTER TABLE invoice_item DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE konto DROP FOREIGN KEY FK_9F927005B03A8386');
        $this->addSql('ALTER TABLE konto DROP FOREIGN KEY FK_9F927005896DBBDE');
        $this->addSql('DROP INDEX IDX_9F927005B03A8386 ON konto');
        $this->addSql('DROP INDEX IDX_9F927005896DBBDE ON konto');
        $this->addSql('ALTER TABLE konto DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE konto_category DROP FOREIGN KEY FK_5A2C4BFDB03A8386');
        $this->addSql('ALTER TABLE konto_category DROP FOREIGN KEY FK_5A2C4BFD896DBBDE');
        $this->addSql('DROP INDEX IDX_5A2C4BFDB03A8386 ON konto_category');
        $this->addSql('DROP INDEX IDX_5A2C4BFD896DBBDE ON konto_category');
        $this->addSql('ALTER TABLE konto_category DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE konto_class DROP FOREIGN KEY FK_2F6A529DB03A8386');
        $this->addSql('ALTER TABLE konto_class DROP FOREIGN KEY FK_2F6A529D896DBBDE');
        $this->addSql('DROP INDEX IDX_2F6A529DB03A8386 ON konto_class');
        $this->addSql('DROP INDEX IDX_2F6A529D896DBBDE ON konto_class');
        $this->addSql('ALTER TABLE konto_class DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CB03A8386');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C896DBBDE');
        $this->addSql('DROP INDEX IDX_C1EE637CB03A8386 ON organization');
        $this->addSql('DROP INDEX IDX_C1EE637C896DBBDE ON organization');
        $this->addSql('ALTER TABLE organization DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267B03A8386');
        $this->addSql('ALTER TABLE organization_settings DROP FOREIGN KEY FK_A5D6267896DBBDE');
        $this->addSql('DROP INDEX IDX_A5D6267B03A8386 ON organization_settings');
        $this->addSql('DROP INDEX IDX_A5D6267896DBBDE ON organization_settings');
        $this->addSql('ALTER TABLE organization_settings DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DB03A8386');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D896DBBDE');
        $this->addSql('DROP INDEX IDX_5A8A6C8DB03A8386 ON post');
        $this->addSql('DROP INDEX IDX_5A8A6C8D896DBBDE ON post');
        $this->addSql('ALTER TABLE post DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1B03A8386');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1896DBBDE');
        $this->addSql('DROP INDEX IDX_723705D1B03A8386 ON transaction');
        $this->addSql('DROP INDEX IDX_723705D1896DBBDE ON transaction');
        $this->addSql('ALTER TABLE transaction DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE travel_expense DROP FOREIGN KEY FK_EC793AB7B03A8386');
        $this->addSql('ALTER TABLE travel_expense DROP FOREIGN KEY FK_EC793AB7896DBBDE');
        $this->addSql('DROP INDEX IDX_EC793AB7B03A8386 ON travel_expense');
        $this->addSql('DROP INDEX IDX_EC793AB7896DBBDE ON travel_expense');
        $this->addSql('ALTER TABLE travel_expense DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE travel_expense_bundle DROP FOREIGN KEY FK_7410FCA5B03A8386');
        $this->addSql('ALTER TABLE travel_expense_bundle DROP FOREIGN KEY FK_7410FCA5896DBBDE');
        $this->addSql('DROP INDEX IDX_7410FCA5B03A8386 ON travel_expense_bundle');
        $this->addSql('DROP INDEX IDX_7410FCA5896DBBDE ON travel_expense_bundle');
        $this->addSql('ALTER TABLE travel_expense_bundle DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE travel_stop DROP FOREIGN KEY FK_720EF51BB03A8386');
        $this->addSql('ALTER TABLE travel_stop DROP FOREIGN KEY FK_720EF51B896DBBDE');
        $this->addSql('DROP INDEX IDX_720EF51BB03A8386 ON travel_stop');
        $this->addSql('DROP INDEX IDX_720EF51B896DBBDE ON travel_stop');
        $this->addSql('ALTER TABLE travel_stop DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');
        $this->addSql('ALTER TABLE user_settings DROP FOREIGN KEY FK_5C844C5B03A8386');
        $this->addSql('ALTER TABLE user_settings DROP FOREIGN KEY FK_5C844C5896DBBDE');
        $this->addSql('DROP INDEX IDX_5C844C5B03A8386 ON user_settings');
        $this->addSql('DROP INDEX IDX_5C844C5896DBBDE ON user_settings');
        $this->addSql('ALTER TABLE user_settings DROP created_by_id, DROP updated_by_id, DROP created_on, DROP updated_on');       
        
    }
}
