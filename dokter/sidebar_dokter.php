<?php
// Ambil data dokter dari sesi
$dokterName = $_SESSION['username'];
$dokterData = $conn->query("SELECT * FROM dokter WHERE username = '$dokterName'")->fetch_assoc();

// Ambil ID dokter dan username dari session
$dokterId = $_SESSION['id'];
$dokterUsername = $_SESSION['username'];
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="avatar-container">
        <h4 id="admin-panel">Dokter Panel</h4>
        <img src="../assets/images/avatar-doctor.png" class="admin-avatar" alt="Admin">
        <h6 id="admin-name"><?= htmlspecialchars($dokterName) ?></h6>
    </div>
    <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
    </a>
    <a href="jadwal_periksa.php" class="<?php echo ($current_page == 'jadwal_periksa.php') ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i><span>Jadwal Periksa</span>
    </a>
    <a href="periksa_pasien.php" class="<?php echo ($current_page == 'periksa_pasien.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-md"></i> <span>Periksa Pasien</span>
    </a>
    <a href="konsultasi.php" class="<?php echo ($current_page == 'konsultasi.php') ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i> <span>Konsultasi</span>
    </a>
    <a href="riwayat_pasien.php" class="<?php echo ($current_page == 'riwayat_pasien.php') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i> <span>Riwayat Pasien</span>
    </a>
    <a href="profil.php" class="<?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>">
        <i class="fas fa-user"></i> <span>Profil</span>
    </a>
    <a href="../logout.php" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>