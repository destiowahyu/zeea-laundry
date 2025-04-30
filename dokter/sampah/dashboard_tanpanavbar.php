<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Ambil ID dan nama dokter dari session
$dokterId = $_SESSION['id'];
$dokterName = $_SESSION['username'];

// Fetch data untuk dashboard
$jadwalAktif = $conn->query("
    SELECT hari, jam_mulai, jam_selesai 
    FROM jadwal_periksa 
    WHERE id_dokter = '$dokterId'
    LIMIT 1
")->fetch_assoc();

$pasienCount = $conn->query("
    SELECT COUNT(*) AS total 
    FROM daftar_poli 
    WHERE id_jadwal IN (
        SELECT id FROM jadwal_periksa WHERE id_dokter = '$dokterId'
    )
")->fetch_assoc()['total'];

$riwayatCount = $conn->query("
    SELECT COUNT(*) AS total 
    FROM periksa 
    WHERE id_daftar_poli IN (
        SELECT id FROM daftar_poli WHERE id_jadwal IN (
            SELECT id FROM jadwal_periksa WHERE id_dokter = '$dokterId'
        )
    ) AND tgl_periksa <= CURDATE()
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dokter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dokter/styles.css">
    <style>
        .card {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card i {
            font-size: 36px;
            color: #42c3cf;
            margin-bottom: 10px;
        }
        .card h5 {
            font-size: 18px;
            color: #333;
        }
        .card p {
            font-size: 22px;
            font-weight: bold;
            color: #42c3cf;
            margin: 0;
        }
        .welcome {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .welcome span {
            color: #42c3cf;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Dokter Panel</h3>
        <div class="profile">
            <img src="../assets/images/doctor.png" alt="Avatar">
            <span><?= htmlspecialchars($dokterName) ?></span>
        </div>
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="jadwal_periksa.php"><i class="fas fa-calendar-check"></i> Jadwal Periksa</a>
        <a href="periksa_pasien.php"><i class="fas fa-user-md"></i> Memeriksa Pasien</a>
        <a href="riwayat_pasien.php"><i class="fas fa-history"></i> Riwayat Pasien</a>
        <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="content" id="content">
        <div class="header">
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h1>Dashboard</h1>
        </div>
        <div class="welcome mt-4">
            Selamat Datang, <span><?= htmlspecialchars($dokterName) ?></span>!
        </div>
        <div class="container-fluid">
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <i class="fas fa-calendar-check"></i>
                        <h5>Jadwal Aktif</h5>
                        <p>
                            <?= $jadwalAktif ? $jadwalAktif['hari'] . " (" . $jadwalAktif['jam_mulai'] . " - " . $jadwalAktif['jam_selesai'] . ")" : "Tidak Ada" ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <i class="fas fa-user-md"></i>
                        <h5>Pasien yang Akan Diperiksa</h5>
                        <p><?= $pasienCount ?></p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <i class="fas fa-history"></i>
                        <h5>Riwayat Pemeriksaan</h5>
                        <p><?= $riwayatCount ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            content.classList.toggle('collapsed');
        });

        content.addEventListener('click', () => {
            if (!sidebar.classList.contains('hidden') && window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                content.classList.add('collapsed');
            }
        });
    </script>
</body>
</html>
