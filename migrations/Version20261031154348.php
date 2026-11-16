<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031154348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Volley Stats (D67) : page du match d\'une équipe, joueurs présents, sets et journal des événements';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE feuille_match (
              id INT AUTO_INCREMENT NOT NULL,
              partie_id INT NOT NULL,
              equipe_id INT NOT NULL,
              mode VARCHAR(20) NOT NULL,
              etat VARCHAR(20) NOT NULL,
              cree_le DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              modifie_le DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              terminee_le DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              UNIQUE INDEX feuille_match_unique (partie_id, equipe_id),
              INDEX IDX_1628A52EE075F7A4 (partie_id),
              INDEX IDX_1628A52E6D861B89 (equipe_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feuille_match_evenement (
              id INT AUTO_INCREMENT NOT NULL,
              set_id INT NOT NULL,
              saisi_par_id INT DEFAULT NULL,
              ordre INT NOT NULL,
              uuid VARCHAR(36) NOT NULL,
              type VARCHAR(20) NOT NULL,
              donnees JSON NOT NULL,
              saisi_le DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              annule_le DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              UNIQUE INDEX feuille_match_evenement_ordre (set_id, ordre),
              UNIQUE INDEX feuille_match_evenement_uuid (uuid),
              INDEX IDX_9780EDD410FB0D18 (set_id),
              INDEX IDX_9780EDD4B0F809FE (saisi_par_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feuille_match_joueur (
              id INT AUTO_INCREMENT NOT NULL,
              feuille_id INT NOT NULL,
              membre_id INT NOT NULL,
              UNIQUE INDEX feuille_match_joueur_unique (feuille_id, membre_id),
              INDEX IDX_56D2E05C65150016 (feuille_id),
              INDEX IDX_56D2E05C6A99F74A (membre_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feuille_match_set (
              id INT AUTO_INCREMENT NOT NULL,
              feuille_id INT NOT NULL,
              numero SMALLINT NOT NULL,
              postes JSON NOT NULL,
              premier_service VARCHAR(4) DEFAULT NULL,
              score_depart JSON NOT NULL,
              points_fin SMALLINT NOT NULL,
              etat VARCHAR(12) NOT NULL,
              UNIQUE INDEX feuille_match_set_unique (feuille_id, numero),
              INDEX IDX_9DA8605B65150016 (feuille_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match
            ADD
              CONSTRAINT FK_1628A52EE075F7A4 FOREIGN KEY (partie_id) REFERENCES partie (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match
            ADD
              CONSTRAINT FK_1628A52E6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match_evenement
            ADD
              CONSTRAINT FK_9780EDD410FB0D18 FOREIGN KEY (set_id) REFERENCES feuille_match_set (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match_evenement
            ADD
              CONSTRAINT FK_9780EDD4B0F809FE FOREIGN KEY (saisi_par_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match_joueur
            ADD
              CONSTRAINT FK_56D2E05C65150016 FOREIGN KEY (feuille_id) REFERENCES feuille_match (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match_joueur
            ADD
              CONSTRAINT FK_56D2E05C6A99F74A FOREIGN KEY (membre_id) REFERENCES membre_equipe (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              feuille_match_set
            ADD
              CONSTRAINT FK_9DA8605B65150016 FOREIGN KEY (feuille_id) REFERENCES feuille_match (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuille_match DROP FOREIGN KEY FK_1628A52EE075F7A4');
        $this->addSql('ALTER TABLE feuille_match DROP FOREIGN KEY FK_1628A52E6D861B89');
        $this->addSql('ALTER TABLE feuille_match_evenement DROP FOREIGN KEY FK_9780EDD410FB0D18');
        $this->addSql('ALTER TABLE feuille_match_evenement DROP FOREIGN KEY FK_9780EDD4B0F809FE');
        $this->addSql('ALTER TABLE feuille_match_joueur DROP FOREIGN KEY FK_56D2E05C65150016');
        $this->addSql('ALTER TABLE feuille_match_joueur DROP FOREIGN KEY FK_56D2E05C6A99F74A');
        $this->addSql('ALTER TABLE feuille_match_set DROP FOREIGN KEY FK_9DA8605B65150016');
        $this->addSql('DROP TABLE feuille_match');
        $this->addSql('DROP TABLE feuille_match_evenement');
        $this->addSql('DROP TABLE feuille_match_joueur');
        $this->addSql('DROP TABLE feuille_match_set');
    }
}
