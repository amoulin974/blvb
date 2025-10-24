<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020062333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe ADD id_lieu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA15B42FBABC FOREIGN KEY (id_lieu_id) REFERENCES lieu (id)');
        $this->addSql('CREATE INDEX IDX_2449BA15B42FBABC ON equipe (id_lieu_id)');
        $this->addSql('ALTER TABLE journee ADD id_phase_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE journee ADD CONSTRAINT FK_DC179AEDC9D2FD1D FOREIGN KEY (id_phase_id) REFERENCES phase (id)');
        $this->addSql('CREATE INDEX IDX_DC179AEDC9D2FD1D ON journee (id_phase_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA15B42FBABC');
        $this->addSql('DROP INDEX IDX_2449BA15B42FBABC ON equipe');
        $this->addSql('ALTER TABLE equipe DROP id_lieu_id');
        $this->addSql('ALTER TABLE journee DROP FOREIGN KEY FK_DC179AEDC9D2FD1D');
        $this->addSql('DROP INDEX IDX_DC179AEDC9D2FD1D ON journee');
        $this->addSql('ALTER TABLE journee DROP id_phase_id');
    }
}
