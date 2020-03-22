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
final class Version20200310204658 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	private $dbMigratorId;

    public function getDescription() : string
    {
        return 'Adding redundant issuer & recepient data to invoice in case of changes';
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

        $this->addSql('ALTER TABLE invoice ADD issuer_name VARCHAR(255) NOT NULL, ADD issuer_address VARCHAR(255) NOT NULL, ADD issuer_post_name VARCHAR(100) NOT NULL, ADD issuer_tax_number VARCHAR(255) NOT NULL, ADD issuer_account_number VARCHAR(255) NOT NULL, ADD issuer_bic VARCHAR(10) NOT NULL, ADD recepient_name VARCHAR(255) NOT NULL, ADD recepient_address VARCHAR(255) NOT NULL, ADD recepient_tax_number VARCHAR(255) NOT NULL');
    }
    
    public function postUp(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$sql = "SELECT i.id, o.name AS issuerName, oa.line1 AS issuerLine1, oa.line2 AS issuerLine2, oap.code AS issuerPostCode, oap.name AS issuerPostName, oapc.name AS issuerCountryName, oapc.a2 AS issuerCountryCode, o.taxable AS issuerTaxable, o.tax_number AS issuerTaxNumber, o.account_number AS issuerAccountNumber, o.bic AS issuerBic, ";
    	$sql .= "p.name AS recepientName, pa.line1 AS recepientLine1, pa.line2 AS recepientLine2, pap.code AS recepientPostCode, pap.name AS recepientPostName, papc.name AS recepientCountryName, papc.a2 AS recepientCountryCode, p.taxable AS recepientTaxable, p.tax_number AS recepientTaxNumber ";
    	$sql .= "FROM `invoice` AS i ";
    	$sql .= "LEFT OUTER JOIN organization AS o ON o.id = i.issuer_id LEFT OUTER JOIN address AS oa ON oa.id = o.address_id ";
    	$sql .= "LEFT OUTER JOIN post AS oap ON oap.id = oa.post_id LEFT OUTER JOIN country AS oapc ON oapc.id = oap.country_id ";
    	$sql .= "LEFT OUTER JOIN partner AS p ON p.id = i.recepient_id LEFT OUTER JOIN address AS pa ON pa.id = p.address_id ";
    	$sql .= "LEFT OUTER JOIN post AS pap ON pap.id = pa.post_id LEFT OUTER JOIN country AS papc ON papc.id = pap.country_id ";
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$res = $stmt->fetchAll();
    	$em->flush();
    	if($res != null)
    	{
    		foreach($res as $i)
    		{
    			$issuerName = $i["issuerName"];
    			
    			$issuerAddress = $i["issuerLine1"] . ", ";
    			$issuerAddress .= $i["issuerLine2"] == "" ? "" : $i["issuerLine2"] . ", ";
    			$issuerAddress .= $i["issuerPostCode"] . " " . $i["issuerPostName"] . ", " . $i["issuerCountryName"];
    			
    			$issuerTaxNumber = $i["issuerTaxable"] == 1 ? $i["issuerCountryCode"] : "";
    			$issuerTaxNumber .= $i["issuerTaxNumber"];
    			
    			$issuerAccountNumber = $i["issuerAccountNumber"];
    			
    			$issuerBic = $i["issuerBic"];
    			
    			$recepientName = $i["recepientName"];
    			
    			$recepientAddress = $i["recepientLine1"] . ", ";
    			$recepientAddress .= $i["recepientLine2"] == "" ? "" : $i["recepientLine2"] . ", ";
    			$recepientAddress .= $i["recepientPostCode"] . " " . $i["recepientPostName"] . ", " . $i["recepientCountryName"];
    			
    			$recepientTaxNumber = $i["recepientTaxable"] == 1 ? $i["recepientCountryCode"] : "";
    			$recepientTaxNumber .= $i["recepientTaxNumber"];
    			
    			$sql = 'UPDATE invoice SET issuer_name = "'.$issuerName.'", issuer_address = "'.$issuerAddress.'", issuer_post_name = "'.$i["issuerPostName"].'", issuer_tax_number = "'.$issuerTaxNumber.'", issuer_account_number = "'.$issuerAccountNumber.'", issuer_bic = "'.$issuerBic.'"';
    			$sql .= ', recepient_name = "'.$recepientName.'", recepient_address = "'.$recepientAddress.'", recepient_tax_number = "'.$recepientTaxNumber.'" WHERE id = "'.$i["id"].'"';
    			$stmt = $em->getConnection()->prepare($sql);
    			$stmt->execute();
    			$em->flush();
    		}
    	}
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE invoice DROP issuer_name, DROP issuer_address, DROP issuer_post_name, DROP issuer_tax_number, DROP issuer_account_number, DROP issuer_bic, DROP recepient_name, DROP recepient_address, DROP recepient_tax_number');
    }
}
