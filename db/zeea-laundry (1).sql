-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 19 Jun 2025 pada 16.16
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

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
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_tracking_code` () RETURNS VARCHAR(10) CHARSET utf8mb4 COLLATE utf8mb4_general_ci  BEGIN
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
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `foto_profil`) VALUES
(1, 'destiowahyu', '$2y$10$/qPUi/YPKktBtttvMxvBFOn2.DJSC0AzTWdP5Ivvf3f.xoFBNCBlS', 'admin_67f8bb1b00679.jpeg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `antar_jemput`
--

CREATE TABLE `antar_jemput` (
  `id` int(11) NOT NULL,
  `id_pesanan` int(11) DEFAULT NULL,
  `layanan` enum('antar','jemput','antar-jemput') NOT NULL,
  `alamat_antar` text DEFAULT NULL,
  `alamat_jemput` text DEFAULT NULL,
  `status` enum('menunggu','dalam perjalanan','selesai') NOT NULL DEFAULT 'menunggu',
  `waktu_antar` datetime DEFAULT NULL,
  `waktu_jemput` datetime DEFAULT NULL,
  `harga_custom` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `antar_jemput`
--

INSERT INTO `antar_jemput` (`id`, `id_pesanan`, `layanan`, `alamat_antar`, `alamat_jemput`, `status`, `waktu_antar`, `waktu_jemput`, `harga_custom`) VALUES
(1, 38, 'antar', 'Desa Padaran Dukuh Jambangan RT 05 RW 04', NULL, 'menunggu', '2025-04-28 13:00:00', NULL, NULL),
(2, 44, 'antar', 'Ds Padaran', NULL, 'menunggu', '2025-05-02 15:00:00', NULL, NULL),
(3, 44, 'antar', 'sfsf', NULL, 'menunggu', '2025-05-02 15:03:00', NULL, 0.00),
(4, 45, 'antar', 'gina gina', NULL, 'selesai', '2025-05-02 15:47:00', NULL, NULL),
(5, NULL, 'jemput', NULL, 'Jalan Abadi No 12, Padaran', 'menunggu', NULL, '2025-06-20 10:00:00', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `paket`
--

CREATE TABLE `paket` (
  `id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `icon` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `paket`
--

