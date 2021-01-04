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
final class Version20210102114103 extends AbstractMigration implements ContainerAwareInterface
{
	use ContainerAwareTrait;
	
    public function getDescription() : string
    {
        return 'Adding a "hidden" field to transaction to hide the konto cleanups and adds the Active/Passive type to Konto.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction ADD hidden TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE konto ADD type SMALLINT(1) NOT NULL ');
    }
    
    public function postUp(Schema $schema) : void
    {
    	$em = $this->container->get('doctrine.orm.entity_manager');
    	$sql = "UPDATE konto k LEFT OUTER JOIN konto_category kca ON k.category_id = kca.id LEFT OUTER JOIN konto_class kcl ON kca.class_id = kcl.id SET k.type = ( CASE WHEN kcl.number = 0 THEN 0 WHEN kcl.number = 1 THEN 0 WHEN kcl.number = 2 THEN 1 WHEN kcl.number = 3 THEN 0 WHEN kcl.number = 4 THEN 0 WHEN kcl.number = 6 THEN 0 WHEN kca.number = 70 THEN 0 WHEN kca.number = 71 THEN 0 WHEN kca.number = 72 THEN 0 WHEN kca.number = 74 THEN 0 WHEN kca.number = 75 THEN 0 WHEN kca.number = 76 THEN 1 WHEN kca.number = 77 THEN 1 WHEN kca.number = 78 THEN 1 WHEN kca.number = 79 THEN 1 WHEN kcl.number = 8 THEN 0 WHEN kcl.number = 9 THEN 1 ELSE 0 END ) ";    	
    	$stmt = $em->getConnection()->prepare($sql);
    	$stmt->execute();
    	$em->flush();    	
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transaction DROP hidden');
        $this->addSql('ALTER TABLE konto DROP type');
    }
}
