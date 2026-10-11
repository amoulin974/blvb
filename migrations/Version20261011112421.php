<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011112421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clubs : table club, gymnases du club (club_lieu), responsables (club_responsable), club de chaque équipe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE club (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_B8EE38726C6E55B5 (nom), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE club_lieu (club_id INT NOT NULL, lieu_id INT NOT NULL, INDEX IDX_4B9FB7B961190A32 (club_id), INDEX IDX_4B9FB7B96AB213CC (lieu_id), PRIMARY KEY(club_id, lieu_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE club_responsable (club_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8CA0558C61190A32 (club_id), INDEX IDX_8CA0558CA76ED395 (user_id), PRIMARY KEY(club_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE club_lieu ADD CONSTRAINT FK_4B9FB7B961190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_lieu ADD CONSTRAINT FK_4B9FB7B96AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_responsable ADD CONSTRAINT FK_8CA0558C61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_responsable ADD CONSTRAINT FK_8CA0558CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe ADD club_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA1561190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2449BA1561190A32 ON equipe (club_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA1561190A32');
        $this->addSql('ALTER TABLE club_lieu DROP FOREIGN KEY FK_4B9FB7B961190A32');
        $this->addSql('ALTER TABLE club_lieu DROP FOREIGN KEY FK_4B9FB7B96AB213CC');
        $this->addSql('ALTER TABLE club_responsable DROP FOREIGN KEY FK_8CA0558C61190A32');
        $this->addSql('ALTER TABLE club_responsable DROP FOREIGN KEY FK_8CA0558CA76ED395');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP TABLE club_lieu');
        $this->addSql('DROP TABLE club_responsable');
        $this->addSql('DROP INDEX IDX_2449BA1561190A32 ON equipe');
        $this->addSql('ALTER TABLE equipe DROP club_id');
    }
}
