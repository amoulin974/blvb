<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104222814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poule ADD niveau INT DEFAULT NULL, CHANGE nb_montee_defaut nb_montee_defaut INT DEFAULT NULL, CHANGE nb_descente_defaut nb_descente_defaut INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poule DROP niveau, CHANGE nb_montee_defaut nb_montee_defaut INT NOT NULL, CHANGE nb_descente_defaut nb_descente_defaut INT NOT NULL');
    }
}
