<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010201556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Points de forfait conformes au règlement : -1 pour l'équipe forfait (le gagnant reçoit les points d'une victoire forte)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison CHANGE points_forfait points_forfait INT DEFAULT -1 NOT NULL');
        // Règlement : « Partie forfait = 3 points pour le gagnant, -1 point pour le perdant ».
        // Les saisons existantes avaient 3 (import des anciennes données) ou -3 (ancienne valeur par défaut).
        // Après cette migration, recalculer les classements : bin/console app:classement:recalculer
        $this->addSql('UPDATE saison SET points_forfait = -1');
    }

    public function down(Schema $schema): void
    {
        // Les anciennes valeurs par saison ne sont pas restaurées (elles étaient contraires au règlement)
        $this->addSql('ALTER TABLE saison CHANGE points_forfait points_forfait INT DEFAULT -3 NOT NULL');
    }
}
