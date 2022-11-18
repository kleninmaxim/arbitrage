-- MySQL dump 10.13  Distrib 8.0.21, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: arbitrage
-- ------------------------------------------------------
-- Server version	8.0.31-0ubuntu0.20.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `balances`
--

NOT ACTUAL!!!!!!!!! BE CAREFULL!

DROP TABLE IF EXISTS `balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balances` (
  `asset` varchar(255) NOT NULL,
  `exchange_id` int unsigned NOT NULL,
  `free` decimal(25,8) NOT NULL DEFAULT '0.00000000',
  `used` decimal(25,8) NOT NULL DEFAULT '0.00000000',
  `total` decimal(25,8) NOT NULL DEFAULT '0.00000000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `fk_balances_asset_exchange` (`asset`,`exchange_id`),
  KEY `fk_balances_exchanges` (`exchange_id`),
  CONSTRAINT `fk_balances_exchanges` FOREIGN KEY (`exchange_id`) REFERENCES `exchanges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balances`
--

LOCK TABLES `balances` WRITE;
/*!40000 ALTER TABLE `balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exchanges`
--

DROP TABLE IF EXISTS `exchanges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchanges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exchange` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchanges`
--

LOCK TABLES `exchanges` WRITE;
/*!40000 ALTER TABLE `exchanges` DISABLE KEYS */;
INSERT INTO `exchanges` VALUES (1,'binance'),(2,'exmo');
/*!40000 ALTER TABLE `exchanges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mirror_trades`
--

DROP TABLE IF EXISTS `mirror_trades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mirror_trades` (
  `trade_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  UNIQUE KEY `fx_mirror_trades_order_id_trade_id` (`trade_id`,`order_id`),
  KEY `fx_mirror_trades_order_id_idx` (`order_id`),
  CONSTRAINT `fx_mirror_trades_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fx_mirror_trades_trade_id` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mirror_trades`
--

LOCK TABLES `mirror_trades` WRITE;
/*!40000 ALTER TABLE `mirror_trades` DISABLE KEYS */;
/*!40000 ALTER TABLE `mirror_trades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exchange_id` int unsigned NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `symbol` varchar(31) NOT NULL,
  `side` varchar(4) NOT NULL,
  `price` decimal(25,8) NOT NULL,
  `amount` decimal(25,8) NOT NULL,
  `quote` decimal(25,8) NOT NULL,
  `status` varchar(31) NOT NULL,
  `filled` decimal(25,8) NOT NULL,
  `timestamp` decimal(25,8) NOT NULL,
  `datetime` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  UNIQUE KEY `fx_orders_order_id_exchange_id` (`order_id`,`exchange_id`),
  KEY `fx_orders_exchanges_idx` (`exchange_id`),
  CONSTRAINT `fx_orders_exchanges` FOREIGN KEY (`exchange_id`) REFERENCES `exchanges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trades`
--

DROP TABLE IF EXISTS `trades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exchange_id` int unsigned NOT NULL,
  `trade_id` varchar(255) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `symbol` varchar(31) NOT NULL,
  `trade_type` varchar(5) DEFAULT NULL,
  `side` varchar(4) NOT NULL,
  `price` decimal(25,8) NOT NULL,
  `amount` decimal(25,8) NOT NULL,
  `quote` decimal(25,8) NOT NULL,
  `fee_asset` varchar(31) DEFAULT NULL,
  `fee_amount` decimal(25,8) DEFAULT '0.00000000',
  `timestamp` decimal(25,8) NOT NULL,
  `datetime` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  UNIQUE KEY `fx_trades_trade_id_order_id` (`trade_id`,`order_id`,`exchange_id`),
  KEY `fx_trades_exchanges_idx` (`exchange_id`),
  CONSTRAINT `fx_trades_exchanges` FOREIGN KEY (`exchange_id`) REFERENCES `exchanges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trades`
--

LOCK TABLES `trades` WRITE;
/*!40000 ALTER TABLE `trades` DISABLE KEYS */;
/*!40000 ALTER TABLE `trades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'arbitrage'
--

--
-- Dumping routines for database 'arbitrage'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-11-18 15:06:34
