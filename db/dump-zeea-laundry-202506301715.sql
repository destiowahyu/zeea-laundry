-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: zeea-laundry
-- ------------------------------------------------------
-- Server version	8.0.30

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
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'destiowahyu','$2y$10$/qPUi/YPKktBtttvMxvBFOn2.DJSC0AzTWdP5Ivvf3f.xoFBNCBlS','admin_67f8bb1b00679.jpeg');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `antar_jemput`
--

DROP TABLE IF EXISTS `antar_jemput`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antar_jemput` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pesanan` int DEFAULT NULL,
  `tracking_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pelanggan` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_hp` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_pelanggan` int DEFAULT NULL,
  `layanan` enum('antar','jemput','antar-jemput') COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_antar` text COLLATE utf8mb4_general_ci,
  `alamat_jemput` text COLLATE utf8mb4_general_ci,
  `status` enum('menunggu','dalam perjalanan','selesai') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'menunggu',
  `waktu_antar` datetime DEFAULT NULL,
  `waktu_jemput` datetime DEFAULT NULL,
  `harga_custom` decimal(10,2) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pesanan` (`id_pesanan`),
  KEY `fk_antar_jemput_pelanggan` (`id_pelanggan`),
  KEY `idx_tracking_code` (`tracking_code`),
  CONSTRAINT `antar_jemput_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_antar_jemput_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `antar_jemput`
--

LOCK TABLES `antar_jemput` WRITE;
/*!40000 ALTER TABLE `antar_jemput` DISABLE KEYS */;
INSERT INTO `antar_jemput` VALUES (1,38,'ZL250324007d24','Azzam Wisam Muafa Laniofa','+6285955196688',7,'antar','Desa Padaran Dukuh Jambangan RT 05 RW 04',NULL,'menunggu','2025-04-28 13:00:00',NULL,NULL,NULL),(2,44,'ZL250424007dc7','Azzam Wisam Muafa Laniofa','+6285955196688',7,'antar','Ds Padaran',NULL,'menunggu','2025-05-02 15:00:00',NULL,NULL,NULL),(3,44,'ZL250424007dc7','Azzam Wisam Muafa Laniofa','+6285955196688',7,'antar','sfsf',NULL,'menunggu','2025-05-02 15:03:00',NULL,0.00,NULL),(4,45,'ZL2504280157d9','Gina Morissa Fenia','+6285766655178',15,'antar','gina gina',NULL,'selesai','2025-05-02 15:47:00',NULL,NULL,NULL),(5,NULL,NULL,NULL,NULL,NULL,'jemput',NULL,'Jalan Abadi No 12, Padaran','selesai',NULL,'2025-06-20 10:00:00',NULL,NULL),(6,NULL,NULL,'Sempurna',NULL,22,'jemput',NULL,'Jalan Kedungmundu','selesai',NULL,'2025-06-25 00:39:00',NULL,NULL),(7,NULL,NULL,'Hindia Belanda',NULL,23,'jemput',NULL,'Jalan KH. Mansyur No 10','menunggu',NULL,'2025-06-26 10:00:00',NULL,NULL);
/*!40000 ALTER TABLE `antar_jemput` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `antarjemput_status`
--

DROP TABLE IF EXISTS `antarjemput_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antarjemput_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `antarjemput_status`
--

