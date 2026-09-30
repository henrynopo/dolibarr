-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le :  ven. 23 fév. 2018 à 11:40
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
-- Structure de la table `llx_exmcontrl`
--

CREATE TABLE IF NOT EXISTS `llx_exmcontrl` (
  `rowid` int(11) NOT NULL,
  `datec` datetime DEFAULT NULL,
  `dated` datetime DEFAULT NULL,
  `datef` datetime DEFAULT NULL,
  `observation` text,
  `type` int(11) DEFAULT NULL,
  `apte` int(11) DEFAULT NULL,
  `da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `llx_exmcontrl`
--

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_exmcontrl`
--
ALTER TABLE `llx_exmcontrl`
  ADD PRIMARY KEY (`rowid`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `llx_exmcontrl`
--
ALTER TABLE `llx_exmcontrl`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
