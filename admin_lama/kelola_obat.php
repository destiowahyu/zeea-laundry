<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/db.php';

// Handle messages for notifications
$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama_obat = $_POST['nama_obat'];
        $kemasan = $_POST['kemasan'];
        $harga = $_POST['harga'];

        $result = $conn->query("INSERT INTO obat (nama_obat, kemasan, harga) 
                                VALUES ('$nama_obat', '$kemasan', '$harga')");
        if ($result) {
            $message = 'Obat berhasil ditambahkan!';
            $type = 'success';
        } else {
            $message = 'Gagal menambahkan obat!';
            $type = 'error';
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_obat = $_POST['nama_obat'];
        $kemasan = $_POST['kemasan'];
        $harga = $_POST['harga'];

        $result = $conn->query("UPDATE obat SET nama_obat='$nama_obat', kemasan='$kemasan', harga='$harga' WHERE id='$id'");
        if ($result) {
            $message = 'Obat berhasil diperbarui!';
            $type = 'success';
        } else {
            $message = 'Gagal memperbarui obat!';
            $type = 'error';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];

        $result = $conn->query("DELETE FROM obat WHERE id='$id'");
        if ($result) {
            $message = 'Obat berhasil dihapus!';
            $type = 'success';
        } else {
            $message = 'Gagal menghapus obat!';
            $type = 'error';
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$obatList = $conn->query("SELECT * FROM obat WHERE nama_obat LIKE '%$search%'");
if (!$obatList) {
    die("Query gagal: " . $conn->error);
}

$adminName = $_SESSION['username'];

    // If it's an AJAX request, only return the table rows
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $no = 1;
        ob_start(); // Start output buffering
        while ($row = $obatList->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $row['id'] ?></td>
                <td><?= $row['nama_obat'] ?></td>
                <td><?= $row['kemasan'] ?></td>
                <td><?= $row['harga'] ?></td>
                <td>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editObatModal<?= $row['id'] ?>">Edit</button>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                </td>
            </tr>
        <?php endwhile;
        echo ob_get_clean(); // Return only the buffered content
        exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Obat - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        </div>

                        <div class="container">
                    <h1 class="mb-4">Kelola Obat</h1>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex">
                            <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Obat..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>


                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addObatModal"><i class="fas fa-pills"></i> Tambah Obat</button>




                    <!-- ISI TABEL -->
                    <table class="table-obat table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Obat</th>
                                <th>Nama Obat</th>
                                <th>Kemasan</th>
                                <th>Harga</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($row = $obatList->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['nama_obat'] ?></td>
                                    <td><?= $row['kemasan'] ?></td>
                                    <td><?= $row['harga'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editObatModal<?= $row['id'] ?>">Edit</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editObatModal<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Obat</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Nama Obat</label>
                                                        <input type="text" name="nama_obat" class="form-control" value="<?= $row['nama_obat'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Kemasan</label>
                                                        <input type="text" name="kemasan" class="form-control" value="<?= $row['kemasan'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Harga</label>
                                                        <input type="number" name="harga" class="form-control" value="<?= $row['harga'] ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="edit" class="btn btn-warning">Simpan</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Modal -->
                <div class="modal fade" id="addObatModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Obat</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Nama Obat</label>
                                        <input type="text" name="nama_obat" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Kemasan</label>
                                        <input type="text" name="kemasan" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Harga</label>
                                        <input type="number" name="harga" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($message): ?>
                <script>
                    Swal.fire({
                        icon: '<?= $type ?>',
                        title: '<?= $message ?>',
                        showConfirmButton: false,
                        timer: 1500
                    });
                </script>
                <?php endif; ?>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
                   


        
            <script>
                // Real-time search
                    document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const tableBody = document.querySelector('.table-obat tbody');

                let timeoutId;
                searchInput.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        const searchTerm = this.value.toLowerCase();
                        
                        // Add X-Requested-With header to identify AJAX request
                        fetch(`kelola_obat.php?search=${encodeURIComponent(searchTerm)}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.text())
                        .then(html => {
                            // Only update the table rows
                            tableBody.innerHTML = html;
                        })
                        .catch(error => console.error('Error:', error));
                    }, 300); // Add debounce delay of 300ms
                });
            });
            </script>

        <!-- SweetAlert Notification -->
        <?php if ($message): ?>
        <script>
            Swal.fire({
                icon: '<?= $type ?>',
                title: '<?= $message ?>',
                showConfirmButton: false,
                timer: 1500
            });
        </script>
        <?php endif; ?>
    



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
