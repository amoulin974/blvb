<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sport de la saison (saison.sport), volley-ball pour les saisons existantes (D13)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison ADD sport VARCHAR(30) DEFAULT \'volley\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saison DROP sport');
    }
}
