<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030225406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fermetures de gymnase : motif facultatif, effacé une fois la fermeture terminée, au changement de saison affichée (D60)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fermeture_gymnase CHANGE motif motif VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE fermeture_gymnase SET motif = '' WHERE motif IS NULL");
        $this->addSql('ALTER TABLE fermeture_gymnase CHANGE motif motif VARCHAR(255) NOT NULL');
    }
}
