-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le :  ven. 23 fév. 2018 à 11:38
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
-- Structure de la table `llx_aparieldig`
--

CREATE TABLE IF NOT EXISTS `llx_aparieldig` (
  `rowid` int(11) NOT NULL,
  `datec` datetime DEFAULT NULL,
  `bouch` varchar(200) DEFAULT NULL,
  `amyg` varchar(200) DEFAULT NULL,
  `foie` varchar(200) DEFAULT NULL,
  `rate` varchar(200) DEFAULT NULL,
  `hern` varchar(200) DEFAULT NULL,
  `da` int(11)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `llx_aparieldig`
--

-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_aparieldig`
--
ALTER TABLE `llx_aparieldig`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `da` (`da`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `llx_aparieldig`
--
ALTER TABLE `llx_aparieldig`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
