<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103231624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE classement (id INT AUTO_INCREMENT NOT NULL, poule_id INT DEFAULT NULL, equipe_id INT DEFAULT NULL, points INT DEFAULT NULL, set_gagnes INT DEFAULT NULL, set_perdus INT DEFAULT NULL, position INT DEFAULT NULL, INDEX IDX_55EE9D6D26596FD8 (poule_id), INDEX IDX_55EE9D6D6D861B89 (equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE creneau (id INT AUTO_INCREMENT NOT NULL, lieu_id INT NOT NULL, jour_semaine INT NOT NULL, heure_debut TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', heure_fin TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', capacite INT NOT NULL, prioritaire INT NOT NULL, INDEX IDX_F9668B5F6AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipe (id INT AUTO_INCREMENT NOT NULL, lieu_id INT DEFAULT NULL, capitaine_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, INDEX IDX_2449BA156AB213CC (lieu_id), INDEX IDX_2449BA152A10D79E (capitaine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE indisponibilite (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8717036FF965414C (saison_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE journee (id INT AUTO_INCREMENT NOT NULL, poule_id INT NOT NULL, numero INT NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', nom VARCHAR(255) DEFAULT NULL, INDEX IDX_DC179AED26596FD8 (poule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lieu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE partie (id INT AUTO_INCREMENT NOT NULL, journee_id INT NOT NULL, poule_id INT NOT NULL, lieu_id INT DEFAULT NULL, id_equipe_recoit_id INT DEFAULT NULL, id_equipe_deplace_id INT DEFAULT NULL, parent_match1_id INT DEFAULT NULL, parent_match2_id INT DEFAULT NULL, date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', nb_set_gagnant_reception INT DEFAULT NULL, nb_set_gagnant_deplacement INT DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, INDEX IDX_59B1F3DCF066148 (journee_id), INDEX IDX_59B1F3D26596FD8 (poule_id), INDEX IDX_59B1F3D6AB213CC (lieu_id), INDEX IDX_59B1F3DB52FB42A (id_equipe_recoit_id), INDEX IDX_59B1F3D9EF24701 (id_equipe_deplace_id), INDEX IDX_59B1F3D48BDCB30 (parent_match1_id), INDEX IDX_59B1F3D5A0864DE (parent_match2_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE phase (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, nom VARCHAR(255) NOT NULL, datedebut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', datefin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', type INT NOT NULL, ordre INT NOT NULL, INDEX IDX_B1BDD6CBF965414C (saison_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poule (id INT AUTO_INCREMENT NOT NULL, phase_id INT NOT NULL, nom VARCHAR(255) NOT NULL, nb_montee_defaut INT NOT NULL, nb_descente_defaut INT NOT NULL, INDEX IDX_FA1FEB4099091188 (phase_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipe_poule (poule_id INT NOT NULL, equipe_id INT NOT NULL, INDEX IDX_A0137DCA26596FD8 (poule_id), INDEX IDX_A0137DCA6D861B89 (equipe_id), PRIMARY KEY(poule_id, equipe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE saison (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, favori INT DEFAULT 0 NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', points_victoire_forte INT DEFAULT 3 NOT NULL, points_defaite_forte INT DEFAULT 1 NOT NULL, points_nul INT DEFAULT 0 NOT NULL, points_forfait INT DEFAULT -3 NOT NULL, points_victoire_faible INT DEFAULT 2 NOT NULL, points_defaite_faible INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6D26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id)');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6D6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id)');
        $this->addSql('ALTER TABLE creneau ADD CONSTRAINT FK_F9668B5F6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA156AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA152A10D79E FOREIGN KEY (capitaine_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE indisponibilite ADD CONSTRAINT FK_8717036FF965414C FOREIGN KEY (saison_id) REFERENCES saison (id)');
        $this->addSql('ALTER TABLE journee ADD CONSTRAINT FK_DC179AED26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DCF066148 FOREIGN KEY (journee_id) REFERENCES journee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DB52FB42A FOREIGN KEY (id_equipe_recoit_id) REFERENCES equipe (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D9EF24701 FOREIGN KEY (id_equipe_deplace_id) REFERENCES equipe (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D48BDCB30 FOREIGN KEY (parent_match1_id) REFERENCES partie (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D5A0864DE FOREIGN KEY (parent_match2_id) REFERENCES partie (id)');
        $this->addSql('ALTER TABLE phase ADD CONSTRAINT FK_B1BDD6CBF965414C FOREIGN KEY (saison_id) REFERENCES saison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poule ADD CONSTRAINT FK_FA1FEB4099091188 FOREIGN KEY (phase_id) REFERENCES phase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe_poule ADD CONSTRAINT FK_A0137DCA26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe_poule ADD CONSTRAINT FK_A0137DCA6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6D26596FD8');
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6D6D861B89');
        $this->addSql('ALTER TABLE creneau DROP FOREIGN KEY FK_F9668B5F6AB213CC');
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA156AB213CC');
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA152A10D79E');
        $this->addSql('ALTER TABLE indisponibilite DROP FOREIGN KEY FK_8717036FF965414C');
        $this->addSql('ALTER TABLE journee DROP FOREIGN KEY FK_DC179AED26596FD8');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DCF066148');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D26596FD8');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D6AB213CC');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DB52FB42A');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D9EF24701');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D48BDCB30');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D5A0864DE');
        $this->addSql('ALTER TABLE phase DROP FOREIGN KEY FK_B1BDD6CBF965414C');
        $this->addSql('ALTER TABLE poule DROP FOREIGN KEY FK_FA1FEB4099091188');
        $this->addSql('ALTER TABLE equipe_poule DROP FOREIGN KEY FK_A0137DCA26596FD8');
        $this->addSql('ALTER TABLE equipe_poule DROP FOREIGN KEY FK_A0137DCA6D861B89');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE classement');
        $this->addSql('DROP TABLE creneau');
        $this->addSql('DROP TABLE equipe');
        $this->addSql('DROP TABLE indisponibilite');
        $this->addSql('DROP TABLE journee');
        $this->addSql('DROP TABLE lieu');
        $this->addSql('DROP TABLE partie');
        $this->addSql('DROP TABLE phase');
        $this->addSql('DROP TABLE poule');
        $this->addSql('DROP TABLE equipe_poule');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE saison');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
