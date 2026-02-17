-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : database
-- Généré le : mar. 17 fév. 2026 à 12:34
-- Version du serveur : 8.4.8
-- Version de PHP : 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `blvb_new`
--

-- --------------------------------------------------------

--
-- Structure de la table `classement`
--

CREATE TABLE `classement` (
  `id` int NOT NULL,
  `poule_id` int DEFAULT NULL,
  `equipe_id` int DEFAULT NULL,
  `points` int DEFAULT NULL,
  `set_gagnes` int DEFAULT NULL,
  `set_perdus` int DEFAULT NULL,
  `position` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `creneau`
--

CREATE TABLE `creneau` (
  `id` int NOT NULL,
  `lieu_id` int NOT NULL,
  `jour_semaine` int NOT NULL,
  `heure_debut` time NOT NULL COMMENT '(DC2Type:time_immutable)',
  `heure_fin` time NOT NULL COMMENT '(DC2Type:time_immutable)',
  `capacite` int NOT NULL,
  `prioritaire` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `equipe`
--

CREATE TABLE `equipe` (
  `id` int NOT NULL,
  `lieu_id` int DEFAULT NULL,
  `capitaine_id` int DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `equipe_poule`
--

CREATE TABLE `equipe_poule` (
  `poule_id` int NOT NULL,
  `equipe_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `indisponibilite`
--

CREATE TABLE `indisponibilite` (
  `id` int NOT NULL,
  `saison_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journee`
--

CREATE TABLE `journee` (
  `id` int NOT NULL,
  `poule_id` int NOT NULL,
  `numero` int NOT NULL,
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lieu`
--

CREATE TABLE `lieu` (
  `id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` longtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `partie`
--

CREATE TABLE `partie` (
  `id` int NOT NULL,
  `journee_id` int NOT NULL,
  `poule_id` int NOT NULL,
  `lieu_id` int DEFAULT NULL,
  `id_equipe_recoit_id` int DEFAULT NULL,
  `id_equipe_deplace_id` int DEFAULT NULL,
  `parent_match1_id` int DEFAULT NULL,
  `parent_match2_id` int DEFAULT NULL,
  `date` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `nb_set_gagnant_reception` int DEFAULT NULL,
  `nb_set_gagnant_deplacement` int DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `phase`
--

CREATE TABLE `phase` (
  `id` int NOT NULL,
  `saison_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datedebut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `datefin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `type` int NOT NULL,
  `ordre` int NOT NULL,
  `close` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `poule`
--

CREATE TABLE `poule` (
  `id` int NOT NULL,
  `phase_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nb_montee_defaut` int DEFAULT NULL,
  `nb_descente_defaut` int DEFAULT NULL,
  `niveau` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reset_password_request`
--

CREATE TABLE `reset_password_request` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `selector` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `expires_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `saison`
--

CREATE TABLE `saison` (
  `id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `favori` int NOT NULL DEFAULT '0',
  `date_debut` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `date_fin` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `points_victoire_forte` int NOT NULL DEFAULT '3',
  `points_defaite_forte` int NOT NULL DEFAULT '1',
  `points_nul` int NOT NULL DEFAULT '0',
  `points_forfait` int NOT NULL DEFAULT '-3',
  `points_victoire_faible` int NOT NULL DEFAULT '2',
  `points_defaite_faible` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `id` int NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_verified` tinyint(1) NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `classement`
--
ALTER TABLE `classement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_55EE9D6D26596FD8` (`poule_id`),
  ADD KEY `IDX_55EE9D6D6D861B89` (`equipe_id`);

--
-- Index pour la table `creneau`
--
ALTER TABLE `creneau`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_F9668B5F6AB213CC` (`lieu_id`);

--
-- Index pour la table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Index pour la table `equipe`
--
ALTER TABLE `equipe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_2449BA156AB213CC` (`lieu_id`),
  ADD KEY `IDX_2449BA152A10D79E` (`capitaine_id`);

--
-- Index pour la table `equipe_poule`
--
ALTER TABLE `equipe_poule`
  ADD PRIMARY KEY (`poule_id`,`equipe_id`),
  ADD KEY `IDX_A0137DCA26596FD8` (`poule_id`),
  ADD KEY `IDX_A0137DCA6D861B89` (`equipe_id`);

--
-- Index pour la table `indisponibilite`
--
ALTER TABLE `indisponibilite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_8717036FF965414C` (`saison_id`);

--
-- Index pour la table `journee`
--
ALTER TABLE `journee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DC179AED26596FD8` (`poule_id`);

--
-- Index pour la table `lieu`
--
ALTER TABLE `lieu`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  ADD KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  ADD KEY `IDX_75EA56E016BA31DB` (`delivered_at`);

--
-- Index pour la table `partie`
--
ALTER TABLE `partie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_59B1F3DCF066148` (`journee_id`),
  ADD KEY `IDX_59B1F3D26596FD8` (`poule_id`),
  ADD KEY `IDX_59B1F3D6AB213CC` (`lieu_id`),
  ADD KEY `IDX_59B1F3DB52FB42A` (`id_equipe_recoit_id`),
  ADD KEY `IDX_59B1F3D9EF24701` (`id_equipe_deplace_id`),
  ADD KEY `IDX_59B1F3D48BDCB30` (`parent_match1_id`),
  ADD KEY `IDX_59B1F3D5A0864DE` (`parent_match2_id`);

--
-- Index pour la table `phase`
--
ALTER TABLE `phase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_B1BDD6CBF965414C` (`saison_id`);

--
-- Index pour la table `poule`
--
ALTER TABLE `poule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_FA1FEB4099091188` (`phase_id`);

--
-- Index pour la table `reset_password_request`
--
ALTER TABLE `reset_password_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_7CE748AA76ED395` (`user_id`);

--
-- Index pour la table `saison`
--
ALTER TABLE `saison`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `classement`
--
ALTER TABLE `classement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `creneau`
--
ALTER TABLE `creneau`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `equipe`
--
ALTER TABLE `equipe`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `indisponibilite`
--
ALTER TABLE `indisponibilite`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journee`
--
ALTER TABLE `journee`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lieu`
--
ALTER TABLE `lieu`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `partie`
--
ALTER TABLE `partie`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `phase`
--
ALTER TABLE `phase`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `poule`
--
ALTER TABLE `poule`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reset_password_request`
--
ALTER TABLE `reset_password_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `saison`
--
ALTER TABLE `saison`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user`
--
ALTER TABLE `user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
