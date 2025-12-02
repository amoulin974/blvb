<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122093546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA15B42FBABC');
        $this->addSql('DROP INDEX IDX_2449BA15B42FBABC ON equipe');
        $this->addSql('ALTER TABLE equipe CHANGE id_lieu_id lieu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA156AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('CREATE INDEX IDX_2449BA156AB213CC ON equipe (lieu_id)');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DB42FBABC');
        $this->addSql('DROP INDEX IDX_59B1F3DB42FBABC ON partie');
        $this->addSql('ALTER TABLE partie CHANGE id_lieu_id lieu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('CREATE INDEX IDX_59B1F3D6AB213CC ON partie (lieu_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA156AB213CC');
        $this->addSql('DROP INDEX IDX_2449BA156AB213CC ON equipe');
        $this->addSql('ALTER TABLE equipe CHANGE lieu_id id_lieu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA15B42FBABC FOREIGN KEY (id_lieu_id) REFERENCES lieu (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_2449BA15B42FBABC ON equipe (id_lieu_id)');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D6AB213CC');
        $this->addSql('DROP INDEX IDX_59B1F3D6AB213CC ON partie');
        $this->addSql('ALTER TABLE partie CHANGE lieu_id id_lieu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DB42FBABC FOREIGN KEY (id_lieu_id) REFERENCES lieu (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_59B1F3DB42FBABC ON partie (id_lieu_id)');
    }
}
