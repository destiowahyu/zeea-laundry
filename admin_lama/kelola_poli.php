    <?php
    session_start();
    if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../index.php");
        exit();
    }

    include '../includes/db.php';

    $current_page = basename($_SERVER['PHP_SELF']);

    // Handle messages for notifications
    $message = '';
    $type = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add'])) {
            $nama_poli = $_POST['nama_poli'];
            $keterangan = $_POST['keterangan'];

            $result = $conn->query("INSERT INTO poli (nama_poli, keterangan) 
                                    VALUES ('$nama_poli', '$keterangan')");
            if ($result) {
                $message = 'Poli berhasil ditambahkan!';
                $type = 'success';
            } else {
                $message = 'Gagal menambahkan poli!';
                $type = 'error';
            }
        }

        if (isset($_POST['edit'])) {
            $id = $_POST['id'];
            $nama_poli = $_POST['nama_poli'];
            $keterangan = $_POST['keterangan'];

            $result = $conn->query("UPDATE poli SET nama_poli='$nama_poli', keterangan='$keterangan' WHERE id='$id'");
            if ($result) {
                $message = 'Poli berhasil diperbarui!';
                $type = 'success';
            } else {
                $message = 'Gagal memperbarui poli!';
                $type = 'error';
            }
        }

        if (isset($_POST['delete'])) {
            $id = $_POST['id'];

            $result = $conn->query("DELETE FROM poli WHERE id='$id'");
            if ($result) {
                $message = 'Poli berhasil dihapus!';
                $type = 'success';
            } else {
                $message = 'Gagal menghapus poli!';
                $type = 'error';
            }
        }
    }

    // Handle search
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $poliList = $conn->query("SELECT * FROM poli WHERE nama_poli LIKE '%$search%'");
    if (!$poliList) {
        die("Query gagal: " . $conn->error);
    }

    $adminName = $_SESSION['username'];

    // If it's an AJAX request, only return the table rows
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $no = 1;
        ob_start(); // Start output buffering
        while ($row = $poliList->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $row['id'] ?></td>
                <td><?= $row['nama_poli'] ?></td>
                <td class="keterangan-poli"><?= htmlspecialchars($row['keterangan']) ?></td>
                <td>
                    <div class="tombol-aksi">
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPoliModal<?= $row['id'] ?>">Edit</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
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
    <title>Kelola Poli - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
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
                    <h1 class="mb-4">Kelola Poli</h1>


                    <!-- ISI TABEL -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex">
                            <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Poli..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPoliModal"><i class="bi bi-building-add"></i> Tambah Poli</button>

                    <table class="table-poli table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Poli</th>
                                <th>Nama Poli</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($row = $poliList->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['nama_poli'] ?></td>
                                    <td class="keterangan-poli"><?= $row['keterangan'] ?></td>
                                    <td>
                                        <div class="tombol-aksi">
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPoliModal<?= $row['id'] ?>">Edit</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editPoliModal<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Poli</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Nama Poli</label>
                                                        <input type="text" name="nama_poli" class="form-control" value="<?= $row['nama_poli'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Keterangan</label>
                                                        <textarea name="keterangan" class="form-control"><?= $row['keterangan'] ?></textarea>
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
                <div class="modal fade" id="addPoliModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Poli</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Nama Poli</label>
                                        <input type="text" name="nama_poli" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Keterangan</label>
                                        <textarea name="keterangan" class="form-control" required></textarea>
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

                    // Real-time search
                    document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const tableBody = document.querySelector('.table-poli tbody');

                let timeoutId;
                searchInput.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        const searchTerm = this.value.toLowerCase();
                        
                        // Add X-Requested-With header to identify AJAX request
                        fetch(`kelola_poli.php?search=${encodeURIComponent(searchTerm)}`, {
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
</body>
</html>
