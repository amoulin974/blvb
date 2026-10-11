<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011143228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Responsables de club désignés par saison : table responsable_club (club, compte, saison) à la place de club_responsable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE responsable_club (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, user_id INT NOT NULL, saison_id INT NOT NULL, INDEX IDX_423489AC61190A32 (club_id), INDEX IDX_423489ACA76ED395 (user_id), INDEX IDX_423489ACF965414C (saison_id), UNIQUE INDEX responsable_club_unique (club_id, user_id, saison_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE responsable_club ADD CONSTRAINT FK_423489AC61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE responsable_club ADD CONSTRAINT FK_423489ACA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE responsable_club ADD CONSTRAINT FK_423489ACF965414C FOREIGN KEY (saison_id) REFERENCES saison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_responsable DROP FOREIGN KEY FK_8CA0558CA76ED395');
        $this->addSql('ALTER TABLE club_responsable DROP FOREIGN KEY FK_8CA0558C61190A32');
        $this->addSql('DROP TABLE club_responsable');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE club_responsable (club_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8CA0558C61190A32 (club_id), INDEX IDX_8CA0558CA76ED395 (user_id), PRIMARY KEY(club_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE club_responsable ADD CONSTRAINT FK_8CA0558CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_responsable ADD CONSTRAINT FK_8CA0558C61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE responsable_club DROP FOREIGN KEY FK_423489AC61190A32');
        $this->addSql('ALTER TABLE responsable_club DROP FOREIGN KEY FK_423489ACA76ED395');
        $this->addSql('ALTER TABLE responsable_club DROP FOREIGN KEY FK_423489ACF965414C');
        $this->addSql('DROP TABLE responsable_club');
    }
}
