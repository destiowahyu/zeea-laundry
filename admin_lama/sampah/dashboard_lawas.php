<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fetch data from the database
$dokterCount = $conn->query("SELECT COUNT(*) AS total FROM dokter")->fetch_assoc()['total'];
$pasienCount = $conn->query("SELECT COUNT(*) AS total FROM pasien")->fetch_assoc()['total'];
$poliCount = $conn->query("SELECT COUNT(*) AS total FROM poli")->fetch_assoc()['total'];
$obatCount = $conn->query("SELECT COUNT(*) AS total FROM obat")->fetch_assoc()['total'];

$adminName = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <style>
        .welcome {
            font-size: 1.5rem;
        }

        .welcome span {
            color: #42c3cf;
            font-weight: bold;
        }

        .card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card i {
            font-size: 40px;
            color: #42c3cf;
            margin-bottom: 10px;
        }

        .card h5 {
            color: black;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .card p {
            font-size: 2rem;
            font-weight: bold;
            color: #42c3cf;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Admin Panel</h3>
    
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="kelola_dokter.php"><i class="fas fa-user-md"></i> Kelola Dokter</a>
        <a href="kelola_pasien.php"><i class="fas fa-user"></i> Kelola Pasien</a>
        <a href="kelola_poli.php"><i class="fas fa-clinic-medical"></i> Kelola Poli</a>
        <a href="kelola_obat.php"><i class="fas fa-pills"></i> Kelola Obat</a>
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
            Selamat Datang, <?= htmlspecialchars($adminName) ?>!
        </div>
        <div class="container-fluid">
            <div class="row mt-4">
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <i class="fas fa-user-md"></i>
                        <h5>Total Dokter</h5>
                        <p><?= $dokterCount ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <i class="fas fa-user"></i>
                        <h5>Total Pasien</h5>
                        <p><?= $pasienCount ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <i class="fas fa-clinic-medical"></i>
                        <h5>Total Poli</h5>
                        <p><?= $poliCount ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <i class="fas fa-pills"></i>
                        <h5>Total Obat</h5>
                        <p><?= $obatCount ?></p>
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
