<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020063240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partie (id INT AUTO_INCREMENT NOT NULL, id_journee_id INT DEFAULT NULL, id_poule_id INT DEFAULT NULL, id_lieu_id INT DEFAULT NULL, id_equipe_recoit_id INT DEFAULT NULL, id_equipe_deplace_id INT DEFAULT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', nb_set_gagnant_reception INT NOT NULL, nb_set_gagnant_deplacement INT DEFAULT NULL, INDEX IDX_59B1F3D6A8CE19F (id_journee_id), INDEX IDX_59B1F3D7682834D (id_poule_id), INDEX IDX_59B1F3DB42FBABC (id_lieu_id), INDEX IDX_59B1F3DB52FB42A (id_equipe_recoit_id), INDEX IDX_59B1F3D9EF24701 (id_equipe_deplace_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D6A8CE19F FOREIGN KEY (id_journee_id) REFERENCES journee (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D7682834D FOREIGN KEY (id_poule_id) REFERENCES poule (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DB42FBABC FOREIGN KEY (id_lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DB52FB42A FOREIGN KEY (id_equipe_recoit_id) REFERENCES equipe (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D9EF24701 FOREIGN KEY (id_equipe_deplace_id) REFERENCES equipe (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D6A8CE19F');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D7682834D');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DB42FBABC');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DB52FB42A');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D9EF24701');
        $this->addSql('DROP TABLE partie');
    }
}
