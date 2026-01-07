-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 06 jan. 2026 à 06:49
-- Version du serveur : 8.4.7
-- Version de PHP : 8.5.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `blvb`
--

-- --------------------------------------------------------

--
-- Structure de la table `classement`
--

DROP TABLE IF EXISTS `classement`;
CREATE TABLE IF NOT EXISTS `classement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `poule_id` int DEFAULT NULL,
  `equipe_id` int DEFAULT NULL,
  `points` int DEFAULT NULL,
  `set_gagnes` int DEFAULT NULL,
  `set_perdus` int DEFAULT NULL,
  `position` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_55EE9D6D26596FD8` (`poule_id`),
  KEY `IDX_55EE9D6D6D861B89` (`equipe_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `creneau`
--

DROP TABLE IF EXISTS `creneau`;
CREATE TABLE IF NOT EXISTS `creneau` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lieu_id` int NOT NULL,
  `jour_semaine` int NOT NULL,
  `heure_debut` time NOT NULL COMMENT '(DC2Type:time_immutable)',
  `heure_fin` time NOT NULL COMMENT '(DC2Type:time_immutable)',
  `capacite` int NOT NULL,
  `prioritaire` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_F9668B5F6AB213CC` (`lieu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `equipe`
--

DROP TABLE IF EXISTS `equipe`;
CREATE TABLE IF NOT EXISTS `equipe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lieu_id` int DEFAULT NULL,
  `capitaine_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2449BA152A10D79E` (`capitaine_id`),
  KEY `IDX_2449BA156AB213CC` (`lieu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `equipe`
--

INSERT INTO `equipe` (`id`, `nom`, `lieu_id`, `capitaine_id`) VALUES
(4, 'ANGELUKO PRINTZESAK', 7, NULL),
(5, 'ANGLET 4', 7, NULL),
(6, 'EROAK', 7, NULL),
(7, 'KAYOU', 7, NULL),
(8, 'LAGUNEN ETXEA', 7, NULL),
(9, 'LAPATINAK', 7, NULL),
(10, 'STRAP\'ATTACK', 7, NULL),
(11, 'ANGR\'ACE', 8, NULL),
(12, 'ANGR\'ACE 2', 8, NULL),
(13, 'BAIONAKO PIKOLA', 10, NULL),
(14, 'BAYONNE VOLLEY BOULES', 10, NULL),
(15, 'EGUZKI TXIKIAK', 10, NULL),
(16, 'LEO LAGRANGE', 10, NULL),
(17, 'ASPATATAK', 9, NULL),
(18, 'LASAI ATACK', 9, NULL),
(19, 'LES DARONNES', 9, NULL),
(20, 'LES FILOUS DU FILET', 9, NULL),
(21, 'LES FRAPADINGUES', 9, NULL),
(22, 'PERLEMPACK', 9, NULL),
(23, 'CAP\'OUT', 11, NULL),
(24, 'LA HIGH FIVE', 11, NULL),
(25, 'LES CAP TAINES', 11, NULL),
(26, 'CAP2', 11, NULL),
(27, 'LA CAP\'SULE', 11, NULL),
(28, 'CHAOUCHTADOR', 12, NULL),
(29, 'LES VAGABONDRES', 12, NULL),
(30, 'ONDRES DE CHOC', 12, NULL),
(31, 'ADISKIDEAK', 6, NULL),
(32, 'JOKO LOKO', 6, NULL),
(33, 'KUKUTXUROS', 6, NULL),
(34, 'LAGUNAK', 6, NULL),
(35, 'OSASUNA', 6, NULL),
(36, 'TXULETAK', 6, NULL),
(37, 'TOSSE 2', 13, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `equipe_poule`
--

DROP TABLE IF EXISTS `equipe_poule`;
CREATE TABLE IF NOT EXISTS `equipe_poule` (
  `equipe_id` int NOT NULL,
  `poule_id` int NOT NULL,
  PRIMARY KEY (`poule_id`,`equipe_id`),
  KEY `IDX_A0137DCA6D861B89` (`equipe_id`),
  KEY `IDX_A0137DCA26596FD8` (`poule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `indisponibilite`
--

DROP TABLE IF EXISTS `indisponibilite`;
CREATE TABLE IF NOT EXISTS `indisponibilite` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saison_id` int NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_8717036FF965414C` (`saison_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journee`
--

DROP TABLE IF EXISTS `journee`;
CREATE TABLE IF NOT EXISTS `journee` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero` int NOT NULL,
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `poule_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DC179AED26596FD8` (`poule_id`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lieu`
--

DROP TABLE IF EXISTS `lieu`;
CREATE TABLE IF NOT EXISTS `lieu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `partie`
--

DROP TABLE IF EXISTS `partie`;
CREATE TABLE IF NOT EXISTS `partie` (
  `id` int NOT NULL AUTO_INCREMENT,
  `poule_id` int NOT NULL,
  `lieu_id` int DEFAULT NULL,
  `id_equipe_recoit_id` int DEFAULT NULL,
  `id_equipe_deplace_id` int DEFAULT NULL,
  `date` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `nb_set_gagnant_reception` int DEFAULT NULL,
  `nb_set_gagnant_deplacement` int DEFAULT NULL,
  `parent_match1_id` int DEFAULT NULL,
  `parent_match2_id` int DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journee_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_59B1F3DB52FB42A` (`id_equipe_recoit_id`),
  KEY `IDX_59B1F3D9EF24701` (`id_equipe_deplace_id`),
  KEY `IDX_59B1F3D26596FD8` (`poule_id`),
  KEY `IDX_59B1F3D6AB213CC` (`lieu_id`),
  KEY `IDX_59B1F3D48BDCB30` (`parent_match1_id`),
  KEY `IDX_59B1F3D5A0864DE` (`parent_match2_id`),
  KEY `IDX_59B1F3DCF066148` (`journee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=317 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `phase`
--

DROP TABLE IF EXISTS `phase`;
CREATE TABLE IF NOT EXISTS `phase` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saison_id` int NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `datedebut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `datefin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `type` int NOT NULL,
  `ordre` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B1BDD6CBF965414C` (`saison_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `poule`
--

DROP TABLE IF EXISTS `poule`;
CREATE TABLE IF NOT EXISTS `poule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phase_id` int NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nb_montee_defaut` int NOT NULL,
  `nb_descente_defaut` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_FA1FEB4099091188` (`phase_id`)
) ENGINE=InnoDB AUTO_INCREMENT=277 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reset_password_request`
--

DROP TABLE IF EXISTS `reset_password_request`;
CREATE TABLE IF NOT EXISTS `reset_password_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `selector` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `expires_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_7CE748AA76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `saison`
--

DROP TABLE IF EXISTS `saison`;
CREATE TABLE IF NOT EXISTS `saison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `favori` int NOT NULL DEFAULT '0',
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `points_victoire_forte` int NOT NULL DEFAULT '3',
  `points_defaite_forte` int NOT NULL DEFAULT '1',
  `points_nul` int NOT NULL DEFAULT '0',
  `points_forfait` int NOT NULL DEFAULT '-3',
  `points_victoire_faible` int NOT NULL DEFAULT '2',
  `points_defaite_faible` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_verified` tinyint(1) NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `classement`
--
ALTER TABLE `classement`
  ADD CONSTRAINT `FK_55EE9D6D26596FD8` FOREIGN KEY (`poule_id`) REFERENCES `poule` (`id`),
  ADD CONSTRAINT `FK_55EE9D6D6D861B89` FOREIGN KEY (`equipe_id`) REFERENCES `equipe` (`id`);

--
-- Contraintes pour la table `creneau`
--
ALTER TABLE `creneau`
  ADD CONSTRAINT `FK_F9668B5F6AB213CC` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `equipe`
--
ALTER TABLE `equipe`
  ADD CONSTRAINT `FK_2449BA152A10D79E` FOREIGN KEY (`capitaine_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_2449BA156AB213CC` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`id`);

--
-- Contraintes pour la table `equipe_poule`
--
ALTER TABLE `equipe_poule`
  ADD CONSTRAINT `FK_A0137DCA26596FD8` FOREIGN KEY (`poule_id`) REFERENCES `poule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_A0137DCA6D861B89` FOREIGN KEY (`equipe_id`) REFERENCES `equipe` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `indisponibilite`
--
ALTER TABLE `indisponibilite`
  ADD CONSTRAINT `FK_8717036FF965414C` FOREIGN KEY (`saison_id`) REFERENCES `saison` (`id`);

--
-- Contraintes pour la table `journee`
--
ALTER TABLE `journee`
  ADD CONSTRAINT `FK_DC179AED26596FD8` FOREIGN KEY (`poule_id`) REFERENCES `poule` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `partie`
--
ALTER TABLE `partie`
  ADD CONSTRAINT `FK_59B1F3D26596FD8` FOREIGN KEY (`poule_id`) REFERENCES `poule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_59B1F3D48BDCB30` FOREIGN KEY (`parent_match1_id`) REFERENCES `partie` (`id`),
  ADD CONSTRAINT `FK_59B1F3D5A0864DE` FOREIGN KEY (`parent_match2_id`) REFERENCES `partie` (`id`),
  ADD CONSTRAINT `FK_59B1F3D6AB213CC` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`id`),
  ADD CONSTRAINT `FK_59B1F3D9EF24701` FOREIGN KEY (`id_equipe_deplace_id`) REFERENCES `equipe` (`id`),
  ADD CONSTRAINT `FK_59B1F3DB52FB42A` FOREIGN KEY (`id_equipe_recoit_id`) REFERENCES `equipe` (`id`),
  ADD CONSTRAINT `FK_59B1F3DCF066148` FOREIGN KEY (`journee_id`) REFERENCES `journee` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `phase`
--
ALTER TABLE `phase`
  ADD CONSTRAINT `FK_B1BDD6CBF965414C` FOREIGN KEY (`saison_id`) REFERENCES `saison` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `poule`
--
ALTER TABLE `poule`
  ADD CONSTRAINT `FK_FA1FEB4099091188` FOREIGN KEY (`phase_id`) REFERENCES `phase` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reset_password_request`
--
ALTER TABLE `reset_password_request`
  ADD CONSTRAINT `FK_7CE748AA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