INSERT INTO `paket` (`id`, `nama`, `harga`, `keterangan`, `icon`) VALUES
(14, 'Cuci Setrika', 6500.00, 'Paket ini adalah paket lengkap. Setelah pakaian dicuci bersih, semua pakaian juga akan disetrika sehingga rapi saat sampai di tangan pelanggan', 'icon_67dc86254cde9.png'),
(15, 'Cuci Kering', 4500.00, 'Paket ini hanya melayani cuci saja sampai pakaian kering tanpa disetrika', 'icon_67df0951ac033.png'),
(16, 'Setrika Saja', 4500.00, 'Paket khusus pelanggan yang tidak ingin repot menyetrika. Paket ini hanya melayani setrika saja tanpa proses mencuci', 'icon_67dc8637de937.png'),
(21, 'Paket Khusus', 0.00, 'Paket dengan harga kustom', 'custom.png');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL,
  `no_hp` varchar(15) NOT NULL,
  `nama` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggan`
--

INSERT INTO `pelanggan` (`id`, `no_hp`, `nama`) VALUES
(6, '+628546465224', 'Husna Nur Alamin'),
(7, '+6285955196688', 'Azzam Wisam Muafa Laniofa'),
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
(21, '+62854213456789', 'Umam');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(20) DEFAULT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_paket` int(11) NOT NULL,
  `berat` decimal(5,2) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `status` enum('diproses','selesai','dibatalkan') NOT NULL DEFAULT 'diproses',
  `status_pembayaran` enum('belum_dibayar','sudah_dibayar') NOT NULL DEFAULT 'belum_dibayar',
  `waktu` datetime NOT NULL DEFAULT current_timestamp(),
  `harga_custom` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id`, `tracking_code`, `id_pelanggan`, `id_paket`, `berat`, `harga`, `status`, `status_pembayaran`, `waktu`, `harga_custom`) VALUES
(1, 'ZL250321006e33', 6, 14, 1.00, 6500.00, 'selesai', 'sudah_dibayar', '2025-03-21 08:51:56', 0.00),
(2, 'ZL250321007ab0', 7, 14, 1.50, 9750.00, 'selesai', 'belum_dibayar', '2025-03-21 09:32:37', 0.00),
(3, 'ZL2503210070c2', 7, 14, 1.50, 9750.00, 'selesai', 'belum_dibayar', '2025-03-21 09:38:12', 0.00),
(4, 'ZL2503210073b6', 7, 14, 2.50, 16250.00, 'selesai', 'belum_dibayar', '2025-03-21 09:59:29', 0.00),
(5, 'ZL2503210071a9', 7, 15, 1.00, 4500.00, 'selesai', 'belum_dibayar', '2025-03-21 10:01:30', 0.00),
(6, 'ZL250321007a24', 7, 14, 1.00, 6500.00, 'selesai', 'belum_dibayar', '2025-03-21 10:08:14', 0.00),
(7, 'ZL25032100759d', 7, 14, 1.00, 6500.00, 'diproses', 'belum_dibayar', '2025-03-21 13:48:39', 0.00),
(8, 'ZL2503210080b2', 8, 14, 2.50, 16250.00, 'selesai', 'sudah_dibayar', '2025-03-21 13:54:11', 0.00),
(9, 'ZL2503210080b2', 8, 14, 1.50, 9750.00, 'selesai', 'sudah_dibayar', '2025-03-21 13:54:36', 0.00),
(10, 'ZL250321008832', 8, 14, 1.50, 9750.00, 'diproses', 'belum_dibayar', '2025-03-21 14:02:14', 0.00),
(11, 'ZL25032100885d', 8, 15, 2.50, 11250.00, 'diproses', 'belum_dibayar', '2025-03-21 14:03:08', 0.00),
(12, 'ZL2503210085b0', 8, 14, 2.90, 18850.00, 'selesai', 'belum_dibayar', '2025-03-21 14:25:49', 0.00),
(13, 'ZL250321009f9d', 9, 15, 10.40, 46800.00, 'diproses', 'belum_dibayar', '2025-03-21 14:27:36', 0.00),
(14, 'ZL2503210089b0', 8, 14, 6.70, 43550.00, 'selesai', 'sudah_dibayar', '2025-03-21 20:57:47', 0.00),
(15, 'ZL250321010f67', 10, 15, 5.40, 24300.00, 'diproses', 'belum_dibayar', '2025-03-21 22:39:56', 0.00),
(16, 'ZL250323007e56', 7, 14, 10.60, 68900.00, 'selesai', 'sudah_dibayar', '2025-03-23 14:48:52', 0.00),
(19, 'ZL250324007c7a', 7, 14, 2.60, 16900.00, 'selesai', 'sudah_dibayar', '2025-03-24 02:25:26', 0.00),
(20, 'ZL250324007c7a', 7, 15, 1.04, 4680.00, 'diproses', 'belum_dibayar', '2025-03-24 02:25:26', 0.00),
(29, 'ZL250324007337', 7, 16, 30.00, 135000.00, 'diproses', 'belum_dibayar', '2025-03-24 14:37:56', 0.00),
(30, 'ZL2503240135d3', 13, 14, 12.00, 96000.00, 'selesai', 'sudah_dibayar', '2025-03-24 14:38:40', 8000.00),
(31, 'ZL250324013aab', 13, 14, 15.00, 180000.00, 'diproses', 'belum_dibayar', '2025-03-24 14:39:10', 12000.00),
(32, 'ZL250324013aab', 13, 15, 1.00, 4500.00, 'diproses', 'belum_dibayar', '2025-03-24 14:39:56', 0.00),
(33, 'ZL250324013919', 13, 14, 1.00, 10000.00, 'diproses', 'belum_dibayar', '2025-03-24 14:40:14', 10000.00),
(34, 'ZL250324007037', 7, 21, 1.00, 3000.00, 'selesai', 'sudah_dibayar', '2025-03-24 14:53:54', 3000.00),
(35, 'ZL2503240074b7', 7, 15, 6.00, 27000.00, 'diproses', 'belum_dibayar', '2025-03-24 14:57:25', 0.00),
(36, 'ZL2503240143d8', 14, 14, 20.00, 130000.00, 'diproses', 'belum_dibayar', '2025-03-24 14:57:50', 0.00),
(37, 'ZL250324014bde', 14, 21, 2.00, 20000.00, 'selesai', 'sudah_dibayar', '2025-03-24 14:58:39', 10000.00),
(38, 'ZL250324007d24', 7, 14, 1.00, 6500.00, 'selesai', 'sudah_dibayar', '2025-03-24 15:02:45', 0.00),
(39, 'ZL250324007d24', 7, 16, 2.00, 9000.00, 'selesai', 'sudah_dibayar', '2025-03-24 15:02:45', 0.00),
(40, 'ZL25032401388c', 13, 16, 1.00, 4500.00, 'selesai', 'sudah_dibayar', '2025-03-24 15:05:06', 0.00),
(41, 'ZL25032401388c', 13, 21, 2.00, 14000.00, 'selesai', 'sudah_dibayar', '2025-03-24 15:05:06', 7000.00),
(42, 'ZL25032401582a', 15, 14, 2.50, 16250.00, 'selesai', 'sudah_dibayar', '2025-03-24 19:55:01', 0.00),
(43, 'ZL2503240150c5', 15, 15, 4.60, 20700.00, 'selesai', 'sudah_dibayar', '2025-03-24 19:56:11', 0.00),
(44, 'ZL250424007dc7', 7, 14, 1.00, 6500.00, 'selesai', 'belum_dibayar', '2025-04-24 20:06:15', 0.00),
(45, 'ZL2504280157d9', 15, 14, 1.70, 11050.00, 'selesai', 'sudah_dibayar', '2025-04-28 16:56:13', 0.00),
(46, 'ZL250502009VBB', 9, 15, 5.60, 25200.00, 'diproses', 'belum_dibayar', '2025-05-02 14:29:41', 0.00),
(47, 'ZL250502009VBB', 9, 16, 1.40, 6300.00, 'diproses', 'belum_dibayar', '2025-05-02 14:29:41', 0.00),
(48, 'ZL2505020165EH', 16, 14, 9.50, 61750.00, 'selesai', 'sudah_dibayar', '2025-05-02 15:14:35', 0.00),
(49, 'ZL250503017CCS', 17, 14, 3.50, 22750.00, 'selesai', 'belum_dibayar', '2025-05-03 22:23:38', 0.00),
(50, 'ZL250510009JZ5', 9, 14, 12.00, 78000.00, 'diproses', 'belum_dibayar', '2025-05-10 20:43:08', 0.00),
(51, 'ZL250604015C3L', 15, 15, 1.03, 4635.00, 'diproses', 'belum_dibayar', '2025-06-04 02:01:02', 0.00),
(52, 'ZL250619018XZX', 18, 14, 2.54, 16510.00, 'selesai', 'sudah_dibayar', '2025-06-19 09:16:12', 0.00),
(53, 'ZL250619019ZOC', 19, 15, 3.21, 14445.00, 'selesai', 'sudah_dibayar', '2025-06-19 09:17:24', 0.00),
(54, 'ZL250619020YW2', 20, 14, 9.21, 59865.00, 'dibatalkan', 'belum_dibayar', '2025-06-19 09:18:51', 0.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat`
--

