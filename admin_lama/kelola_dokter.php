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

// TAMBAH DATA DOKTER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_hp = $_POST['no_hp'];
        $id_poli = $_POST['id_poli'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Menggunakan password_hash

        // Validasi username dengan regex (tidak boleh ada dua tanda minus berturut-turut)
        if (!preg_match('/^(?!.*--)[a-zA-Z0-9-]+$/', $username)) {
            $message = "Username tidak valid! Hindari penggunaan karakter khusus yang tidak diperbolehkan.";
            $type = "error";
        } else {
            // Cek apakah username sudah ada di database
            $cekUsername = $conn->prepare("SELECT * FROM dokter WHERE username = ?");
            $cekUsername->bind_param("s", $username);
            $cekUsername->execute();
            $result = $cekUsername->get_result();
            if ($result->num_rows > 0) {
                $message = "Username sudah digunakan oleh dokter lain!";
                $type = "error";
            } else {
                // Jika username belum ada, tambahkan data dokter
                $stmt = $conn->prepare("INSERT INTO dokter (nama, alamat, no_hp, id_poli, username, password) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $nama, $alamat, $no_hp, $id_poli, $username, $password);
                if ($stmt->execute()) {
                    $message = "Dokter berhasil ditambahkan!";
                    $type = "success";
                } else {
                    $message = "Gagal menambahkan dokter: " . $conn->error;
                    $type = "error";
                }
            }
        }
    }

    // EDIT DATA DOKTER
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_hp = $_POST['no_hp'];
        $id_poli = $_POST['id_poli'];
        $username = $_POST['username'];
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        // Validasi username dengan regex (tidak boleh ada dua tanda minus berturut-turut)
        if (!preg_match('/^(?!.*--)[a-zA-Z0-9-]+$/', $username)) {
            $message = "Username tidak valid! Hindari penggunaan karakter khusus yang tidak diperbolehkan.";
            $type = "error";
        } else {
            // Cek apakah username sudah ada di database dan bukan milik dokter ini
            $cekUsername = $conn->prepare("SELECT * FROM dokter WHERE username = ? AND id != ?");
            $cekUsername->bind_param("si", $username, $id);
            $cekUsername->execute();
            $result = $cekUsername->get_result();
            if ($result->num_rows > 0) {
                $message = "Username sudah digunakan oleh dokter lain!";
                $type = "error";
            } else {
                // Jika username belum digunakan oleh dokter lain, lanjutkan update
                if ($password) {
                    $stmt = $conn->prepare("UPDATE dokter SET nama=?, alamat=?, no_hp=?, id_poli=?, username=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $nama, $alamat, $no_hp, $id_poli, $username, $password, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE dokter SET nama=?, alamat=?, no_hp=?, id_poli=?, username=? WHERE id=?");
                    $stmt->bind_param("sssssi", $nama, $alamat, $no_hp, $id_poli, $username, $id);
                }
                if ($stmt->execute()) {
                    $message = "Data dokter berhasil diperbarui!";
                    $type = "success";
                } else {
                    $message = "Gagal memperbarui data dokter: " . $conn->error;
                    $type = "error";
                }
            }
        }
    }

    // HAPUS DATA DOKTER
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM dokter WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Dokter berhasil dihapus!";
            $type = "success";
        } else {
            $message = "Gagal menghapus dokter: " . $conn->error;
            $type = "error";
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT dokter.*, poli.nama_poli 
          FROM dokter 
          LEFT JOIN poli ON dokter.id_poli = poli.id
          WHERE dokter.nama LIKE ?";
$stmt = $conn->prepare($query);
$searchParam = "%$search%";
$stmt->bind_param("s", $searchParam);
$stmt->execute();
$dokterList = $stmt->get_result();

if (!$dokterList) {
    die("Query gagal: " . $conn->error);
}

$poliList = $conn->query("SELECT * FROM poli");

$adminName = $_SESSION['username'];

// AJAX search handler
if (isset($_GET['ajax_search'])) {
    $search = $_GET['ajax_search'];
    $query = "SELECT dokter.*, poli.nama_poli 
              FROM dokter 
              LEFT JOIN poli ON dokter.id_poli = poli.id
              WHERE dokter.nama LIKE ?";
    $stmt = $conn->prepare($query);
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $dokterList = $stmt->get_result();

    $no = 1;
    while ($row = $dokterList->fetch_assoc()):
        echo "<tr>
                <td>" . $no++ . "</td>
                <td>" . $row['id'] . "</td>
                <td>" . htmlspecialchars($row['nama']) . "</td>
                <td>" . htmlspecialchars($row['alamat']) . "</td>
                <td>" . htmlspecialchars($row['no_hp']) . "</td>
                <td>" . htmlspecialchars($row['nama_poli']) . "</td>
                <td>" . htmlspecialchars($row['username']) . "</td>
                <td>
                    <div class='tombol-aksi'>
                        <button class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editDoctorModal" . $row['id'] . "'>Edit</button>
                        <form method='POST' style='display:inline-block;'>
                            <input type='hidden' name='id' value='" . $row['id'] . "'>
                            <button type='submit' name='delete' class='btn btn-danger btn-sm'>Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>";
    endwhile;
    exit;
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Dokter - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
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
            <div class="container">
                <h1 class="mb-4">Kelola Dokter</h1>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex">
                        <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Dokter..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDoctorModal"><i class="fas fa-user-md"></i> Tambah Dokter</button>

                <!-- ISI TABEL DOKTER -->
                <table class="table-dokter table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Dokter</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th>No HP</th>
                            <th>Poli</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="doctorTableBody">
                        <?php 
                        $no = 1;
                        while ($row = $dokterList->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['alamat']) ?></td>
                                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td>
                                    <div class="tombol-aksi">
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editDoctorModal<?= $row['id'] ?>">Edit</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editDoctorModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Dokter</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Nama</label>
                                                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($row['nama']) ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Alamat</label>
                                                    <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($row['alamat']) ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">No HP</label>
                                                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($row['no_hp']) ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="poli" class="form-label">Pilih Poli:</label>
                                                    <select name="id_poli" class="form-select">
                                                        <option value="">Pilih Poli</option>
                                                        <?php
                                                        $poliDropdown = $conn->query("SELECT * FROM poli");
                                                        while ($poli = $poliDropdown->fetch_assoc()):
                                                        ?>
                                                            <option value="<?= $poli['id'] ?>" <?= $row['id_poli'] == $poli['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($poli['nama_poli']) ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Username</label>
                                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($row['username']) ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label>Password (Kosongkan jika tidak ingin diubah)</label>
                                                    <input type="password" name="password" class="form-control">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="edit" class="btn btn-warning">Simpan</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
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
            <div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Tambah Dokter</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama</label>
                                    <input type="text" name="nama" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <input type="text" name="alamat" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">No HP</label>
                                    <input type="text" name="no_hp" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Poli</label>
                                    <select name="id_poli" class="form-control" required>
                                        <?php while ($row = $poliList->fetch_assoc()): ?>
                                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama_poli']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SweetAlert Notification -->
            <?php if ($message): ?>
            <script>
                Swal.fire({
                    icon: '<?= $type ?>',
                    title: '<?= $message ?>',
                    showConfirmButton: false,
                    timer: 2000
                });
            </script>
            <?php endif; ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

            <script>
                // SIDEBAR BAWAAN JANGAN DIHAPUS
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

                // Realtime search
                document.getElementById('searchInput').addEventListener('input', function() {
                    const searchTerm = this.value;
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'kelola_dokter.php?ajax_search=' + encodeURIComponent(searchTerm), true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            document.getElementById('doctorTableBody').innerHTML = xhr.responseText;
                        }
                    };
                    xhr.send();
                });
            </script>
        </div>
    </div>
</body>
</html>

