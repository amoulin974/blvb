<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031173937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Volley Stats (D68) : mode de saisie de chaque set (le score seulement, ou le score et les touches)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuille_match_set ADD saisie VARCHAR(8) DEFAULT \'score\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuille_match_set DROP saisie');
    }
}
