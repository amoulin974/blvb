<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Competition\CodesCompetition;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Codes des phases (P1, P2, F…) et des poules (A, B…) pour les règles de passage, calculés pour les saisons existantes (App\Competition\CodesCompetition)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phase ADD code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE poule ADD code VARCHAR(20) DEFAULT NULL');

        // Même calcul que l'application : saison par saison
        $poulesParPhase = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, phase_id, nom, niveau FROM poule') as $p) {
            $poulesParPhase[$p['phase_id']][] = ['cle' => (int) $p['id'], 'code' => null, 'nom' => $p['nom'], 'niveau' => $p['niveau'] === null ? null : (int) $p['niveau']];
        }
        $phasesParSaison = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, saison_id, type, ordre FROM phase') as $ph) {
            $phasesParSaison[$ph['saison_id']][] = ['cle' => (int) $ph['id'], 'code' => null, 'finale' => (int) $ph['type'] === 1,
                'ordre' => $ph['ordre'] === null ? null : (int) $ph['ordre'], 'poules' => $poulesParPhase[$ph['id']] ?? []];
        }
        foreach ($phasesParSaison as $phases) {
            foreach (CodesCompetition::calculer($phases) as $phase) {
                $this->addSql('UPDATE phase SET code = ? WHERE id = ?', [$phase['code'], $phase['cle']]);
                foreach ($phase['poules'] as $poule) {
                    $this->addSql('UPDATE poule SET code = ? WHERE id = ?', [$poule['code'], $poule['cle']]);
                }
            }
        }

        $this->addSql('CREATE UNIQUE INDEX phase_code_unique ON phase (saison_id, code)');
        $this->addSql('CREATE UNIQUE INDEX poule_code_unique ON poule (phase_id, code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX phase_code_unique ON phase');
        $this->addSql('DROP INDEX poule_code_unique ON poule');
        $this->addSql('ALTER TABLE phase DROP code');
        $this->addSql('ALTER TABLE poule DROP code');
    }
}
