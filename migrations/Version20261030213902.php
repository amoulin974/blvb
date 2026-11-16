<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030213902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètres du site (D62) : une seule ligne, connexion limitée désactivée, logo d\'origine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parametres_site (id INT NOT NULL, connexion_limitee TINYINT(1) DEFAULT 0 NOT NULL, connexion_verifiee_le DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', adresse_contact VARCHAR(180) DEFAULT NULL, logo VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO parametres_site (id, connexion_limitee) VALUES (1, 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parametres_site');
    }
}
