<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024135247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipe_poule (equipe_id INT NOT NULL, poule_id INT NOT NULL, INDEX IDX_A0137DCA6D861B89 (equipe_id), INDEX IDX_A0137DCA26596FD8 (poule_id), PRIMARY KEY(equipe_id, poule_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipe_poule ADD CONSTRAINT FK_A0137DCA6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe_poule ADD CONSTRAINT FK_A0137DCA26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipe_poule DROP FOREIGN KEY FK_A0137DCA6D861B89');
        $this->addSql('ALTER TABLE equipe_poule DROP FOREIGN KEY FK_A0137DCA26596FD8');
        $this->addSql('DROP TABLE equipe_poule');
    }
}
