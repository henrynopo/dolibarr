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
-- Structure de la table `llx_l_pointage`
--

CREATE TABLE IF NOT EXISTS `llx_l_pointage` (
  `rowid` int(11) NOT NULL,
  `fk_user` int(11) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `val` int(11) DEFAULT NULL,
  `jour` int(11) DEFAULT NULL,
  `month_point` int(11) DEFAULT NULL,
  `year_point` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `llx_l_pointage`
--

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_l_pointage`
--
ALTER TABLE `llx_l_pointage`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `idx_user` (`fk_user`),
  ADD KEY `idx_createdby` (`created_by`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `llx_l_pointage`
--
ALTER TABLE `llx_l_pointage`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
