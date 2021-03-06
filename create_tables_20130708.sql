-- phpMyAdmin SQL Dump
-- version 3.3.10.5
-- http://www.phpmyadmin.net
--
-- ホスト: mysql458.db.sakura.ne.jp
-- 生成時間: 2013 年 7 月 08 日 15:59
-- サーバのバージョン: 5.5.28
-- PHP のバージョン: 5.4.7

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- データベース: `kazumix_steam`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `country_players`
--

CREATE TABLE IF NOT EXISTS `country_players` (
  `country_players_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `country` char(2) NOT NULL,
  `total_players` int(10) unsigned NOT NULL,
  `country_players_update` int(10) unsigned NOT NULL,
  PRIMARY KEY (`country_players_id`),
  KEY `country` (`country`),
  KEY `country_players_update` (`country_players_update`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `game_players`
--

CREATE TABLE IF NOT EXISTS `game_players` (
  `game_player_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `player_name` varchar(64) NOT NULL,
  `game_server_id` int(10) unsigned NOT NULL,
  `player_connection_time` int(10) unsigned NOT NULL,
  `player_score` int(11) NOT NULL,
  `player_update` int(10) unsigned NOT NULL,
  PRIMARY KEY (`game_player_id`),
  KEY `game_server_id` (`game_server_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `game_servers`
--

CREATE TABLE IF NOT EXISTS `game_servers` (
  `game_server_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL,
  `country` char(2) NOT NULL,
  `query_port` int(10) unsigned NOT NULL,
  `server_name` varchar(128) DEFAULT NULL,
  `game_port` int(10) unsigned DEFAULT NULL,
  `map_name` varchar(128) DEFAULT NULL,
  `max_players` int(11) DEFAULT NULL,
  `number_of_players` int(11) DEFAULT NULL,
  `no_response_counter` int(10) unsigned NOT NULL,
  `game_server_update` int(10) unsigned NOT NULL,
  PRIMARY KEY (`game_server_id`),
  UNIQUE KEY `ip_and_port` (`ip`,`query_port`),
  KEY `country` (`country`),
  KEY `no_response_counter` (`no_response_counter`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `game_players`
--
ALTER TABLE `game_players`
  ADD CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`game_server_id`) REFERENCES `game_servers` (`game_server_id`) ON DELETE CASCADE ON UPDATE CASCADE;