LOCK TABLES `antarjemput_status` WRITE;
/*!40000 ALTER TABLE `antarjemput_status` DISABLE KEYS */;
INSERT INTO `antarjemput_status` VALUES (1,'active','2025-06-25 03:15:37','destiowahyu');
/*!40000 ALTER TABLE `antarjemput_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paket`
--

DROP TABLE IF EXISTS `paket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paket` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  `icon` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paket`
--

LOCK TABLES `paket` WRITE;
/*!40000 ALTER TABLE `paket` DISABLE KEYS */;
INSERT INTO `paket` VALUES (14,'Cuci Setrika',6500.00,'Paket ini adalah paket lengkap. Setelah pakaian dicuci bersih, semua pakaian juga akan disetrika sehingga rapi saat sampai di tangan pelanggan','icon_67dc86254cde9.png'),(15,'Cuci Kering',4500.00,'Paket ini hanya melayani cuci saja sampai pakaian kering tanpa disetrika','icon_67df0951ac033.png'),(16,'Setrika Saja',4500.00,'Paket khusus pelanggan yang tidak ingin repot menyetrika. Paket ini hanya melayani setrika saja tanpa proses mencuci','icon_67dc8637de937.png'),(21,'Paket Khusus',0.00,'Paket dengan harga kustom','custom.png');
/*!40000 ALTER TABLE `paket` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggan`
--

DROP TABLE IF EXISTS `pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pelanggan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_hp` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_hp` (`no_hp`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggan`
--

LOCK TABLES `pelanggan` WRITE;
/*!40000 ALTER TABLE `pelanggan` DISABLE KEYS */;
INSERT INTO `pelanggan` VALUES (6,'+628546465224','Husna Nur Alamin'),(7,'+6285955196688','Azzam Wisam Muafa Laniofa'),(8,'+62820193019309','Novita Khoirunnisa'),(9,'+6287546597211','Siti Fadhillah'),(10,'+6285232252572','Deni Kurniawan'),(11,'+6285112631998','Nabila Husna Putri'),(12,'+6285654110520','Andini Cantika Putri'),(13,'+6285929096633','Juicy Lucy'),(14,'+6281325445652','Fabio Asher'),(15,'+6285766655178','Gina Morissa Fenia'),(16,'+6285136578955','Shafira Tinaria Azzahra'),(17,'+62892784998392','Kinan Aira Dina'),(18,'+6285456255879','Denny Setiawan'),(19,'+6287546985111','Umay Tresna'),(20,'+6285456789456','Jefri Nichol'),(21,'+62854213456789','Umam'),(22,'+6289381978913','Sempurna'),(23,'+62878271872817','Junior Robert');
/*!40000 ALTER TABLE `pelanggan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pesanan`
--

DROP TABLE IF EXISTS `pesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pesanan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tracking_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_pelanggan` int NOT NULL,
  `id_paket` int NOT NULL,
  `berat` decimal(5,2) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `status` enum('diproses','selesai','dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'diproses',
  `status_pembayaran` enum('belum_dibayar','sudah_dibayar') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'belum_dibayar',
  `waktu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `harga_custom` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `id_pelanggan` (`id_pelanggan`),
  KEY `id_paket` (`id_paket`),
  KEY `idx_tracking_code` (`tracking_code`),
  CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`id_paket`) REFERENCES `paket` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pesanan`
--

LOCK TABLES `pesanan` WRITE;
/*!40000 ALTER TABLE `pesanan` DISABLE KEYS */;
INSERT INTO `pesanan` VALUES (1,'ZL250321006e33',6,14,1.00,6500.00,'selesai','sudah_dibayar','2025-03-21 08:51:56',0.00),(2,'ZL250321007ab0',7,14,1.50,9750.00,'selesai','belum_dibayar','2025-03-21 09:32:37',0.00),(3,'ZL2503210070c2',7,14,1.50,9750.00,'selesai','belum_dibayar','2025-03-21 09:38:12',0.00),(4,'ZL2503210073b6',7,14,2.50,16250.00,'selesai','belum_dibayar','2025-03-21 09:59:29',0.00),(5,'ZL2503210071a9',7,15,1.00,4500.00,'selesai','belum_dibayar','2025-03-21 10:01:30',0.00),(6,'ZL250321007a24',7,14,1.00,6500.00,'selesai','belum_dibayar','2025-03-21 10:08:14',0.00),(7,'ZL25032100759d',7,14,1.00,6500.00,'diproses','belum_dibayar','2025-03-21 13:48:39',0.00),(8,'ZL2503210080b2',8,14,2.50,16250.00,'selesai','sudah_dibayar','2025-03-21 13:54:11',0.00),(9,'ZL2503210080b2',8,14,1.50,9750.00,'selesai','sudah_dibayar','2025-03-21 13:54:36',0.00),(10,'ZL250321008832',8,14,1.50,9750.00,'diproses','belum_dibayar','2025-03-21 14:02:14',0.00),(11,'ZL25032100885d',8,15,2.50,11250.00,'diproses','belum_dibayar','2025-03-21 14:03:08',0.00),(12,'ZL2503210085b0',8,14,2.90,18850.00,'selesai','belum_dibayar','2025-03-21 14:25:49',0.00),(13,'ZL250321009f9d',9,15,10.40,46800.00,'diproses','belum_dibayar','2025-03-21 14:27:36',0.00),(14,'ZL2503210089b0',8,14,6.70,43550.00,'selesai','sudah_dibayar','2025-03-21 20:57:47',0.00),(15,'ZL250321010f67',10,15,5.40,24300.00,'diproses','belum_dibayar','2025-03-21 22:39:56',0.00),(16,'ZL250323007e56',7,14,10.60,68900.00,'selesai','sudah_dibayar','2025-03-23 14:48:52',0.00),(19,'ZL250324007c7a',7,14,2.60,16900.00,'selesai','sudah_dibayar','2025-03-24 02:25:26',0.00),(20,'ZL250324007c7a',7,15,1.04,4680.00,'diproses','belum_dibayar','2025-03-24 02:25:26',0.00),(29,'ZL250324007337',7,16,30.00,135000.00,'diproses','belum_dibayar','2025-03-24 14:37:56',0.00),(30,'ZL2503240135d3',13,14,12.00,96000.00,'selesai','sudah_dibayar','2025-03-24 14:38:40',8000.00),(31,'ZL250324013aab',13,14,15.00,180000.00,'diproses','belum_dibayar','2025-03-24 14:39:10',12000.00),(32,'ZL250324013aab',13,15,1.00,4500.00,'diproses','belum_dibayar','2025-03-24 14:39:56',0.00),(33,'ZL250324013919',13,14,1.00,10000.00,'diproses','belum_dibayar','2025-03-24 14:40:14',10000.00),(34,'ZL250324007037',7,21,1.00,3000.00,'selesai','sudah_dibayar','2025-03-24 14:53:54',3000.00),(35,'ZL2503240074b7',7,15,6.00,27000.00,'diproses','belum_dibayar','2025-03-24 14:57:25',0.00),(36,'ZL2503240143d8',14,14,20.00,130000.00,'diproses','belum_dibayar','2025-03-24 14:57:50',0.00),(37,'ZL250324014bde',14,21,2.00,20000.00,'selesai','sudah_dibayar','2025-03-24 14:58:39',10000.00),(38,'ZL250324007d24',7,14,1.00,6500.00,'selesai','sudah_dibayar','2025-03-24 15:02:45',0.00),(39,'ZL250324007d24',7,16,2.00,9000.00,'selesai','sudah_dibayar','2025-03-24 15:02:45',0.00),(40,'ZL25032401388c',13,16,1.00,4500.00,'selesai','sudah_dibayar','2025-03-24 15:05:06',0.00),(41,'ZL25032401388c',13,21,2.00,14000.00,'selesai','sudah_dibayar','2025-03-24 15:05:06',7000.00),(42,'ZL25032401582a',15,14,2.50,16250.00,'selesai','sudah_dibayar','2025-03-24 19:55:01',0.00),(43,'ZL2503240150c5',15,15,4.60,20700.00,'selesai','sudah_dibayar','2025-03-24 19:56:11',0.00),(44,'ZL250424007dc7',7,14,1.00,6500.00,'selesai','belum_dibayar','2025-04-24 20:06:15',0.00),(45,'ZL2504280157d9',15,14,1.70,11050.00,'selesai','sudah_dibayar','2025-04-28 16:56:13',0.00),(46,'ZL250502009VBB',9,15,5.60,25200.00,'diproses','belum_dibayar','2025-05-02 14:29:41',0.00),(47,'ZL250502009VBB',9,16,1.40,6300.00,'diproses','belum_dibayar','2025-05-02 14:29:41',0.00),(48,'ZL2505020165EH',16,14,9.50,61750.00,'selesai','sudah_dibayar','2025-05-02 15:14:35',0.00),(49,'ZL250503017CCS',17,14,3.50,22750.00,'selesai','belum_dibayar','2025-05-03 22:23:38',0.00),(50,'ZL250510009JZ5',9,14,12.00,78000.00,'diproses','belum_dibayar','2025-05-10 20:43:08',0.00),(51,'ZL250604015C3L',15,15,1.03,4635.00,'diproses','belum_dibayar','2025-06-04 02:01:02',0.00),(52,'ZL250619018XZX',18,14,2.54,16510.00,'selesai','sudah_dibayar','2025-06-19 09:16:12',0.00),(53,'ZL250619019ZOC',19,15,3.21,14445.00,'selesai','sudah_dibayar','2025-06-19 09:17:24',0.00),(54,'ZL250619020YW2',20,14,9.21,59865.00,'dibatalkan','belum_dibayar','2025-06-19 09:18:51',0.00),(55,'ZL250625014XCL',14,14,1.00,6500.00,'selesai','sudah_dibayar','2025-06-25 00:34:49',0.00),(56,'ZL250625023PQ2',23,14,1.01,6565.00,'selesai','sudah_dibayar','2025-06-25 20:23:27',0.00);
/*!40000 ALTER TABLE `pesanan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `riwayat`
--

DROP TABLE IF EXISTS `riwayat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riwayat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pesanan` int NOT NULL,
  `tgl_selesai` date NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pesanan` (`id_pesanan`),
  CONSTRAINT `riwayat_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `riwayat`
--

LOCK TABLES `riwayat` WRITE;
/*!40000 ALTER TABLE `riwayat` DISABLE KEYS */;
INSERT INTO `riwayat` VALUES (1,14,'2025-03-21',43550.00),(2,16,'2025-03-23',68900.00),(3,19,'2025-03-24',16900.00),(5,40,'2025-03-24',4500.00),(6,38,'2025-03-24',6500.00),(7,39,'2025-03-24',9000.00),(8,37,'2025-03-24',20000.00),(9,41,'2025-03-24',14000.00),(10,34,'2025-03-24',3000.00),(11,43,'2025-03-24',20700.00),(12,30,'2025-04-11',96000.00),(13,45,'2025-04-28',11050.00),(14,44,'2025-04-30',6500.00),(15,42,'2025-04-30',16250.00),(16,49,'2025-05-03',22750.00),(17,48,'2025-05-10',61750.00),(18,52,'2025-06-19',16510.00),(19,53,'2025-06-19',14445.00),(20,8,'2025-06-19',16250.00),(21,9,'2025-06-19',9750.00),(22,55,'2025-06-25',6500.00),(23,56,'2025-06-30',6565.00);
/*!40000 ALTER TABLE `riwayat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'antar_jemput_active','active','2025-05-02 08:59:18');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `toko_status`
--

DROP TABLE IF EXISTS `toko_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `toko_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` enum('buka','tutup') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'buka',
  `waktu` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `toko_status`
--

LOCK TABLES `toko_status` WRITE;
/*!40000 ALTER TABLE `toko_status` DISABLE KEYS */;
INSERT INTO `toko_status` VALUES (1,'buka','2025-06-25 10:15:19');
/*!40000 ALTER TABLE `toko_status` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `prevent_multiple_status_insert` BEFORE INSERT ON `toko_status` FOR EACH ROW BEGIN
    -- Jika sudah ada baris di toko_status, update baris yang sudah ada
    IF (SELECT COUNT(*) FROM toko_status) > 0 THEN
        UPDATE toko_status SET
            status = NEW.status,
            waktu = NEW.waktu
        WHERE id = 1; -- Asumsi ID 1 adalah baris status tunggal
        -- SIGNAL SQLSTATE digunakan untuk menghentikan INSERT dan memberikan pesan.
        -- Namun, karena kita sudah meng-UPDATE, kita bisa saja tidak menghentikan prosesnya
        -- atau memilih untuk menghentikan dengan pesan yang lebih informatif jika ada yang mencoba INSERT selain ID 1.
        -- Untuk tujuan perbaikan error import, kita biarkan saja UPDATE dan tidak SIGNA.
        -- Jika Anda ingin menghentikan INSERT dan memberitahu user bahwa sudah diupdate, aktifkan baris di bawah:
        -- SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Status updated instead of inserted, as only one status row is allowed.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `toko_status_backup`
--

DROP TABLE IF EXISTS `toko_status_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `toko_status_backup` (
  `id` int NOT NULL DEFAULT '0',
  `status` enum('buka','tutup') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'buka',
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `toko_status_backup`
--

LOCK TABLES `toko_status_backup` WRITE;
/*!40000 ALTER TABLE `toko_status_backup` DISABLE KEYS */;
INSERT INTO `toko_status_backup` VALUES (1,'tutup','2025-06-19 07:54:53'),(24,'buka','2025-04-28 20:09:32'),(25,'tutup','2025-04-28 20:48:23'),(26,'buka','2025-04-28 20:49:41'),(27,'tutup','2025-04-28 22:07:27'),(28,'buka','2025-04-28 22:08:13'),(29,'tutup','2025-04-29 12:34:56'),(30,'buka','2025-04-29 12:34:57'),(31,'tutup','2025-04-30 17:43:38'),(32,'buka','2025-04-30 17:43:43'),(33,'tutup','2025-04-30 17:49:18'),(34,'buka','2025-04-30 17:49:19'),(35,'tutup','2025-05-10 21:07:53'),(36,'buka','2025-05-10 21:08:03');
/*!40000 ALTER TABLE `toko_status_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_bln`
--

DROP TABLE IF EXISTS `transaksi_bln`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaksi_bln` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tgl` date NOT NULL,
  `pemasukan` decimal(10,2) NOT NULL,
  `total_bulanan` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_bln`
--

LOCK TABLES `transaksi_bln` WRITE;
/*!40000 ALTER TABLE `transaksi_bln` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaksi_bln` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'zeea-laundry'
--
/*!50003 DROP FUNCTION IF EXISTS `generate_tracking_code` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_tracking_code`() RETURNS varchar(10) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
    READS SQL DATA
BEGIN
    DECLARE new_code VARCHAR(10);
    DECLARE code_exists INT;

    SET new_code = CONCAT(
        CHAR(65 + FLOOR(RAND() * 26)),
        CHAR(65 + FLOOR(RAND() * 26)),
        LPAD(FLOOR(RAND() * 10000), 4, '0')
    );

    SELECT COUNT(*) INTO code_exists FROM pesanan WHERE tracking_code = new_code;

    IF code_exists > 0 THEN
        RETURN generate_tracking_code();
    END IF;

    RETURN new_code;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-30 17:15:01
