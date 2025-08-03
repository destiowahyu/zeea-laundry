-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 03, 2025 at 08:36 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zeea-laundry`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_tracking_code` () RETURNS VARCHAR(10) CHARSET utf8mb4 COLLATE utf8mb4_general_ci READS SQL DATA BEGIN
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `foto_profil`) VALUES
(1, 'destiowahyu', '$2y$10$/qPUi/YPKktBtttvMxvBFOn2.DJSC0AzTWdP5Ivvf3f.xoFBNCBlS', 'admin_688f1f60df80d.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `antarjemput_status`
--

CREATE TABLE `antarjemput_status` (
  `id` int NOT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `antarjemput_status`
--

INSERT INTO `antarjemput_status` (`id`, `status`, `updated_at`, `updated_by`) VALUES
(1, 'active', '2025-06-30 18:21:05', 'destiowahyu');

-- --------------------------------------------------------

--
-- Table structure for table `antar_jemput`
--

CREATE TABLE `antar_jemput` (
  `id` int NOT NULL,
  `id_pesanan` int DEFAULT NULL,
  `tracking_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pelanggan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_hp` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_pelanggan` int DEFAULT NULL,
  `layanan` enum('antar','jemput','antar-jemput') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_antar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `alamat_jemput` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` enum('menunggu','dalam perjalanan','selesai') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'menunggu',
  `selesai_at` datetime DEFAULT NULL,
  `waktu_antar` datetime DEFAULT NULL,
  `waktu_jemput` datetime DEFAULT NULL,
  `harga` decimal(10,2) NOT NULL DEFAULT '5000.00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status_pembayaran` enum('belum_dibayar','sudah_dibayar') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'belum_dibayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `antar_jemput`
--

INSERT INTO `antar_jemput` (`id`, `id_pesanan`, `tracking_code`, `nama_pelanggan`, `no_hp`, `id_pelanggan`, `layanan`, `alamat_antar`, `alamat_jemput`, `status`, `selesai_at`, `waktu_antar`, `waktu_jemput`, `harga`, `deleted_at`, `status_pembayaran`) VALUES
(1, 38, 'ZL250324007d24', 'Azzam Wisam Muafa Laniofa', '+6285955196688', 7, 'antar', 'Desa Padaran Dukuh Jambangan RT 05 RW 04', NULL, 'menunggu', NULL, '2025-04-28 13:00:00', NULL, '5000.00', NULL, 'belum_dibayar'),
(2, 44, 'ZL250424007dc7', 'Azzam Wisam Muafa Laniofa', '+6285955196688', 7, 'antar', 'Ds Padaran', NULL, 'menunggu', NULL, '2025-05-02 15:00:00', NULL, '5000.00', NULL, 'belum_dibayar'),
(3, 44, 'ZL250424007dc7', 'Azzam Wisam Muafa Laniofa', '+6285955196688', 7, 'antar', 'sfsf', NULL, 'dalam perjalanan', NULL, '2025-05-02 15:03:00', NULL, '5000.00', NULL, 'belum_dibayar'),
(4, 45, 'ZL2504280157d9', 'Gina Morissa Fenia', '+6285766655178', 15, 'antar', 'gina gina', NULL, 'selesai', '2025-05-02 15:47:00', '2025-05-02 15:47:00', NULL, '5000.00', NULL, 'sudah_dibayar'),
(5, NULL, NULL, NULL, NULL, NULL, 'jemput', NULL, 'Jalan Abadi No 12, Padaran', 'selesai', '2025-06-20 10:00:00', NULL, '2025-06-20 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(6, NULL, NULL, 'Sempurna', NULL, 22, 'jemput', NULL, 'Jalan Kedungmundu', 'selesai', '2025-06-25 00:39:00', NULL, '2025-06-25 00:39:00', '5000.00', NULL, 'sudah_dibayar'),
(7, NULL, NULL, 'Hindia Belanda', NULL, 23, 'jemput', NULL, 'Jalan KH. Mansyur No 10', 'dalam perjalanan', NULL, NULL, '2025-06-26 10:00:00', '5000.00', NULL, 'belum_dibayar'),
(8, 61, NULL, 'Destio Wahyu', NULL, 24, 'antar', 'Padaran', NULL, 'selesai', '2025-07-01 01:01:00', '2025-07-01 01:01:00', NULL, '5000.00', NULL, 'sudah_dibayar'),
(9, 61, NULL, 'Destio Wahyu', NULL, 24, 'antar', 'Jalan Setia Budi', NULL, 'menunggu', NULL, '2025-07-01 01:25:00', NULL, '5000.00', '2025-06-30 17:50:07', 'belum_dibayar'),
(10, 61, NULL, 'Destio Wahyu', NULL, 24, 'antar', 'Jalan Bahagia', NULL, 'selesai', '2025-07-01 01:30:00', '2025-07-01 01:30:00', NULL, '5000.00', '2025-06-30 18:04:10', 'sudah_dibayar'),
(11, 63, NULL, 'Destio Wahyu', NULL, 24, 'antar', 'Jalan Imam Bonjol no 207', NULL, 'menunggu', NULL, '2025-07-01 01:51:00', NULL, '5000.00', '2025-06-30 18:00:05', 'belum_dibayar'),
(12, 63, 'ZL250701024TT3', 'Destio Wahyu', '+6285929095672', 24, 'antar', 'Jalan Abaaaaa', NULL, 'selesai', '2025-07-01 02:00:00', '2025-07-01 02:00:00', NULL, '5000.00', '2025-06-30 18:01:33', 'sudah_dibayar'),
(13, 63, 'ZL250701024TT3', 'Destio Wahyu', '+6285929095672', 24, 'antar', 'Jalan Bahagia No 10', NULL, 'menunggu', NULL, '2025-07-01 02:01:00', NULL, '5000.00', '2025-06-30 18:04:21', 'belum_dibayar'),
(14, 63, 'ZL250701024TT3', 'Destio Wahyu', '+6285929095672', 24, 'antar', 'Jalan Bahagia No 211', NULL, 'dalam perjalanan', NULL, '2025-07-01 02:04:00', NULL, '5000.00', NULL, 'sudah_dibayar'),
(15, NULL, NULL, 'Destio Wahyu', NULL, 24, 'jemput', NULL, 'Padaran', 'menunggu', NULL, NULL, '2025-07-01 02:31:00', '5000.00', '2025-06-30 18:31:29', 'belum_dibayar'),
(16, 64, 'ZL25070100776W', 'Azzam Wisam Muafa Laniofa', '+6285600607791', 7, 'antar', 'Jalan Pemuda', NULL, 'dalam perjalanan', NULL, '2025-07-01 15:27:00', NULL, '5000.00', '2025-07-01 14:15:37', 'belum_dibayar'),
(17, 65, 'ZL2507010234MH', 'Junior Robert', '+62878271872817', 23, 'antar', 'Jalan Bergendi', NULL, 'dalam perjalanan', NULL, '2025-07-01 15:57:00', NULL, '5000.00', NULL, 'belum_dibayar'),
(18, 66, 'ZL2507010101M4', 'Deni Kurniawan', '+6285232252572', 10, 'antar', 'Jalan jalan', NULL, 'dalam perjalanan', NULL, '2025-07-02 10:00:00', NULL, '5000.00', '2025-07-01 13:29:06', 'belum_dibayar'),
(19, NULL, 'AJ-000019', 'William Both', NULL, 24, 'jemput', NULL, 'Jalan Pandaran No 153', 'selesai', '2025-07-03 07:00:00', NULL, '2025-07-03 07:00:00', '5000.00', NULL, 'sudah_dibayar'),
(20, 74, 'ZL250710025XRE', 'Kurniawan Nur Pratama', '+6285726314581', 25, 'antar', 'jl ewew 123', NULL, 'menunggu', NULL, '2025-07-10 15:26:00', NULL, '5000.00', '2025-07-10 07:45:41', 'belum_dibayar'),
(21, NULL, 'AJ-000021', 'WAWAN', NULL, NULL, 'jemput', NULL, 'JL BANDENG 123', 'selesai', '2025-07-17 10:00:00', NULL, '2025-07-17 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(22, NULL, 'AJ-000022', 'Nailatu Saidah', NULL, 30, 'jemput', NULL, 'Padaran RT 05 RW 04', 'selesai', '2025-07-10 15:48:00', NULL, '2025-07-10 15:48:00', '5000.00', NULL, 'sudah_dibayar'),
(23, NULL, 'AJ-000023', 'Nini Bobo', NULL, 31, 'jemput', NULL, 'Jalan Kyai Abdul Saleh', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(24, NULL, 'AJ-000024', 'Nadia', NULL, 32, 'jemput', NULL, 'Jalan Oke', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(25, NULL, 'AJ-000025', 'Hendrawan', NULL, 33, 'jemput', NULL, 'Jalan Oke', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(26, NULL, 'AJ-000026', 'Justin', NULL, 34, 'jemput', NULL, 'Jalan Jalan', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '7000.00', NULL, 'sudah_dibayar'),
(27, NULL, 'AJ-000027', 'Bobo', NULL, 35, 'jemput', NULL, 'Jalan Gagak', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(28, NULL, 'AJ-000028', 'Kunti', NULL, 36, 'jemput', NULL, 'Jalan Ini', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(29, NULL, 'AJ-000029', 'Jason', NULL, 37, 'jemput', NULL, 'Lasem', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(30, NULL, 'AJ-000030', 'Nimas', NULL, 38, 'jemput', NULL, 'Jalan Bukit', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(31, NULL, 'AJ-000031', 'Gugus', NULL, 39, 'jemput', NULL, 'sfsfs', 'selesai', '2025-07-11 10:00:00', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(32, NULL, 'AJ-000032', 'asa', NULL, 40, 'jemput', NULL, '21212', 'menunggu', NULL, NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'belum_dibayar'),
(33, NULL, 'AJ-000033', 'Hijaaa', NULL, 41, 'jemput', NULL, 'Padaran', 'selesai', '2025-07-10 17:47:49', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(34, NULL, 'AJ-000034', 'ada', NULL, 42, 'jemput', NULL, 'adf', 'selesai', '2025-07-10 17:53:39', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(35, NULL, 'AJ-000035', 'jeje', NULL, 43, 'jemput', NULL, 'ini alamat', 'selesai', '2025-07-10 17:59:04', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar'),
(36, NULL, 'AJ-000036', 'Hasna', NULL, 44, 'jemput', NULL, 'Jalan Oke', 'selesai', '2025-07-10 18:07:50', NULL, '2025-07-11 10:00:00', '5000.00', NULL, 'sudah_dibayar');

-- --------------------------------------------------------

--
-- Table structure for table `paket`
--

CREATE TABLE `paket` (
  `id` int NOT NULL,
  `nama` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paket`
--

INSERT INTO `paket` (`id`, `nama`, `harga`, `keterangan`, `icon`) VALUES
(14, 'Cuci Setrika', '6500.00', 'Paket ini adalah paket lengkap. Setelah pakaian dicuci bersih, semua pakaian juga akan disetrika sehingga rapi saat sampai di tangan pelanggan', 'icon_67dc86254cde9.png'),
(15, 'Cuci Kering', '4500.00', 'Paket ini hanya melayani cuci saja sampai pakaian kering tanpa disetrika', 'icon_67df0951ac033.png'),
(16, 'Setrika Saja', '4500.00', 'Paket khusus pelanggan yang tidak ingin repot menyetrika. Paket ini hanya melayani setrika saja tanpa proses mencuci', 'icon_67dc8637de937.png'),
(21, 'Paket Khusus', '0.00', 'Paket dengan harga kustom', 'custom.png');

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int NOT NULL,
  `no_hp` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`id`, `no_hp`, `nama`) VALUES
(6, '+628546465224', 'Husna Nur Alamin'),
(7, '+6285600607791', 'Azzam Wisam Muafa Laniofa'),
(8, '+62820193019309', 'Novita Khoirunnisa'),
(9, '+6287546597211', 'Siti Fadhillah'),
(10, '+6285232252572', 'Deni Kurniawan'),
(11, '+6285112631998', 'Nabila Husna Putri'),
(12, '+6285654110520', 'Andini Cantika Putri'),
(13, '+6285929096633', 'Juicy Lucy'),
(14, '+6281325445652', 'Fabio Asher'),
(15, '+6285766655178', 'Gina Morissa Fenia'),
(16, '+6285136578955', 'Shafira Tinaria Azzahra'),
(17, '+62892784998392', 'Kinan Aira Dina'),
(18, '+6285456255879', 'Denny Setiawan'),
(19, '+6287546985111', 'Umay Tresna'),
(20, '+6285456789456', 'Jefri Nichol'),
(21, '+62854213456789', 'Umam'),
(22, '+6289381978913', 'Sempurna'),
(23, '+62878271872817', 'Junior Robert'),
(24, '+6285929095672', 'William Both'),
(25, '+6285726314581', 'Kurniawan Nur Pratama'),
(26, '+6281293998172', 'Fenia Arfatiyah'),
(27, '+62812928309499', 'Yuliana Nur'),
(28, '+62867887578578', 'Yanto'),
(30, '+6282918298197', 'Nailatu Saidah'),
(31, '+6289209489282', 'Nini Bobo'),
(32, '+6289834298232', 'Nadia'),
(33, '+62980239802983', 'Hendrawan'),
(34, '+62891898393109', 'Justin'),
(35, '+628389198329', 'Bobo'),
(36, '+6289127387182', 'Kunti'),
(37, '+6283289738278', 'Jason'),
(38, '+6283982938928', 'Nimas'),
(39, '+62839289329783', 'Gugus'),
(40, '1122', 'asa'),
(41, '+622908249289', 'Hijaaa'),
(42, '33121354', 'ada'),
(43, '+6298209480298', 'jeje'),
(44, '+6292850298245', 'Hasna');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int NOT NULL,
  `tracking_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_pelanggan` int NOT NULL,
  `id_paket` int NOT NULL,
  `berat` decimal(5,2) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `status` enum('diproses','selesai','dibatalkan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'diproses',
  `status_pembayaran` enum('belum_dibayar','sudah_dibayar') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'belum_dibayar',
  `waktu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `harga_custom` decimal(10,2) DEFAULT '0.00',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `tracking_code`, `id_pelanggan`, `id_paket`, `berat`, `harga`, `status`, `status_pembayaran`, `waktu`, `harga_custom`, `deleted_at`) VALUES
(1, 'ZL250321006e33', 6, 14, '1.00', '6500.00', 'selesai', 'sudah_dibayar', '2025-03-21 08:51:56', '0.00', NULL),
(2, 'ZL250321007ab0', 7, 14, '1.50', '9750.00', 'selesai', 'belum_dibayar', '2025-03-21 09:32:37', '0.00', NULL),
(3, 'ZL2503210070c2', 7, 14, '1.50', '9750.00', 'selesai', 'belum_dibayar', '2025-03-21 09:38:12', '0.00', NULL),
(4, 'ZL2503210073b6', 7, 14, '2.50', '16250.00', 'selesai', 'belum_dibayar', '2025-03-21 09:59:29', '0.00', NULL),
(5, 'ZL2503210071a9', 7, 15, '1.00', '4500.00', 'selesai', 'belum_dibayar', '2025-03-21 10:01:30', '0.00', NULL),
(6, 'ZL250321007a24', 7, 14, '1.00', '6500.00', 'selesai', 'belum_dibayar', '2025-03-21 10:08:14', '0.00', NULL),
(7, 'ZL25032100759d', 7, 14, '1.00', '6500.00', 'diproses', 'belum_dibayar', '2025-03-21 13:48:39', '0.00', NULL),
(8, 'ZL2503210080b2', 8, 14, '2.50', '16250.00', 'selesai', 'sudah_dibayar', '2025-03-21 13:54:11', '0.00', NULL),
(9, 'ZL2503210080b2', 8, 14, '1.50', '9750.00', 'selesai', 'sudah_dibayar', '2025-03-21 13:54:36', '0.00', NULL),
(10, 'ZL250321008832', 8, 14, '1.50', '9750.00', 'diproses', 'belum_dibayar', '2025-03-21 14:02:14', '0.00', NULL),
(11, 'ZL25032100885d', 8, 15, '2.50', '11250.00', 'diproses', 'belum_dibayar', '2025-03-21 14:03:08', '0.00', NULL),
(12, 'ZL2503210085b0', 8, 14, '2.90', '18850.00', 'selesai', 'belum_dibayar', '2025-03-21 14:25:49', '0.00', NULL),
(13, 'ZL250321009f9d', 9, 15, '10.40', '46800.00', 'diproses', 'belum_dibayar', '2025-03-21 14:27:36', '0.00', NULL),
(14, 'ZL2503210089b0', 8, 14, '6.70', '43550.00', 'selesai', 'sudah_dibayar', '2025-03-21 20:57:47', '0.00', NULL),
(15, 'ZL250321010f67', 10, 15, '5.40', '24300.00', 'diproses', 'belum_dibayar', '2025-03-21 22:39:56', '0.00', NULL),
(16, 'ZL250323007e56', 7, 14, '10.60', '68900.00', 'selesai', 'sudah_dibayar', '2025-03-23 14:48:52', '0.00', NULL),
(19, 'ZL250324007c7a', 7, 14, '2.60', '16900.00', 'selesai', 'sudah_dibayar', '2025-03-24 02:25:26', '0.00', NULL),
(20, 'ZL250324007c7a', 7, 15, '1.04', '4680.00', 'diproses', 'belum_dibayar', '2025-03-24 02:25:26', '0.00', NULL),
(29, 'ZL250324007337', 7, 16, '30.00', '135000.00', 'diproses', 'belum_dibayar', '2025-03-24 14:37:56', '0.00', NULL),
(30, 'ZL2503240135d3', 13, 14, '12.00', '96000.00', 'selesai', 'sudah_dibayar', '2025-03-24 14:38:40', '8000.00', NULL),
(31, 'ZL250324013aab', 13, 14, '15.00', '180000.00', 'diproses', 'belum_dibayar', '2025-03-24 14:39:10', '12000.00', NULL),
(32, 'ZL250324013aab', 13, 15, '1.00', '4500.00', 'diproses', 'belum_dibayar', '2025-03-24 14:39:56', '0.00', NULL),
(33, 'ZL250324013919', 13, 14, '1.00', '10000.00', 'diproses', 'belum_dibayar', '2025-03-24 14:40:14', '10000.00', NULL),
(34, 'ZL250324007037', 7, 21, '1.00', '3000.00', 'selesai', 'sudah_dibayar', '2025-03-24 14:53:54', '3000.00', NULL),
(35, 'ZL2503240074b7', 7, 15, '6.00', '27000.00', 'diproses', 'belum_dibayar', '2025-03-24 14:57:25', '0.00', NULL),
(36, 'ZL2503240143d8', 14, 14, '20.00', '130000.00', 'diproses', 'belum_dibayar', '2025-03-24 14:57:50', '0.00', NULL),
(37, 'ZL250324014bde', 14, 21, '2.00', '20000.00', 'selesai', 'sudah_dibayar', '2025-03-24 14:58:39', '10000.00', NULL),
(38, 'ZL250324007d24', 7, 14, '1.00', '6500.00', 'selesai', 'sudah_dibayar', '2025-03-24 15:02:45', '0.00', NULL),
(39, 'ZL250324007d24', 7, 16, '2.00', '9000.00', 'selesai', 'sudah_dibayar', '2025-03-24 15:02:45', '0.00', NULL),
(40, 'ZL25032401388c', 13, 16, '1.00', '4500.00', 'selesai', 'sudah_dibayar', '2025-03-24 15:05:06', '0.00', NULL),
(41, 'ZL25032401388c', 13, 21, '2.00', '14000.00', 'selesai', 'sudah_dibayar', '2025-03-24 15:05:06', '7000.00', NULL),
(42, 'ZL25032401582a', 15, 14, '2.50', '16250.00', 'selesai', 'sudah_dibayar', '2025-03-24 19:55:01', '0.00', NULL),
(43, 'ZL2503240150c5', 15, 15, '4.60', '20700.00', 'selesai', 'sudah_dibayar', '2025-03-24 19:56:11', '0.00', NULL),
(44, 'ZL250424007dc7', 7, 14, '1.00', '6500.00', 'selesai', 'belum_dibayar', '2025-04-24 20:06:15', '0.00', NULL),
(45, 'ZL2504280157d9', 15, 14, '1.70', '11050.00', 'selesai', 'sudah_dibayar', '2025-04-28 16:56:13', '0.00', NULL),
(46, 'ZL250502009VBB', 9, 15, '5.60', '25200.00', 'diproses', 'belum_dibayar', '2025-05-02 14:29:41', '0.00', NULL),
(47, 'ZL250502009VBB', 9, 16, '1.40', '6300.00', 'diproses', 'belum_dibayar', '2025-05-02 14:29:41', '0.00', NULL),
(48, 'ZL2505020165EH', 16, 14, '9.50', '61750.00', 'selesai', 'sudah_dibayar', '2025-05-02 15:14:35', '0.00', NULL),
(49, 'ZL250503017CCS', 17, 14, '3.50', '22750.00', 'selesai', 'belum_dibayar', '2025-05-03 22:23:38', '0.00', NULL),
(50, 'ZL250510009JZ5', 9, 14, '12.00', '78000.00', 'diproses', 'belum_dibayar', '2025-05-10 20:43:08', '0.00', NULL),
(51, 'ZL250604015C3L', 15, 15, '1.03', '4635.00', 'diproses', 'belum_dibayar', '2025-06-04 02:01:02', '0.00', NULL),
(52, 'ZL250619018XZX', 18, 14, '2.54', '16510.00', 'selesai', 'sudah_dibayar', '2025-06-19 09:16:12', '0.00', NULL),
(53, 'ZL250619019ZOC', 19, 15, '3.21', '14445.00', 'selesai', 'sudah_dibayar', '2025-06-19 09:17:24', '0.00', NULL),
(54, 'ZL250619020YW2', 20, 14, '9.21', '59865.00', 'dibatalkan', 'belum_dibayar', '2025-06-19 09:18:51', '0.00', NULL),
(55, 'ZL250625014XCL', 14, 14, '1.00', '6500.00', 'selesai', 'sudah_dibayar', '2025-06-25 00:34:49', '0.00', NULL),
(56, 'ZL250625023PQ2', 23, 14, '1.01', '6565.00', 'selesai', 'sudah_dibayar', '2025-06-25 20:23:27', '0.00', NULL),
(57, 'ZL250630010C62', 10, 14, '13.00', '84500.00', 'selesai', 'belum_dibayar', '2025-06-30 17:19:34', '0.00', NULL),
(58, 'ZL250630013HFI', 13, 16, '13.12', '59040.00', 'diproses', 'belum_dibayar', '2025-06-30 18:00:22', '0.00', NULL),
(59, 'ZL2506300072E2', 7, 14, '23.00', '149500.00', 'selesai', 'sudah_dibayar', '2025-06-30 18:22:58', '0.00', NULL),
(60, 'ZL250630024BCB', 24, 14, '21.00', '136500.00', 'selesai', 'belum_dibayar', '2025-06-30 18:51:14', '0.00', NULL),
(61, 'ZL250630024618', 24, 15, '1.00', '4500.00', 'selesai', 'sudah_dibayar', '2025-06-30 21:01:24', '0.00', NULL),
(62, 'ZL250630024618', 24, 16, '1.00', '4500.00', 'selesai', 'sudah_dibayar', '2025-06-30 21:01:24', '0.00', NULL),
(63, 'ZL250701024TT3', 24, 14, '12.00', '78000.00', 'selesai', 'sudah_dibayar', '2025-07-01 00:51:25', '0.00', NULL),
(64, 'ZL25070100776W', 7, 14, '132.00', '858000.00', 'selesai', 'belum_dibayar', '2025-07-01 14:14:06', '0.00', '2025-07-01 14:15:37'),
(65, 'ZL2507010234MH', 23, 14, '21.90', '142350.00', 'selesai', 'sudah_dibayar', '2025-07-01 14:57:08', '0.00', NULL),
(66, 'ZL2507010101M4', 10, 15, '4.30', '19350.00', 'selesai', 'belum_dibayar', '2025-07-01 16:31:52', '0.00', '2025-07-01 13:29:06'),
(67, 'ZL2507010256C5', 25, 14, '10.20', '66300.00', 'selesai', 'belum_dibayar', '2025-07-01 21:53:13', '0.00', NULL),
(68, 'ZL250701024A0X', 24, 16, '5.10', '22950.00', 'selesai', 'sudah_dibayar', '2025-07-01 21:58:52', '0.00', NULL),
(69, 'ZL250702024O0X', 24, 14, '1.00', '6500.00', 'diproses', 'sudah_dibayar', '2025-07-02 17:26:01', '0.00', NULL),
(70, 'ZL250707026ZMO', 26, 16, '12.00', '54000.00', 'diproses', 'belum_dibayar', '2025-07-07 16:03:02', '0.00', NULL),
(71, 'ZL250707015KDY', 15, 14, '5.10', '33150.00', 'selesai', 'belum_dibayar', '2025-07-07 16:03:38', '0.00', NULL),
(72, 'ZL250709027GIO', 27, 14, '2.30', '14950.00', 'diproses', 'belum_dibayar', '2025-07-09 13:59:47', '0.00', NULL),
(73, 'ZL250710028FNR', 28, 14, '600.00', '3900000.00', 'diproses', 'belum_dibayar', '2025-07-10 14:14:13', '0.00', '2025-07-10 07:14:49'),
(74, 'ZL250710025XRE', 25, 21, '50.00', '15000000.00', 'selesai', 'sudah_dibayar', '2025-07-10 14:17:41', '300000.00', '2025-07-10 07:45:41');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat`
--

CREATE TABLE `riwayat` (
  `id` int NOT NULL,
  `id_pesanan` int NOT NULL,
  `tgl_selesai` date NOT NULL,
  `harga` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riwayat`
--

INSERT INTO `riwayat` (`id`, `id_pesanan`, `tgl_selesai`, `harga`) VALUES
(1, 14, '2025-03-21', '43550.00'),
(2, 16, '2025-03-23', '68900.00'),
(3, 19, '2025-03-24', '16900.00'),
(5, 40, '2025-03-24', '4500.00'),
(6, 38, '2025-03-24', '6500.00'),
(7, 39, '2025-03-24', '9000.00'),
(8, 37, '2025-03-24', '20000.00'),
(9, 41, '2025-03-24', '14000.00'),
(10, 34, '2025-03-24', '3000.00'),
(11, 43, '2025-03-24', '20700.00'),
(12, 30, '2025-04-11', '96000.00'),
(13, 45, '2025-04-28', '11050.00'),
(14, 44, '2025-04-30', '6500.00'),
(15, 42, '2025-04-30', '16250.00'),
(16, 49, '2025-05-03', '22750.00'),
(17, 48, '2025-05-10', '61750.00'),
(18, 52, '2025-06-19', '16510.00'),
(19, 53, '2025-06-19', '14445.00'),
(20, 8, '2025-06-19', '16250.00'),
(21, 9, '2025-06-19', '9750.00'),
(22, 55, '2025-06-25', '6500.00'),
(23, 56, '2025-06-30', '6565.00'),
(24, 57, '2025-06-30', '84500.00'),
(25, 58, '2025-06-30', '59040.00'),
(26, 60, '2025-06-30', '136500.00'),
(27, 61, '2025-06-30', '4500.00'),
(28, 62, '2025-06-30', '4500.00'),
(29, 63, '2025-07-01', '78000.00'),
(30, 64, '2025-07-01', '858000.00'),
(31, 65, '2025-07-01', '142350.00'),
(32, 66, '2025-07-01', '19350.00'),
(33, 59, '2025-07-01', '149500.00'),
(34, 67, '2025-07-01', '45900.00'),
(35, 68, '2025-07-02', '22950.00'),
(36, 69, '2025-07-02', '6500.00'),
(37, 71, '2025-07-07', '33150.00'),
(38, 72, '2025-07-09', '14950.00'),
(39, 74, '2025-07-10', '15000000.00');

-- --------------------------------------------------------

--
-- Table structure for table `toko_status`
--

CREATE TABLE `toko_status` (
  `id` int NOT NULL,
  `status` enum('buka','tutup') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'buka',
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `toko_status`
--

INSERT INTO `toko_status` (`id`, `status`, `waktu`) VALUES
(1, 'buka', '2025-07-10 14:09:26');

--
-- Triggers `toko_status`
--
DELIMITER $$
CREATE TRIGGER `prevent_multiple_status_insert` BEFORE INSERT ON `toko_status` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `antarjemput_status`
--
ALTER TABLE `antarjemput_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `antar_jemput`
--
ALTER TABLE `antar_jemput`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `fk_antar_jemput_pelanggan` (`id_pelanggan`),
  ADD KEY `idx_tracking_code` (`tracking_code`),
  ADD KEY `idx_layanan_status` (`layanan`,`status`);

--
-- Indexes for table `paket`
--
ALTER TABLE `paket`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_hp` (`no_hp`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `id_paket` (`id_paket`),
  ADD KEY `idx_tracking_code` (`tracking_code`);

--
-- Indexes for table `riwayat`
--
ALTER TABLE `riwayat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indexes for table `toko_status`
--
ALTER TABLE `toko_status`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `antarjemput_status`
--
ALTER TABLE `antarjemput_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `antar_jemput`
--
ALTER TABLE `antar_jemput`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `paket`
--
ALTER TABLE `paket`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `riwayat`
--
ALTER TABLE `riwayat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `toko_status`
--
ALTER TABLE `toko_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `antar_jemput`
--
ALTER TABLE `antar_jemput`
  ADD CONSTRAINT `antar_jemput_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_antar_jemput_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`id_paket`) REFERENCES `paket` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `riwayat`
--
ALTER TABLE `riwayat`
  ADD CONSTRAINT `riwayat_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
