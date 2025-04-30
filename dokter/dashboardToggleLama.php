<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Ambil ID dokter dari session
$dokterId = $_SESSION['id'];

// Ambil data nama dokter dari database
$stmt = $conn->prepare("SELECT nama FROM dokter WHERE id = ?");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$dokterData = $stmt->get_result()->fetch_assoc();
$namaDokter = $dokterData['nama'] ?? "Tidak Ditemukan";

// Ambil data nama poli dokter dari database
$stmt = $conn->prepare("SELECT p.nama_poli FROM poli p JOIN dokter d ON d.id_poli = p.id WHERE d.id = ?");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$poliData = $stmt->get_result()->fetch_assoc();
$poliDokter = $poliData['nama_poli'] ?? "Tidak Ditemukan";

// Fetch jadwal aktif untuk dashboard
$stmt = $conn->prepare("SELECT hari, jam_mulai, jam_selesai FROM jadwal_periksa WHERE id_dokter = ? AND status = 'Aktif' LIMIT 1");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$jadwalAktif = $stmt->get_result()->fetch_assoc();

// Fetch jumlah pasien yang mendaftar ke dokter ini hari ini
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM daftar_poli dp
    JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
    WHERE jp.id_dokter = ? AND DATE(dp.created_at) = CURDATE()
");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$pasienHariIni = $stmt->get_result()->fetch_assoc()['total'];

// Fetch jumlah pasien hari ini yang belum diperiksa
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM daftar_poli dp
    JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
    WHERE jp.id_dokter = ? AND DATE(dp.created_at) = CURDATE() AND dp.status = 'Belum Diperiksa'
");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$pasienBelumDiperiksa = $stmt->get_result()->fetch_assoc()['total'];

// Fetch jumlah konsultasi pasien belum terjawab
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM konsultasi 
    WHERE id_dokter = ? AND jawaban IS NULL
");
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$konsultasiBelumTerjawab = $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header">
            <h1>Dashboard</h1>
        </div>
        <div class="welcome mt-4">
            Selamat Datang, <span><strong style="color: #42c3cf;"><?= htmlspecialchars($namaDokter) ?>!</strong></span> 
        </div>
        <div class="welcome mt-2">
        Anda adalah Dokter di <strong>Poli</strong> : <strong style="color: #42c3cf;"><?= htmlspecialchars($poliDokter) ?></strong>
        </div>
        <div class="container-fluid">
            <div class="row mt-4">
                <div class="col-md-3 mb-4">
                    <div class="card">
                    <a style="text-decoration:none;" href="jadwal_periksa.php">
                        <i class="fas fa-calendar-check"></i>
                        <h5>Jadwal Aktif</h5>
                        <p>
                            <?= $jadwalAktif ? $jadwalAktif['hari'] . " (" . $jadwalAktif['jam_mulai'] . " - " . $jadwalAktif['jam_selesai'] . ")" : "Tidak ada jadwal aktif" ?>
                        </p>
                    </a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <a style="text-decoration:none;" href="periksa_pasien.php">
                    <div class="card">
                        <i class="fas fa-user-plus"></i>
                        <h5>Pasien Hari Ini</h5>
                        <p><?= $pasienHariIni ?></p>
                    </div>
                    </a>
                </div>
                <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="periksa_pasien.php">
                    <div class="card">
                        <i class="fas fa-user-clock"></i>
                        <h5>Pasien Belum Diperiksa Hari Ini</h5>
                        <p><?= $pasienBelumDiperiksa ?></p>
                    </div>
                </a>
                </div>
                <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="konsultasi.php">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <h5>Konsultasi Belum Terjawab</h5>
                        <p><?= $konsultasiBelumTerjawab ?></p>
                    </div>
                </a>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
