<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienUsername = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienUsername'")->fetch_assoc();

// Ambil riwayat pemeriksaan terakhir pasien
$riwayatTerakhir = $conn->query("
    SELECT p.tgl_periksa, p.catatan, d.nama AS nama_dokter, poli.nama_poli
    FROM periksa p
    JOIN daftar_poli dp ON p.id_daftar_poli = dp.id
    JOIN dokter d ON dp.id_jadwal IN (SELECT id FROM jadwal_periksa WHERE id_dokter = d.id)
    JOIN poli ON d.id_poli = poli.id
    WHERE dp.id_pasien = '{$pasienData['id']}'
    ORDER BY p.tgl_periksa DESC
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
    <link rel="stylesheet" href="../assets/css/pasien/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <div class="avatar-container">
            <h4 id="pasien-panel">Pasien Panel</h4>
            <img src="../assets/images/pasien.png" class="pasien-avatar" alt="Pasien">
            <h6 id="pasien-name"><?= htmlspecialchars($pasienData['nama']) ?></h6>
        </div>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="jadwal_poli.php" class="<?php echo ($current_page == 'jadwal_poli.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> <span>Jadwal Poli</span>
        </a>
        <a href="riwayat_periksa.php" class="<?php echo ($current_page == 'riwayat_periksa.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-medical"></i> <span>Riwayat Periksa</span>
        </a>
        <a href="../logout.php" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header">
            <h1>Dashboard</h1>
        </div>
        <div class="welcome mt-4">
            Selamat Datang, <span><?= htmlspecialchars($pasienData['nama']) ?></span>!
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card p-3">
                    <h5>Data Diri Anda</h5>
                    <p><strong>Nama:</strong> <?= htmlspecialchars($pasienData['nama']) ?></p>
                    <p><strong>No RM:</strong> <?= htmlspecialchars($pasienData['no_rm']) ?></p>
                    <p><strong>No KTP:</strong> <?= htmlspecialchars($pasienData['no_ktp']) ?></p>
                    <p><strong>Alamat:</strong> <?= htmlspecialchars($pasienData['alamat']) ?></p>
                    <p><strong>No HP:</strong> <?= htmlspecialchars($pasienData['no_hp']) ?></p>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card p-3">
                    <h5>Riwayat Pemeriksaan Terakhir</h5>
                    <?php if ($riwayatTerakhir): ?>
                        <p><strong>Tanggal Periksa:</strong> <?= htmlspecialchars($riwayatTerakhir['tgl_periksa']) ?></p>
                        <p><strong>Dokter:</strong> <?= htmlspecialchars($riwayatTerakhir['nama_dokter']) ?></p>
                        <p><strong>Poli:</strong> <?= htmlspecialchars($riwayatTerakhir['nama_poli']) ?></p>
                        <p><strong>Catatan:</strong> <?= htmlspecialchars($riwayatTerakhir['catatan']) ?></p>
                    <?php else: ?>
                        <p>Belum ada riwayat pemeriksaan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const content = document.getElementById('content');

            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
            } else {
                sidebar.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
