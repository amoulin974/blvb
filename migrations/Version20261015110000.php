<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Formats BLVB (lot 6) : match dont les équipes sont les perdants des matchs parents, petite finale (partie.parent_perdants)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partie ADD parent_perdants TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partie DROP parent_perdants');
    }
}
