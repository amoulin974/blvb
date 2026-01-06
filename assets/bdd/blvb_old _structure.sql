-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 06 jan. 2026 à 06:47
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
-- Base de données : `blvb_old`
--

-- --------------------------------------------------------

--
-- Structure de la table `tvbaclassement`
--

DROP TABLE IF EXISTS `tvbaclassement`;
CREATE TABLE IF NOT EXISTS `tvbaclassement` (
  `code` int NOT NULL,
  `classement` int NOT NULL,
  `codeequipe` int NOT NULL,
  `point` int DEFAULT NULL,
  `joue` int DEFAULT NULL,
  `gagne` int DEFAULT NULL,
  `perdu` int DEFAULT NULL,
  `sp` int DEFAULT NULL,
  `sc` int DEFAULT NULL,
  `diff` int DEFAULT NULL,
  `saison` varchar(30) DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `tvbacontact`
--

DROP TABLE IF EXISTS `tvbacontact`;
CREATE TABLE IF NOT EXISTS `tvbacontact` (
  `code` int NOT NULL,
  `nom` varchar(30) DEFAULT NULL,
  `portable` varchar(15) DEFAULT NULL,
  `tel` varchar(15) DEFAULT NULL,
  `codeequipe` int DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `tvbadatesaison`
--

DROP TABLE IF EXISTS `tvbadatesaison`;
CREATE TABLE IF NOT EXISTS `tvbadatesaison` (
  `code` int NOT NULL,
  `saison` varchar(30) NOT NULL,
  `date_init` date DEFAULT NULL,
  `date_ts1` date DEFAULT NULL,
  `date_ts2` date DEFAULT NULL,
  `date_no1` date DEFAULT NULL,
  `date_no2` date DEFAULT NULL,
  `date_ca1` date DEFAULT NULL,
  `date_ca2` date DEFAULT NULL,
  `date_pa1` date DEFAULT NULL,
  `date_pa2` date DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `tvbaequipe`
--

DROP TABLE IF EXISTS `tvbaequipe`;
CREATE TABLE IF NOT EXISTS `tvbaequipe` (
  `code` int UNSIGNED NOT NULL,
  `nom` varchar(45) DEFAULT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `jour` varchar(10) DEFAULT NULL,
  `heure` varchar(50) DEFAULT NULL,
  `saison` varchar(30) DEFAULT NULL,
  `ordre` int DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Equipe';

-- --------------------------------------------------------

--
-- Structure de la table `tvbafinales`
--

DROP TABLE IF EXISTS `tvbafinales`;
CREATE TABLE IF NOT EXISTS `tvbafinales` (
  `code` int NOT NULL,
  `nombreequipe` int NOT NULL,
  `optionsaison` varchar(9) NOT NULL,
  `barrage` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quart1` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quart2` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quart3` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quart4` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `demi1` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `demi2` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `finale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tvbainitclassement`
--

DROP TABLE IF EXISTS `tvbainitclassement`;
CREATE TABLE IF NOT EXISTS `tvbainitclassement` (
  `code` int NOT NULL,
  `nbequipe` varchar(10) NOT NULL,
  `saison` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tvbajoueur`
--

DROP TABLE IF EXISTS `tvbajoueur`;
CREATE TABLE IF NOT EXISTS `tvbajoueur` (
  `code` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `portable` varchar(15) DEFAULT NULL,
  `tel` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` varchar(100) DEFAULT NULL,
  `cp` varchar(5) DEFAULT NULL,
  `ville` varchar(50) DEFAULT NULL,
  `sexe` varchar(1) DEFAULT NULL,
  `ddn` varchar(10) DEFAULT NULL,
  `codeequipe` int NOT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `tvbaresultat`
--

DROP TABLE IF EXISTS `tvbaresultat`;
CREATE TABLE IF NOT EXISTS `tvbaresultat` (
  `code` int NOT NULL,
  `equipe1` int DEFAULT NULL,
  `equipe2` int DEFAULT NULL,
  `set1` int DEFAULT NULL,
  `set2` int DEFAULT NULL,
  `saison` varchar(30) DEFAULT NULL,
  `datepartie` date DEFAULT NULL,
  `heurepartie` varchar(5) NOT NULL,
  `lieupartie` varchar(100) NOT NULL,
  `numjournee` int DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `tvbasaison`
--

DROP TABLE IF EXISTS `tvbasaison`;
CREATE TABLE IF NOT EXISTS `tvbasaison` (
  `code` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) DEFAULT NULL,
  `finale` varchar(150) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'inconnu',
  `active` tinyint NOT NULL DEFAULT '1',
  `comment` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
