<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028222113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D5DE37D35');
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D6AB213CC');
        $this->addSql('DROP TABLE joursemaine');
        $this->addSql('DROP TABLE lieu_jour');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE joursemaine (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lieu_jour (id INT AUTO_INCREMENT NOT NULL, lieu_id INT DEFAULT NULL, jour_semaine_id INT DEFAULT NULL, nb_terrain INT NOT NULL, heure_ouverture TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', INDEX IDX_508B7C7D5DE37D35 (jour_semaine_id), INDEX IDX_508B7C7D6AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D5DE37D35 FOREIGN KEY (jour_semaine_id) REFERENCES joursemaine (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
