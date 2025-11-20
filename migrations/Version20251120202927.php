<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120202927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau ADD prioritaire INT NOT NULL');
        $this->addSql('ALTER TABLE lieu DROP jour, DROP heure, DROP nb_terrains');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lieu ADD jour VARCHAR(255) NOT NULL, ADD heure TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', ADD nb_terrains INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creneau DROP prioritaire');
    }
}
