<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030091911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fermetures de gymnase (D60) : table fermeture_gymnase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fermeture_gymnase (
              id INT AUTO_INCREMENT NOT NULL,
              lieu_id INT NOT NULL,
              motif VARCHAR(255) NOT NULL,
              date_debut DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
              date_fin DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
              INDEX IDX_B0C88B6D6AB213CC (lieu_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              fermeture_gymnase
            ADD
              CONSTRAINT FK_B0C88B6D6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fermeture_gymnase DROP FOREIGN KEY FK_B0C88B6D6AB213CC');
        $this->addSql('DROP TABLE fermeture_gymnase');
    }
}
