<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119221200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE creneau (id INT AUTO_INCREMENT NOT NULL, lieu_id INT NOT NULL, jour_semaine INT NOT NULL, heure_debut TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', heure_fin TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', capacite INT NOT NULL, INDEX IDX_F9668B5F6AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE indisponibilite (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8717036FF965414C (saison_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE creneau ADD CONSTRAINT FK_F9668B5F6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE indisponibilite ADD CONSTRAINT FK_8717036FF965414C FOREIGN KEY (saison_id) REFERENCES saison (id)');
        $this->addSql('ALTER TABLE poule ADD nb_montee_defaut INT NOT NULL, ADD nb_descente_defaut INT NOT NULL');
        $this->addSql('ALTER TABLE saison ADD points_victoire_forte INT DEFAULT 3 NOT NULL, ADD points_defaite_forte INT DEFAULT 0 NOT NULL, ADD points_nul INT DEFAULT 0 NOT NULL, ADD points_forfait INT DEFAULT -3 NOT NULL, ADD points_victoire_faible INT DEFAULT 1 NOT NULL, ADD points_defaite_faible INT DEFAULT 2 NOT NULL');
        $this->addSql('
    INSERT INTO creneau (lieu_id, jour_semaine, heure_debut, heure_fin, capacite)
    SELECT
        id,
        CASE
            WHEN jour = "Lundi" THEN 1
            WHEN jour = "Mardi" THEN 2
            WHEN jour = "Mercredi" THEN 3
            WHEN jour = "Jeudi" THEN 4
            WHEN jour = "Vendredi" THEN 5
            WHEN jour = "Samedi" THEN 6
            WHEN jour = "Dimanche" THEN 7
            ELSE 6
        END,
        heure,
        ADDTIME(heure, "02:00:00"),
        IFNULL(nb_terrains, 1)
    FROM lieu
    WHERE heure IS NOT NULL
');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau DROP FOREIGN KEY FK_F9668B5F6AB213CC');
        $this->addSql('ALTER TABLE indisponibilite DROP FOREIGN KEY FK_8717036FF965414C');
        $this->addSql('DROP TABLE creneau');
        $this->addSql('DROP TABLE indisponibilite');
        $this->addSql('ALTER TABLE poule DROP nb_montee_defaut, DROP nb_descente_defaut');
        $this->addSql('ALTER TABLE saison DROP points_victoire_forte, DROP points_defaite_forte, DROP points_nul, DROP points_forfait, DROP points_victoire_faible, DROP points_defaite_faible');
    }
}
