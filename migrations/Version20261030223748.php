<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030223748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fermetures de gymnase : qui a saisi la fermeture, visible des admins seulement (D60)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fermeture_gymnase ADD cree_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fermeture_gymnase ADD CONSTRAINT FK_B0C88B6DFC29C013 FOREIGN KEY (cree_par_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B0C88B6DFC29C013 ON fermeture_gymnase (cree_par_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fermeture_gymnase DROP FOREIGN KEY FK_B0C88B6DFC29C013');
        $this->addSql('DROP INDEX IDX_B0C88B6DFC29C013 ON fermeture_gymnase');
        $this->addSql('ALTER TABLE fermeture_gymnase DROP cree_par_id');
    }
}
