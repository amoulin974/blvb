<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wizard de création de saison : point de départ du brouillon (saison.depart_libelle) et date du dernier enregistrement (saison.modifie_le)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison ADD depart_libelle VARCHAR(255) DEFAULT NULL, ADD modifie_le DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison DROP depart_libelle, DROP modifie_le');
    }
}
