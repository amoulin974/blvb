<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031184649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Volley Stats (D68) : seuil d\'échanges détaillés réglable par l\'admin, 80 % par défaut';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parametres_site ADD seuil_echanges_detailles INT DEFAULT 80 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parametres_site DROP seuil_echanges_detailles');
    }
}
