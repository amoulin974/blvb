<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\DescriptionClub;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiche du club : description en texte brut convertie en HTML pour l\'éditeur (D63)';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, description FROM club WHERE description IS NOT NULL') as $ligne) {
            $html = DescriptionClub::depuisTexte($ligne['description']);
            if ($html !== $ligne['description']) {
                $this->addSql('UPDATE club SET description = :description WHERE id = :id', ['description' => $html, 'id' => $ligne['id']]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Retour au texte brut : paragraphes séparés par une ligne vide, sauts de ligne gardés, balises retirées
        foreach ($this->connection->fetchAllAssociative('SELECT id, description FROM club WHERE description IS NOT NULL') as $ligne) {
            $texte = preg_replace(['#<br\s*/?>#i', '#</(p|h3|h4|li)>#i'], ["\n", "\n\n"], $ligne['description']);
            $texte = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($texte), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
            $this->addSql('UPDATE club SET description = :description WHERE id = :id', ['description' => $texte === '' ? null : $texte, 'id' => $ligne['id']]);
        }
    }
}
