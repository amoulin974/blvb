<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016212334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE poule (id INT AUTO_INCREMENT NOT NULL, phase_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, INDEX IDX_FA1FEB4099091188 (phase_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE poule ADD CONSTRAINT FK_FA1FEB4099091188 FOREIGN KEY (phase_id) REFERENCES phase (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poule DROP FOREIGN KEY FK_FA1FEB4099091188');
        $this->addSql('DROP TABLE poule');
    }
}
