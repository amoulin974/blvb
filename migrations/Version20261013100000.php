<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Classement paramétrable : départage de la saison (saison.departage, départage actuel inscrit dans les saisons existantes) et barème propre à une phase (phase.bareme)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison ADD departage JSON DEFAULT NULL');
        // Départage appliqué jusqu'ici : points, différence de sets, confrontation directe, ordre alphabétique
        $this->addSql('UPDATE saison SET departage = \'["points","difference_sets","confrontation_directe","alphabetique"]\'');
        $this->addSql('ALTER TABLE phase ADD bareme JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison DROP departage');
        $this->addSql('ALTER TABLE phase DROP bareme');
    }
}
