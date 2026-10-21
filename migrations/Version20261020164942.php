<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020164942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Règlement par saison (D55 à D57) : tables reglement et reglement_version (versions précédentes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reglement (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, modifie_par_id INT DEFAULT NULL, contenu LONGTEXT NOT NULL, modifie_le DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_EBE4C14CF965414C (saison_id), INDEX IDX_EBE4C14C553B2554 (modifie_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reglement_version (id INT AUTO_INCREMENT NOT NULL, reglement_id INT NOT NULL, auteur_id INT DEFAULT NULL, contenu LONGTEXT NOT NULL, enregistre_le DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DDDDE796A477111 (reglement_id), INDEX IDX_DDDDE7960BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reglement ADD CONSTRAINT FK_EBE4C14CF965414C FOREIGN KEY (saison_id) REFERENCES saison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reglement ADD CONSTRAINT FK_EBE4C14C553B2554 FOREIGN KEY (modifie_par_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reglement_version ADD CONSTRAINT FK_DDDDE796A477111 FOREIGN KEY (reglement_id) REFERENCES reglement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reglement_version ADD CONSTRAINT FK_DDDDE7960BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14CF965414C');
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14C553B2554');
        $this->addSql('ALTER TABLE reglement_version DROP FOREIGN KEY FK_DDDDE796A477111');
        $this->addSql('ALTER TABLE reglement_version DROP FOREIGN KEY FK_DDDDE7960BB6FE6');
        $this->addSql('DROP TABLE reglement');
        $this->addSql('DROP TABLE reglement_version');
    }
}
