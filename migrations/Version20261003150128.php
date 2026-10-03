<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003150128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE joueur (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_FD71A9C5A76ED395 (user_id), INDEX IDX_FD71A9C56C6E55B5A625945B (nom, prenom), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membre_equipe (id INT AUTO_INCREMENT NOT NULL, equipe_id INT NOT NULL, saison_id INT NOT NULL, joueur_id INT NOT NULL, capitaine TINYINT(1) NOT NULL, INDEX IDX_BE402FAC6D861B89 (equipe_id), INDEX IDX_BE402FACF965414C (saison_id), INDEX IDX_BE402FACA9E2D76C (joueur_id), UNIQUE INDEX membre_equipe_unique (equipe_id, saison_id, joueur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE joueur ADD CONSTRAINT FK_FD71A9C5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE membre_equipe ADD CONSTRAINT FK_BE402FAC6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre_equipe ADD CONSTRAINT FK_BE402FACF965414C FOREIGN KEY (saison_id) REFERENCES saison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre_equipe ADD CONSTRAINT FK_BE402FACA9E2D76C FOREIGN KEY (joueur_id) REFERENCES joueur (id)');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE joueur DROP FOREIGN KEY FK_FD71A9C5A76ED395');
        $this->addSql('ALTER TABLE membre_equipe DROP FOREIGN KEY FK_BE402FAC6D861B89');
        $this->addSql('ALTER TABLE membre_equipe DROP FOREIGN KEY FK_BE402FACF965414C');
        $this->addSql('ALTER TABLE membre_equipe DROP FOREIGN KEY FK_BE402FACA9E2D76C');
        $this->addSql('DROP TABLE joueur');
        $this->addSql('DROP TABLE membre_equipe');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }
}
