-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le :  ven. 23 fév. 2018 à 11:39
-- Version du serveur :  10.1.28-MariaDB
-- Version de PHP :  7.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `mod_btp`
--

-- --------------------------------------------------------

--
-- Structure de la table `llx_exmclinic`
--

CREATE TABLE IF NOT EXISTS `llx_exmclinic` (
  `rowid` int(11) NOT NULL,
  `datec` datetime DEFAULT NULL,
  `poid` float DEFAULT NULL,
  `vision` varchar(100) DEFAULT NULL,
  `taille` float DEFAULT NULL,
  `audition` varchar(100) DEFAULT NULL,
  `denture` varchar(100) DEFAULT NULL,
  `peux` varchar(100) DEFAULT NULL,
  `da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `llx_exmclinic`
--

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_exmclinic`
--
ALTER TABLE `llx_exmclinic`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `da` (`da`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `llx_exmclinic`
--
ALTER TABLE `llx_exmclinic`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
