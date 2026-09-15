-- MySQL dump 10.13  Distrib 8.0.46, for Linux (aarch64)
--
-- Host: localhost    Database: cafe_supply_chain
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phones` json DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `delivery_zone_id` bigint unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_foreign` (`user_id`),
  KEY `addresses_delivery_zone_id_foreign` (`delivery_zone_id`),
  CONSTRAINT `addresses_delivery_zone_id_foreign` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
INSERT INTO `addresses` VALUES (1,3,'فرع طرابلس الرئيسي','طرابلس','شارع الجمهورية','[\"0910000001\"]',32.88720000,13.19130000,1,1,1,'2026-09-13 21:17:39','2026-09-13 21:17:39',NULL),(2,3,'فرع بنغازي','بنغازي','شارع عمر المختار',NULL,32.11870000,20.06870000,2,0,1,'2026-09-13 21:17:39','2026-09-13 21:17:39',NULL),(3,4,'فرع بنغازي الرئيسي','بنغازي','شارع الجمهورية','[\"0910000002\"]',32.11670000,20.06670000,2,1,1,'2026-09-13 21:17:39','2026-09-13 21:17:39',NULL),(4,4,'فرع مصراتة','مصراتة','شارع عمر المختار',NULL,32.37740000,15.09450000,3,0,1,'2026-09-13 21:17:39','2026-09-13 21:17:39',NULL),(5,5,'فرع مصراتة الرئيسي','مصراتة','شارع الجمهورية','[\"0910000003\"]',32.37540000,15.09250000,3,1,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(6,5,'فرع الزاوية','الزاوية','شارع عمر المختار',NULL,32.75420000,12.72980000,4,0,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(7,6,'فرع الزاوية الرئيسي','الزاوية','شارع الجمهورية','[\"0910000004\"]',32.75220000,12.72780000,4,1,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(8,6,'فرع سبها','سبها','شارع عمر المختار',NULL,27.03970000,14.43030000,5,0,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(9,7,'فرع سبها الرئيسي','سبها','شارع الجمهورية','[\"0910000005\"]',27.03770000,14.42830000,5,1,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(10,7,'فرع البيضاء','البيضاء','شارع عمر المختار',NULL,32.76870000,21.73530000,6,0,1,'2026-09-13 21:17:40','2026-09-13 21:17:40',NULL),(11,11,'الفرع الرئيسي - معدل','طرابلس','شارع الجمهورية','[\"0977284503\"]',32.89100000,13.19100000,1,0,1,'2026-09-13 22:03:22','2026-09-13 22:03:22',NULL),(12,11,'فرع للحذف',NULL,NULL,NULL,32.80000000,13.10000000,NULL,0,1,'2026-09-13 22:03:22','2026-09-13 22:03:22','2026-09-13 22:03:22'),(13,12,'الفرع الرئيسي - معدل','طرابلس','شارع الجمهورية','[\"0946387065\"]',32.89100000,13.19100000,1,0,1,'2026-09-13 22:04:03','2026-09-13 22:04:04',NULL),(14,12,'فرع للحذف',NULL,NULL,NULL,32.80000000,13.10000000,NULL,0,1,'2026-09-13 22:04:04','2026-09-13 22:04:04','2026-09-13 22:04:04');
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-15 20:19:27
