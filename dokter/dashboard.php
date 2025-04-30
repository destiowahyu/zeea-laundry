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
    <style>
        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        /* Header Container */
        .header-container {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 1000;
            transition: all 0.3s ease;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 1);
            height: 70px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .content.collapsed .header-container {
            left: 60px;
        }

        .header-container.scrolled {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(2px);
            height: 60px;
        }

        /* Header Title */
        .header-container h1 {
            font-size: 24px;
            padding-left: 20px;
            margin: 0;
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1001;
            color: #42c3cf;
            border: none;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            display: block; /* Always display the toggle button */
        }

        /* Content Wrapper */
        .content-wrapper {
            margin-top: 90px;
            padding: 20px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .card i {
            font-size: 2rem;
            color: #42c3cf;
            margin-bottom: 1rem;
        }

        .card h5 {
            color: #333;
            margin-bottom: 1rem;
        }

        .card p {
            color: #666;
            margin: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #f8f9fa;
            transition: left 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            left: -190px;
        }

        /* Content */
        .content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        .content.collapsed {
            margin-left: 60px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-container {
                left: 0;
                padding: 1rem;
                padding-left: 35px;
            }

            .header-container.scrolled h1 {
                font-size: 20px;
                text-align: center;
                align-items: center;
                padding-top: 7px;
            }

            .sidebar.open + .sidebar-overlay {
                display: block;
            }

            .sidebar {
                left: -250px;
            }

            .sidebar.open {
                left: 0;
            }

            .content {
                margin-left: 0;
            }
        }

    </style>
</head>
<body>
    <?php include 'sidebar_dokter.php'; ?>



    <!-- Main Content -->
    <div class="content" id="content">

        <!-- Tombol toggle untuk semua ukuran layar -->
        <button class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="header-container" id="header">
            <h1>Dashboard</h1>
        </div>

        <div class="content-wrapper">
            <div class="welcome mb-4">
                Selamat Datang, <span><strong style="color: #42c3cf;"><?= htmlspecialchars($namaDokter) ?>!</strong></span> 
            </div>
            <div class="welcome mb-4">
                Anda adalah Dokter di <strong>Poli</strong> : <strong style="color: #42c3cf;"><?= htmlspecialchars($poliDokter) ?></strong>
            </div>
            <div class="container-fluid px-0">
                <div class="row g-4">
                    <div class="col-md-3">
                        <a href="jadwal_periksa.php" style="text-decoration:none;">
                            <div class="card h-100">
                                <i class="fas fa-calendar-check"></i>
                                <h5>Jadwal Aktif</h5>
                                <p><?= $jadwalAktif ? $jadwalAktif['hari'] . " (" . $jadwalAktif['jam_mulai'] . " - " . $jadwalAktif['jam_selesai'] . ")" : "Tidak ada jadwal aktif" ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="periksa_pasien.php" style="text-decoration:none;">
                            <div class="card h-100">
                                <i class="fas fa-user-plus"></i>
                                <h5>Pasien Hari Ini</h5>
                                <p><?= $pasienHariIni ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="periksa_pasien.php" style="text-decoration:none;">
                            <div class="card h-100">
                                <i class="fas fa-user-clock"></i>
                                <h5>Pasien Belum Diperiksa Hari Ini</h5>
                                <p><?= $pasienBelumDiperiksa ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="konsultasi.php" style="text-decoration:none;">
                            <div class="card h-100">
                                <i class="fas fa-comments"></i>
                                <h5>Konsultasi Belum Terjawab</h5>
                                <p><?= $konsultasiBelumTerjawab ?></p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');

            function toggleSidebar() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('open');
                    if (sidebarOverlay) {
                        sidebarOverlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
                    }
                }
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    toggleSidebar();
                });
            }



            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('open');
                    if (sidebarOverlay) {
                        sidebarOverlay.style.display = 'none';
                    }
                }
            });

            // Scroll effect
            window.addEventListener('scroll', function() {
                const header = document.getElementById('header');
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        });
    </script>
</body>
</html>

