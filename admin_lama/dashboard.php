<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/db.php';

// Fetch data from the database
$dokterCount = $conn->query("SELECT COUNT(*) AS total FROM dokter")->fetch_assoc()['total'];
$pasienCount = $conn->query("SELECT COUNT(*) AS total FROM pasien")->fetch_assoc()['total'];
$poliCount = $conn->query("SELECT COUNT(*) AS total FROM poli")->fetch_assoc()['total'];
$obatCount = $conn->query("SELECT COUNT(*) AS total FROM obat")->fetch_assoc()['total'];
$adminCount = $conn->query("SELECT COUNT(*) AS total FROM admin")->fetch_assoc()['total'];

$adminName = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/admin.png">

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
        <h4 id="admin-panel">Admin Panel</h4>
        <img src="../assets/images/admin.png" class="admin-avatar" alt="Admin">
        <h6 id="admin-name"><?= htmlspecialchars($adminName) ?></h6>
    </div>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="kelola_dokter.php" class="<?php echo ($current_page == 'kelola_dokter.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-md"></i> <span>Kelola Dokter</span>
        </a>
        <a href="kelola_pasien.php" class="<?php echo ($current_page == 'kelola_pasien.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>Kelola Pasien</span>
        </a>
        <a href="kelola_poli.php" class="<?php echo ($current_page == 'kelola_poli.php') ? 'active' : ''; ?>">
            <i class="fas fa-hospital"></i> <span>Kelola Poli</span>
        </a>
        <a href="kelola_obat.php" class="<?php echo ($current_page == 'kelola_obat.php') ? 'active' : ''; ?>">
            <i class="fas fa-pills"></i> <span>Kelola Obat</span>
        </a>
        <a href="kelola_admin.php" class="<?php echo ($current_page == 'kelola_admin.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> <span>Kelola Admin</span>
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
            Selamat Datang, <span><?= htmlspecialchars($adminName) ?></span>!
        </div>
        <div class="row d-flex justify-content-center align-items-center">
            <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="kelola_dokter.php">
                    <div class="card p-3">
                        <i class="fas fa-user-md mb-2"></i>
                        <h5>Total Dokter</h5>
                        <p><?= $dokterCount ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="kelola_pasien.php">
                    <div class="card p-3">
                        <i class="fas fa-users mb-2"></i>
                        <h5>Total Pasien</h5>
                        <p><?= $pasienCount ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="kelola_poli.php">
                    <div class="card p-3">
                        <i class="fas fa-hospital mb-2"></i>
                        <h5>Total Poli</h5>
                        <p><?= $poliCount ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="kelola_obat.php">
                    <div class="card p-3">
                        <i class="fas fa-pills mb-2"></i>
                        <h5>Total Obat</h5>
                        <p><?= $obatCount ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a style="text-decoration:none;" href="kelola_admin.php">
                    <div class="card p-3">
                        <i class="fas fa-pills mb-2"></i>
                        <h5>Total Admin</h5>
                        <p><?= $adminCount ?></p>
                    </div>
                </a>
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

        // Default sidebar state on load
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
