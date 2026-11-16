<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Formats BLVB (lot 6) : options propres au format d\'une phase (phase.options, JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phase ADD options JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phase DROP options');
    }
}
