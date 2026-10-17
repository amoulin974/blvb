<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017145440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rappel des scores non saisis : date d\'envoi du rappel sur chaque match (partie.rappel_score_envoye_le)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partie ADD rappel_score_envoye_le DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partie DROP rappel_score_envoye_le');
    }
}
