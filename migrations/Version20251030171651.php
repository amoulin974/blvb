<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030171651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE classement (id INT AUTO_INCREMENT NOT NULL, poule_id INT DEFAULT NULL, equipe_id INT DEFAULT NULL, points INT DEFAULT NULL, set_gagnes INT DEFAULT NULL, set_perdus INT DEFAULT NULL, position INT DEFAULT NULL, INDEX IDX_55EE9D6D26596FD8 (poule_id), INDEX IDX_55EE9D6D6D861B89 (equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6D26596FD8 FOREIGN KEY (poule_id) REFERENCES poule (id)');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6D6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6D26596FD8');
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6D6D861B89');
        $this->addSql('DROP TABLE classement');
    }
}
