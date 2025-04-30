<?php
// Ambil data dokter dari sesi
$dokterName = $_SESSION['username'];
$dokterData = $conn->query("SELECT * FROM dokter WHERE username = '$dokterName'")->fetch_assoc();

// Ambil ID dokter dan username dari session
$dokterId = $_SESSION['id'];
$dokterUsername = $_SESSION['username'];
?>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Tombol Sidebar Mobile-->
<button class="toggle-btn-mobile" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>


<!-- Sidebar -->
<div class="sidebar" id="sidebar">
<button class="toggle-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>
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
    <a href="periksa_pasien.php" class="<?php echo ($periksaPasien_page == 'periksa_pasien.php') ? 'active' : ''; ?>">
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


<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const overlay = document.getElementById('overlay');
        const toggleBtnMobile = document.querySelector('.toggle-btn-mobile');

        if (window.innerWidth > 768) {
            // Toggle for desktop
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');

            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
        } else {
            // Toggle for mobile
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');

            // Sembunyikan tombol toggle ketika sidebar terbuka
            if (sidebar.classList.contains('open')) {
                toggleBtnMobile.style.display = 'none'; // Sembunyikan tombol toggle
            } else {
                toggleBtnMobile.style.display = 'block'; // Tampilkan tombol toggle
            }
        }
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const overlay = document.getElementById('overlay');
        const toggleBtnMobile = document.querySelector('.toggle-btn-mobile');
        const sidebarState = localStorage.getItem('sidebarState');

        if (sidebarState === 'collapsed' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
            content.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            content.classList.remove('collapsed');
        }

        // Ensure overlay is hidden on load
        if (window.innerWidth <= 768) {
            sidebar.classList.add('hidden');
            overlay.classList.remove('show');

            // Periksa status sidebar dan sembunyikan tombol toggle jika sidebar terbuka
            if (sidebar.classList.contains('open')) {
                toggleBtnMobile.style.display = 'none';
            } else {
                toggleBtnMobile.style.display = 'block';
            }
        }
    });

</script>
