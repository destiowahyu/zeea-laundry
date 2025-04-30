<?php
// Ambil data admin dari sesi
$adminName = $_SESSION['username'];

// Ambil data admin dari database
$adminQuery = $conn->prepare("SELECT id, username, foto_profil FROM admin WHERE username = ?");
$adminQuery->bind_param("s", $adminName);
$adminQuery->execute();
$adminResult = $adminQuery->get_result();
$adminData = $adminResult->fetch_assoc();
$namaAdmin = $adminData['username'] ?? "Tidak Ditemukan";


// Ambil ID admin dan username dari session
$adminId = $_SESSION['id'] ?? $adminData['id'];
$adminUsername = $_SESSION['username'];
$adminFoto = $adminData['foto_profil'] ?? null;
?>

<!-- Overlay -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<button class="toggle-btn-mobile" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="avatar-container">
        <div class="admin-avatar-wrapper">
            <?php if (!empty($adminFoto)): ?>
                <img src="../assets/uploads/profil_admin/<?php echo htmlspecialchars($adminFoto); ?>" class="admin-avatar" alt="Admin" onclick="openFullscreenImage(this.src)">
            <?php else: ?>
                <img src="../assets/images/default-avatar.png" class="admin-avatar" alt="Admin" onclick="openFullscreenImage(this.src)">
            <?php endif; ?>
            <a href="profil.php" class="edit-avatar-btn" title="Edit Profil">
                <i class="fas fa-camera"></i>
            </a>
        </div>
        <h6 id="username-admin" style="color: #42c3cf; font-family: Poppins, sans-serif; font-weight: bold; font-size:14px; padding-top:15px;"><?= htmlspecialchars($namaAdmin) ?></h6>
    </div>
    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
    </a>
    <a href="pesanan.php" class="<?php echo ($current_page == 'pesanan.php') ? 'active' : ''; ?>">
        <i class="fas fa-box"></i> <span>Daftar Pesanan</span>
    </a>
    <a href="antar-jemput.php" class="<?php echo ($current_page == 'antar-jemput.php') ? 'active' : ''; ?>">
        <i class="fas fa-truck"></i> <span>Antar Jemput</span>
    </a>
    <a href="laporan_pemasukan.php" class="<?php echo ($current_page == 'laporan_pemasukan.php') ? 'active' : ''; ?>">
        <i class="fas fa-wallet"></i> <span>Laporan Pemasukan</span>
    </a>
    <a href="riwayat_transaksi.php" class="<?php echo ($current_page == 'riwayat_transaksi.php') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i> <span>Riwayat Transaksi</span>
    </a>
    <a href="kelola_pelanggan.php" class="<?php echo ($current_page == 'kelola_pelanggan.php') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> <span>Kelola Pelanggan</span>
    </a>
    <a href="kelola_paket.php" class="<?php echo ($current_page == 'kelola_paket.php') ? 'active' : ''; ?>">
        <i class="fas fa-cogs"></i> <span>Kelola Paket</span>
    </a>
    <a href="profil.php" class="<?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>">
        <i class="fas fa-user"></i> <span>Profil</span>
    </a>
    <a href="javascript:void(0);" onclick="confirmLogout()" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>" style="color: #ff3b30;">
        <i class="fas fa-sign-out-alt" style="color:rgb(224, 44, 34);"></i> <span>Logout</span>
    </a>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: #595959;" id="logoutModalLabel">Konfirmasi Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin logout?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="../logout.php" class="btn btn-danger">Ya, Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Custom fullscreen image overlay -->
<div id="fullscreenOverlay" class="fullscreen-overlay" onclick="closeFullscreenImage(event)">
    <div class="fullscreen-image-container">
        <img id="fullscreenImage" src="/placeholder.svg" alt="Profile Image" onclick="event.stopPropagation()">
    </div>
</div>

<style>
    .admin-avatar-wrapper {
        position: relative;
        display: inline-block;
        margin-bottom: 10px;
    }
    
    .admin-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #42c3cf;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .admin-avatar:hover {
        border-color: #fff;
        box-shadow: 0 0 15px rgba(66, 195, 207, 0.8), 0 0 30px rgba(66, 195, 207, 0.6);
        transform: scale(1.05);
    }
    
    .edit-avatar-btn {
        position: absolute;
        bottom: -25px;
        right: -11px;
        background-color: rgba(66, 195, 207, 0.9);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        font-size: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        z-index: 10;
    }
    
    .edit-avatar-btn:hover {
        background-color: #38adb8;
        transform: scale(1.1);
        color: white;
    }
    
    /* Custom fullscreen styles */
    .fullscreen-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.31);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    
    .fullscreen-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    .fullscreen-image-container {
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #fullscreenImage {
        max-width: 100%;
        max-height: 90vh;
        border-radius: 50%;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    
    .fullscreen-overlay.active #fullscreenImage {
        transform: scale(1);
    }
    
    /* Modal styling */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        background-color: #f8f9fa;
        border-radius: 16px 16px 0 0;
    }
    
    .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background-color: #f8f9fa;
        border-radius: 0 0 16px 16px;
    }
    
    .btn-danger {
        background-color: #ff3b30;
        border-color: #ff3b30;
    }
    
    .btn-danger:hover {
        background-color: #e0352b;
        border-color: #e0352b;
    }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const content = document.getElementById('content');

        // Ubah breakpoint dari 768px menjadi 1440px
        if (window.innerWidth > 1440) {
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
        }
    }

    // Function to open fullscreen image
    function openFullscreenImage(imageSrc) {
        const fullscreenOverlay = document.getElementById('fullscreenOverlay');
        const fullscreenImage = document.getElementById('fullscreenImage');
        
        fullscreenImage.src = imageSrc;
        fullscreenOverlay.classList.add('active');
        
        // Prevent scrolling of the body
        document.body.style.overflow = 'hidden';
    }
    
    // Function to close fullscreen image
    function closeFullscreenImage(event) {
        const fullscreenOverlay = document.getElementById('fullscreenOverlay');
        fullscreenOverlay.classList.remove('active');
        
        // Re-enable scrolling
        document.body.style.overflow = '';
    }
    
    // Function to show logout confirmation
    function confirmLogout() {
        // Using Bootstrap modal
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const overlay = document.getElementById('overlay');
        const sidebarState = localStorage.getItem('sidebarState');

        // Ubah breakpoint dari 768px menjadi 1440px
        if (sidebarState === 'collapsed' && window.innerWidth > 1440) {
            sidebar.classList.add('collapsed');
            content.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            content.classList.remove('collapsed');
        }

        // Ensure overlay is hidden on load
        if (window.innerWidth <= 1440) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
        
        // Add keyboard support for closing fullscreen image with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeFullscreenImage();
            }
        });
    });
</script>