CREATE TABLE `riwayat` (
  `id` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `tgl_selesai` date NOT NULL,
  `harga` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `riwayat`
--

INSERT INTO `riwayat` (`id`, `id_pesanan`, `tgl_selesai`, `harga`) VALUES
(1, 14, '2025-03-21', 43550.00),
(2, 16, '2025-03-23', 68900.00),
(3, 19, '2025-03-24', 16900.00),
(5, 40, '2025-03-24', 4500.00),
(6, 38, '2025-03-24', 6500.00),
(7, 39, '2025-03-24', 9000.00),
(8, 37, '2025-03-24', 20000.00),
(9, 41, '2025-03-24', 14000.00),
(10, 34, '2025-03-24', 3000.00),
(11, 43, '2025-03-24', 20700.00),
(12, 30, '2025-04-11', 96000.00),
(13, 45, '2025-04-28', 11050.00),
(14, 44, '2025-04-30', 6500.00),
(15, 42, '2025-04-30', 16250.00),
(16, 49, '2025-05-03', 22750.00),
(17, 48, '2025-05-10', 61750.00),
(18, 52, '2025-06-19', 16510.00),
(19, 53, '2025-06-19', 14445.00),
(20, 8, '2025-06-19', 16250.00),
(21, 9, '2025-06-19', 9750.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'antar_jemput_active', 'active', '2025-05-02 08:59:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `toko_status`
--

CREATE TABLE `toko_status` (
  `id` int(11) NOT NULL,
  `status` enum('buka','tutup') NOT NULL DEFAULT 'buka',
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `toko_status`
--

INSERT INTO `toko_status` (`id`, `status`, `waktu`) VALUES
(1, 'buka', '2025-06-19 21:12:04');

--
-- Trigger `toko_status`
--
DELIMITER $$
CREATE TRIGGER `prevent_multiple_status_insert` BEFORE INSERT ON `toko_status` FOR EACH ROW BEGIN
    
    IF (SELECT COUNT(*) FROM toko_status) > 0 THEN
        UPDATE toko_status SET 
            status = NEW.status, 
            waktu = NEW.waktu 
        WHERE id = 1;
        
        
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Status updated instead of inserted';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `toko_status_backup`
--

CREATE TABLE `toko_status_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `status` enum('buka','tutup') NOT NULL DEFAULT 'buka',
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `toko_status_backup`
--

INSERT INTO `toko_status_backup` (`id`, `status`, `waktu`) VALUES
(1, 'tutup', '2025-06-19 07:54:53'),
(24, 'buka', '2025-04-28 20:09:32'),
(25, 'tutup', '2025-04-28 20:48:23'),
(26, 'buka', '2025-04-28 20:49:41'),
(27, 'tutup', '2025-04-28 22:07:27'),
(28, 'buka', '2025-04-28 22:08:13'),
(29, 'tutup', '2025-04-29 12:34:56'),
(30, 'buka', '2025-04-29 12:34:57'),
(31, 'tutup', '2025-04-30 17:43:38'),
(32, 'buka', '2025-04-30 17:43:43'),
(33, 'tutup', '2025-04-30 17:49:18'),
(34, 'buka', '2025-04-30 17:49:19'),
(35, 'tutup', '2025-05-10 21:07:53'),
(36, 'buka', '2025-05-10 21:08:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_bln`
--

CREATE TABLE `transaksi_bln` (
  `id` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `pemasukan` decimal(10,2) NOT NULL,
  `total_bulanan` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `antar_jemput`
--
ALTER TABLE `antar_jemput`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `paket`
--
ALTER TABLE `paket`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_hp` (`no_hp`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `id_paket` (`id_paket`),
  ADD KEY `idx_tracking_code` (`tracking_code`);

--
-- Indeks untuk tabel `riwayat`
--
ALTER TABLE `riwayat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indeks untuk tabel `toko_status`
--
ALTER TABLE `toko_status`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `transaksi_bln`
--
ALTER TABLE `transaksi_bln`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `antar_jemput`
--
ALTER TABLE `antar_jemput`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `paket`
--
ALTER TABLE `paket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT untuk tabel `riwayat`
--
ALTER TABLE `riwayat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT untuk tabel `toko_status`
--
ALTER TABLE `toko_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `transaksi_bln`
--
ALTER TABLE `transaksi_bln`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `antar_jemput`
--
ALTER TABLE `antar_jemput`
  ADD CONSTRAINT `antar_jemput_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`id_paket`) REFERENCES `paket` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat`
--
ALTER TABLE `riwayat`
  ADD CONSTRAINT `riwayat_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
