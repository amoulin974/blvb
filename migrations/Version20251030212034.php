<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030212034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe ADD capitaine_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA152A10D79E FOREIGN KEY (capitaine_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2449BA152A10D79E ON equipe (capitaine_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA152A10D79E');
        $this->addSql('DROP INDEX IDX_2449BA152A10D79E ON equipe');
        $this->addSql('ALTER TABLE equipe DROP capitaine_id');
    }
}
