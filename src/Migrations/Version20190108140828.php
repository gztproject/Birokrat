<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190108140828 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE invoice_state (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE konto_category (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', class_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', number INT NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_5A2C4BFDEA000B10 (class_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE country (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, name_int INT NOT NULL, a2 VARCHAR(2) NOT NULL, a3 VARCHAR(3) NOT NULL, n3 INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE organization (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, short_name VARCHAR(100) DEFAULT NULL, tax_number INT NOT NULL, taxable TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE organization_user (organization_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY(organization_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', issuer_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', recepient_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', state_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', date_of_issue DATETIME NOT NULL, number VARCHAR(255) NOT NULL, discount NUMERIC(5, 2) DEFAULT NULL, total_value NUMERIC(15, 2) NOT NULL, total_price NUMERIC(15, 2) NOT NULL, reference_number VARCHAR(50) NOT NULL, INDEX IDX_90651744BB9D6FEE (issuer_id), INDEX IDX_90651744F1B7C6C (recepient_id), INDEX IDX_906517445D83CC1 (state_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice_item (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', invoice_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(5) NOT NULL, value NUMERIC(10, 2) NOT NULL, discount NUMERIC(5, 2) DEFAULT NULL, INDEX IDX_1DDE477B2989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE konto (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', konto_category_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', class INT NOT NULL, number INT NOT NULL, name VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_9F927005FA08DFC4 (konto_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', code VARCHAR(10) NOT NULL, code_international VARCHAR(15) NOT NULL, name VARCHAR(100) NOT NULL, INDEX IDX_5A8A6C8DF92F3E70 (country_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE address (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', post_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', organization_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', line1 VARCHAR(255) NOT NULL, line2 VARCHAR(255) DEFAULT NULL, INDEX IDX_D4E6F814B89032C (post_id), INDEX IDX_D4E6F8132C8A3DE (organization_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE konto_class (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', number INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE konto_category ADD CONSTRAINT FK_5A2C4BFDEA000B10 FOREIGN KEY (class_id) REFERENCES konto_class (id)');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES app_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744BB9D6FEE FOREIGN KEY (issuer_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744F1B7C6C FOREIGN KEY (recepient_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517445D83CC1 FOREIGN KEY (state_id) REFERENCES invoice_state (id)');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE konto ADD CONSTRAINT FK_9F927005FA08DFC4 FOREIGN KEY (konto_category_id) REFERENCES konto_category (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F814B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F8132C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517445D83CC1');
        $this->addSql('ALTER TABLE konto DROP FOREIGN KEY FK_9F927005FA08DFC4');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DF92F3E70');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744BB9D6FEE');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744F1B7C6C');
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F8132C8A3DE');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477B2989F1FD');
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F814B89032C');
        $this->addSql('ALTER TABLE konto_category DROP FOREIGN KEY FK_5A2C4BFDEA000B10');
        $this->addSql('DROP TABLE invoice_state');
        $this->addSql('DROP TABLE konto_category');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_item');
        $this->addSql('DROP TABLE konto');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE konto_class');
    }
}
