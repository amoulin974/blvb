<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wizard de création de saison : saison en brouillon (saison.brouillon, saison.etape_wizard) et nombre d\'équipes prévu par poule (poule.nb_equipes_prevu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison ADD brouillon TINYINT(1) DEFAULT 0 NOT NULL, ADD etape_wizard INT DEFAULT NULL');
        $this->addSql('ALTER TABLE poule ADD nb_equipes_prevu INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison DROP brouillon, DROP etape_wizard');
        $this->addSql('ALTER TABLE poule DROP nb_equipes_prevu');
    }
}
