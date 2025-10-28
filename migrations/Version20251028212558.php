<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028212558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D4DD1BA2E');
        $this->addSql('DROP INDEX IDX_508B7C7D4DD1BA2E ON lieu_jour');
        $this->addSql('ALTER TABLE lieu_jour CHANGE joursemaine_id jour_semaine_id INT DEFAULT NULL, CHANGE nbterrain nb_terrain INT NOT NULL, CHANGE heureouverture heure_ouverture TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\'');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D5DE37D35 FOREIGN KEY (jour_semaine_id) REFERENCES joursemaine (id)');
        $this->addSql('CREATE INDEX IDX_508B7C7D5DE37D35 ON lieu_jour (jour_semaine_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D5DE37D35');
        $this->addSql('DROP INDEX IDX_508B7C7D5DE37D35 ON lieu_jour');
        $this->addSql('ALTER TABLE lieu_jour CHANGE jour_semaine_id joursemaine_id INT DEFAULT NULL, CHANGE nb_terrain nbterrain INT NOT NULL, CHANGE heure_ouverture heureouverture TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\'');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D4DD1BA2E FOREIGN KEY (joursemaine_id) REFERENCES joursemaine (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_508B7C7D4DD1BA2E ON lieu_jour (joursemaine_id)');
    }
}
