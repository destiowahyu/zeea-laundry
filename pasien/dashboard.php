<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienName = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienName'")->fetch_assoc();

// Ambil riwayat pendaftaran terakhir dari tabel daftar_poli
$riwayatTerakhir = $conn->query("
    SELECT dp.created_at AS tanggal_daftar, dp.keluhan, d.nama AS nama_dokter, po.nama_poli, dp.status
    FROM daftar_poli dp
    JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
    JOIN dokter d ON jp.id_dokter = d.id
    JOIN poli po ON d.id_poli = po.id
    WHERE dp.id_pasien = '{$pasienData['id']}'
    ORDER BY dp.created_at DESC
    LIMIT 1
")->fetch_assoc();

$waktuPeriksa = $conn->query("
    SELECT p.tgl_periksa
    FROM periksa p
    JOIN daftar_poli dp ON p.id_daftar_poli = dp.id
    WHERE dp.id_pasien = '{$pasienData['id']}'
    ORDER BY p.tgl_periksa DESC
    LIMIT 1
")->fetch_assoc();

// Ambil riwayat konsultasi terakhir
$konsultasiTerakhir = $conn->query("
    SELECT k.id, k.subject, k.pertanyaan, k.jawaban, k.tgl_konsultasi, d.nama AS nama_dokter
    FROM konsultasi k
    JOIN dokter d ON k.id_dokter = d.id
    WHERE k.id_pasien = '{$pasienData['id']}'
    ORDER BY k.tgl_konsultasi DESC
    LIMIT 1
")->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    

    <!-- Sidebar -->
    <?php include 'sidebar_pasien.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header">
            <h1>Dashboard</h1>
        </div>
        <div class="welcome mt-4">
            Selamat Datang, <span><strong style="color: #42c3cf;"><?= htmlspecialchars($pasienData['nama'])  ?>!</strong></span>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card-pasien p-3">
                    <h5>Data Diri Anda</h5>
                    <p><strong>Nama Lengkap</strong> : <?= htmlspecialchars($pasienData['nama']) ?></p>
                    <p><strong>Username</strong> : <?= htmlspecialchars($pasienData['username']) ?></p>
                    <p><strong>No RM</strong> : <?= htmlspecialchars($pasienData['no_rm']) ?></p>
                    <p><strong>No KTP</strong> : <?= htmlspecialchars($pasienData['no_ktp']) ?></p>
                    <p><strong>Alamat</strong> : <?= htmlspecialchars($pasienData['alamat']) ?></p>
                    <p><strong>No HP</strong> : <?= htmlspecialchars($pasienData['no_hp']) ?></p>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card-pasien p-3">
                    <h5>Riwayat Daftar Poli Terakhir</h5>
                    <?php if ($riwayatTerakhir): ?>
                        <p><strong>Tanggal Daftar</strong> : <?= htmlspecialchars($riwayatTerakhir['tanggal_daftar']) ?></p>
                        <p><strong>Dokter</strong> : <?= htmlspecialchars($riwayatTerakhir['nama_dokter']) ?></p>
                        <p><strong>Poli</strong> : <?= htmlspecialchars($riwayatTerakhir['nama_poli']) ?></p>
                        <p><strong>Keluhan</strong> : <?= htmlspecialchars($riwayatTerakhir['keluhan']) ?></p>
                        <p><strong>Status</strong> : <?= htmlspecialchars($riwayatTerakhir['status']) ?></p>
                        <p><strong>Waktu Diperiksa</strong> : <?= htmlspecialchars($waktuPeriksa['tgl_periksa'] ?? 'Belum diperiksa') ?></p>
                    <?php else: ?>
                        <p>Belum ada riwayat pendaftaran.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row justify-content-center align-items-center">
            <div class="col-md-12 mb-4" style="max-width: 550px;"> 
                <div class="card-pasien p-3">
                    <h5>Riwayat Konsultasi Terakhir</h5>
                    <?php if ($konsultasiTerakhir): ?>
                        <p>
                            <?= $konsultasiTerakhir['jawaban'] 
                                ? '<span class="badge" style="background-color:rgb(45, 165, 43); color: #fff;">Terjawab &#9989;</span>' 
                                : '<span class="badge" style="background-color:rgb(242, 235, 29); color: rgb(51, 51, 51);">Belum Dijawab 🕗</span>' ?>
                        </p>
                        <p><strong>Tanggal Konsultasi</strong> : <?= htmlspecialchars($konsultasiTerakhir['tgl_konsultasi']) ?></p>
                        <p><strong>Dokter</strong> : <?= htmlspecialchars($konsultasiTerakhir['nama_dokter']) ?></p>
                        <p><strong>Subjek</strong> : <?= htmlspecialchars($konsultasiTerakhir['subject']) ?></p>
                        <p><strong>Pertanyaan</strong> : <?= htmlspecialchars($konsultasiTerakhir['pertanyaan']) ?></p>
                        <p><strong>Jawaban</strong> : <?= $konsultasiTerakhir['jawaban'] ? htmlspecialchars($konsultasiTerakhir['jawaban']) : 'Belum dijawab' ?></p>

                    <?php else: ?>
                        <p>Belum ada riwayat konsultasi.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

