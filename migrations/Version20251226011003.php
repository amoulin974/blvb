<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226011003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partie ADD parent_match1_id INT DEFAULT NULL, ADD parent_match2_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D48BDCB30 FOREIGN KEY (parent_match1_id) REFERENCES partie (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D5A0864DE FOREIGN KEY (parent_match2_id) REFERENCES partie (id)');
        $this->addSql('CREATE INDEX IDX_59B1F3D48BDCB30 ON partie (parent_match1_id)');
        $this->addSql('CREATE INDEX IDX_59B1F3D5A0864DE ON partie (parent_match2_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D48BDCB30');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D5A0864DE');
        $this->addSql('DROP INDEX IDX_59B1F3D48BDCB30 ON partie');
        $this->addSql('DROP INDEX IDX_59B1F3D5A0864DE ON partie');
        $this->addSql('ALTER TABLE partie DROP parent_match1_id, DROP parent_match2_id');
    }
}
