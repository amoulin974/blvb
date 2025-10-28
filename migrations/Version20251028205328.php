<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028205328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lieu_jour (id INT AUTO_INCREMENT NOT NULL, lieu_id INT DEFAULT NULL, joursemaine_id INT DEFAULT NULL, nbterrain INT NOT NULL, heureouverture TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\', INDEX IDX_508B7C7D6AB213CC (lieu_id), INDEX IDX_508B7C7D4DD1BA2E (joursemaine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE lieu_jour ADD CONSTRAINT FK_508B7C7D4DD1BA2E FOREIGN KEY (joursemaine_id) REFERENCES joursemaine (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D6AB213CC');
        $this->addSql('ALTER TABLE lieu_jour DROP FOREIGN KEY FK_508B7C7D4DD1BA2E');
        $this->addSql('DROP TABLE lieu_jour');
    }
}
