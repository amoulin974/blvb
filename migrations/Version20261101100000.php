<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ce que vous pouvez faire (D71) : rôles déjà présentés à chaque compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD presentation_vue JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP presentation_vue');
    }
}
