<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025193843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journee DROP FOREIGN KEY FK_DC179AEDC9D2FD1D');
        $this->addSql('DROP INDEX IDX_DC179AEDC9D2FD1D ON journee');
        $this->addSql('ALTER TABLE journee CHANGE id_phase_id poule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE journee ADD CONSTRAINT FK_DC179AED26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id)');
        $this->addSql('CREATE INDEX IDX_DC179AED26596FD8 ON journee (poule_id)');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D7682834D');
        $this->addSql('DROP INDEX IDX_59B1F3D7682834D ON partie');
        $this->addSql('ALTER TABLE partie CHANGE id_poule_id poule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id)');
        $this->addSql('CREATE INDEX IDX_59B1F3D26596FD8 ON partie (poule_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journee DROP FOREIGN KEY FK_DC179AED26596FD8');
        $this->addSql('DROP INDEX IDX_DC179AED26596FD8 ON journee');
        $this->addSql('ALTER TABLE journee CHANGE poule_id id_phase_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE journee ADD CONSTRAINT FK_DC179AEDC9D2FD1D FOREIGN KEY (id_phase_id) REFERENCES phase (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_DC179AEDC9D2FD1D ON journee (id_phase_id)');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D26596FD8');
        $this->addSql('DROP INDEX IDX_59B1F3D26596FD8 ON partie');
        $this->addSql('ALTER TABLE partie CHANGE poule_id id_poule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D7682834D FOREIGN KEY (id_poule_id) REFERENCES poule (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_59B1F3D7682834D ON partie (id_poule_id)');
    }
}
