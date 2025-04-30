-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 22 Mar 2025 pada 23.05
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
-- Database: `zeea_laundry`
--

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
(1, 'destiowahyu', '$2y$10$Q3BmPpwmYS0NnPM8ZXWMGeoLhUoN6zgKO2jyQAnfPHDVigqZzf/ai', 'admin_67df1b8bc5d1c.jpeg');

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
  `waktu_jemput` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(16, 'Setrika Saja', 4500.00, 'Paket khusus pelanggan yang tidak ingin repot menyetrika. Paket ini hanya melayani setrika saja tanpa proses mencuci', 'icon_67dc8637de937.png');

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
(6, '+628546465224', 'adad'),
(7, '+6213801930012', 'Azzam Wisam'),
(8, '+62820193019309', 'Novita Khoirunnisa'),
(9, '+6287546597211', 'Siti Fadhillah'),
(10, '+6285232252572', 'Deni Kurniawan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_paket` int(11) NOT NULL,
  `berat` decimal(5,2) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `status` enum('diproses','selesai','dibatalkan') NOT NULL DEFAULT 'diproses',
  `status_pembayaran` enum('belum_dibayar','sudah_dibayar') NOT NULL DEFAULT 'belum_dibayar',
  `waktu` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id`, `id_pelanggan`, `id_paket`, `berat`, `harga`, `status`, `status_pembayaran`, `waktu`) VALUES
(1, 6, 14, 1.00, 6500.00, 'selesai', 'sudah_dibayar', '2025-03-21 08:51:56'),
(2, 7, 14, 1.50, 9750.00, 'selesai', 'belum_dibayar', '2025-03-21 09:32:37'),
(3, 7, 14, 1.50, 9750.00, 'selesai', 'belum_dibayar', '2025-03-21 09:38:12'),
(4, 7, 14, 2.50, 16250.00, 'selesai', 'belum_dibayar', '2025-03-21 09:59:29'),
(5, 7, 15, 1.00, 4500.00, 'selesai', 'belum_dibayar', '2025-03-21 10:01:30'),
(6, 7, 14, 1.00, 6500.00, 'selesai', 'belum_dibayar', '2025-03-21 10:08:14'),
(7, 7, 14, 1.00, 6500.00, 'diproses', 'belum_dibayar', '2025-03-21 13:48:39'),
(8, 8, 14, 2.50, 16250.00, 'diproses', 'belum_dibayar', '2025-03-21 13:54:11'),
(9, 8, 14, 1.50, 9750.00, 'diproses', 'belum_dibayar', '2025-03-21 13:54:36'),
(10, 8, 14, 1.50, 9750.00, 'diproses', 'belum_dibayar', '2025-03-21 14:02:14'),
(11, 8, 15, 2.50, 11250.00, 'diproses', 'belum_dibayar', '2025-03-21 14:03:08'),
(12, 8, 14, 2.90, 18850.00, 'selesai', 'belum_dibayar', '2025-03-21 14:25:49'),
(13, 9, 15, 10.40, 46800.00, 'diproses', 'belum_dibayar', '2025-03-21 14:27:36'),
(14, 8, 14, 6.70, 43550.00, 'selesai', 'sudah_dibayar', '2025-03-21 20:57:47'),
(15, 10, 15, 5.40, 24300.00, 'diproses', 'belum_dibayar', '2025-03-21 22:39:56');

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
(1, 14, '2025-03-21', 43550.00);

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
  ADD KEY `id_paket` (`id_paket`);

--
-- Indeks untuk tabel `riwayat`
--
ALTER TABLE `riwayat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `paket`
--
ALTER TABLE `paket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `riwayat`
--
ALTER TABLE `riwayat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
