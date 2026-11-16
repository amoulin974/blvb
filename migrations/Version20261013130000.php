<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Competition\ReglesParDefaut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Règles de passage entre phases (table regle_de_passage), générées pour les saisons existantes selon le schéma 2025 (App\Competition\ReglesParDefaut, D21)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE regle_de_passage (id INT AUTO_INCREMENT NOT NULL, phase_id INT NOT NULL, poule_destination_id INT DEFAULT NULL, ordre INT NOT NULL, source VARCHAR(100) NOT NULL, niveau_saison_suivante INT DEFAULT NULL, placement VARCHAR(20) NOT NULL, points_conserves VARCHAR(20) NOT NULL, INDEX IDX_179D836399091188 (phase_id), INDEX IDX_179D8363EAB5690E (poule_destination_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regle_de_passage ADD CONSTRAINT FK_179D836399091188 FOREIGN KEY (phase_id) REFERENCES phase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE regle_de_passage ADD CONSTRAINT FK_179D8363EAB5690E FOREIGN KEY (poule_destination_id) REFERENCES poule (id) ON DELETE CASCADE');

        // Même calcul que l'application, entre phases consécutives de chaque saison (codes attribués par Version20261013120000)
        $nbEquipes = [];
        foreach ($this->connection->fetchAllAssociative('SELECT poule_id, COUNT(*) AS nb FROM equipe_poule GROUP BY poule_id') as $e) {
            $nbEquipes[$e['poule_id']] = (int) $e['nb'];
        }
        $poulesParPhase = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, phase_id, code, niveau, nb_montee_defaut, nb_descente_defaut FROM poule ORDER BY id') as $p) {
            $poulesParPhase[$p['phase_id']][] = ['cle' => (int) $p['id'], 'code' => (string) $p['code'],
                'niveau' => $p['niveau'] === null ? null : (int) $p['niveau'],
                'montee' => $p['nb_montee_defaut'] === null ? null : (int) $p['nb_montee_defaut'],
                'descente' => $p['nb_descente_defaut'] === null ? null : (int) $p['nb_descente_defaut'],
                'nbEquipes' => $nbEquipes[$p['id']] ?? null];
        }
        $phasesParSaison = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, saison_id, code, type FROM phase ORDER BY saison_id, ordre, id') as $ph) {
            $phasesParSaison[$ph['saison_id']][] = ['id' => (int) $ph['id'], 'code' => (string) $ph['code'], 'finale' => (int) $ph['type'] === 1,
                'poules' => $poulesParPhase[$ph['id']] ?? []];
        }
        foreach ($phasesParSaison as $phases) {
            foreach ($phases as $i => $phase) {
                foreach (ReglesParDefaut::calculer($phase, $phases[$i + 1] ?? null) as $r) {
                    $this->addSql(
                        'INSERT INTO regle_de_passage (phase_id, poule_destination_id, ordre, source, niveau_saison_suivante, placement, points_conserves) VALUES (?, ?, ?, ?, ?, \'rang\', \'aucun\')',
                        [$phase['id'], $r['poule'], $r['ordre'], $r['source'], $r['niveauSaisonSuivante']],
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE regle_de_passage DROP FOREIGN KEY FK_179D836399091188');
        $this->addSql('ALTER TABLE regle_de_passage DROP FOREIGN KEY FK_179D8363EAB5690E');
        $this->addSql('DROP TABLE regle_de_passage');
    }
}
