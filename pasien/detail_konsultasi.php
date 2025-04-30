<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$active_page = 'konsultasi.php';

include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienName = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienName'")->fetch_assoc();

// Ambil ID konsultasi dari parameter URL
$id_konsultasi = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil detail konsultasi
$query_konsultasi = "
    SELECT k.*, d.nama AS nama_dokter, p.nama_poli
    FROM konsultasi k
    JOIN dokter d ON k.id_dokter = d.id
    JOIN poli p ON d.id_poli = p.id
    WHERE k.id = ? AND k.id_pasien = ?
";
$stmt_konsultasi = $conn->prepare($query_konsultasi);
$stmt_konsultasi->bind_param("ii", $id_konsultasi, $pasienData['id']);
$stmt_konsultasi->execute();
$result_konsultasi = $stmt_konsultasi->get_result();

if ($result_konsultasi->num_rows === 0) {
    echo "Konsultasi tidak ditemukan atau Anda tidak memiliki akses.";
    exit();
}

$konsultasi = $result_konsultasi->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Konsultasi - Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <div class="avatar-container">
            <h4 id="admin-panel">Pasien Panel</h4>
            <img src="../assets/images/pasien.png" class="admin-avatar" alt="Pasien">
            <h6 id="admin-name"><?= htmlspecialchars($pasienName) ?></h6>
        </div>
        <a href="dashboard.php" class="<?php echo ($active_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="daftar_poli.php" class="<?php echo ($active_page == 'daftar_poli.php') ? 'active' : ''; ?>">
            <i class="fas fa-stethoscope"></i> <span>Daftar Poli</span>
        </a>
        <a href="konsultasi.php" class="<?php echo ($active_page == 'konsultasi.php') ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> <span>Konsultasi</span>
        </a>
        <a href="profil.php" class="<?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> <span>Profil</span>
        </a>
        <a href="../logout.php" class="<?php echo ($active_page == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Detail Konsultasi</h1>

            <div class="card-konsultasi mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-2">Subject : <?= htmlspecialchars($konsultasi['subject']) ?></h4>
                    <p class="card-text"><strong>Pertanyaan : </strong><?= nl2br(htmlspecialchars($konsultasi['pertanyaan'])) ?></p>
                    <h6 class="card-subtitle mb-2 text-muted">Poli: <?= htmlspecialchars($konsultasi['nama_poli']) ?></h6>
                    <h6 class="card-subtitle mb-2 text-muted">Dokter: <?= htmlspecialchars($konsultasi['nama_dokter']) ?></h6>
                    <h6 class="card-subtitle mb-2 text-muted">Tanggal Konsultasi:<?= date('d-m-Y H:i', strtotime($konsultasi['tgl_konsultasi'])) ?></h6>
                    
                    
                    <?php if ($konsultasi['jawaban']): ?>
                        <hr>
                        <p class="card-text"><strong>Jawaban Dokter:</strong></p>
                        <p class="card-text"><?= nl2br(htmlspecialchars($konsultasi['jawaban'])) ?></p>
                    <?php else: ?>
                        <p class="card-text text-muted">Belum ada jawaban dari dokter.</p>
                    <?php endif; ?>
                </div>
            </div>

            <a href="konsultasi.php" class="btn btn-primary">Kembali</a>
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
    </script>
</body>
</html>


