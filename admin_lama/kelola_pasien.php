<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/db.php';

// Fungsi untuk generate No RM terkecil yang belum ada
function generateNoRM($conn) {
    $tahunBulan = date('Ym');
    
    // Ambil semua No RM yang sudah ada untuk tahun-bulan saat ini
    $query = $conn->query("SELECT no_rm FROM pasien WHERE no_rm LIKE '$tahunBulan-%'");
    if ($query === false) {
        die("Error query: " . $conn->error);
    }
    
    // Simpan No RM yang ada ke dalam array
    $existingNumbers = [];
    while ($row = $query->fetch_assoc()) {
        $number = (int)substr($row['no_rm'], strlen($tahunBulan) + 1); // Ambil angka setelah "-"
        $existingNumbers[] = $number;
    }
    
    // Cari nomor terkecil yang belum ada
    $newNumber = 1; // Mulai dari 1
    while (in_array($newNumber, $existingNumbers)) {
        $newNumber++;
    }
    
    // Hasilkan No RM baru
    return sprintf('%s-%d', $tahunBulan, $newNumber);
}

$no_rm_generate = generateNoRM($conn);

// TAMBAH DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_ktp = $_POST['no_ktp'];
        $no_hp = $_POST['no_hp'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Menggunakan password_hash()

        // Cek apakah username dan no KTP sudah ada di database
        $cekUsernameKTP = $conn->query("SELECT * FROM pasien WHERE username = '$username' OR no_ktp = '$no_ktp'");

        $usernameUsed = false;
        $ktpUsed = false;

        while ($row = $cekUsernameKTP->fetch_assoc()) {
            if ($row['username'] === $username) {
                $usernameUsed = true;
            }
            if ($row['no_ktp'] === $no_ktp) {
                $ktpUsed = true;
            }
        }

        // Menentukan pesan error berdasarkan kondisi
        if ($usernameUsed && $ktpUsed) {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("Username dan No KTP sudah terdaftar!"));
            exit();
        } elseif ($usernameUsed) {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("Username sudah digunakan!"));
            exit();
        } elseif ($ktpUsed) {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("No KTP sudah terdaftar!"));
            exit();
        }

        // Jika username dan No KTP tidak ada, lanjut proses insert
        $no_rm = generateNoRM($conn);
        $result = $conn->query("INSERT INTO pasien (nama, alamat, no_ktp, no_hp, no_rm, username, password) 
                                VALUES ('$nama', '$alamat', '$no_ktp', '$no_hp', '$no_rm', '$username', '$password')");
        if ($result) {
            header("Location: kelola_pasien.php?message=success&info=" . urlencode("Pasien berhasil ditambahkan!"));
            exit();
        } else {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("Gagal menambahkan pasien!"));
            exit();
        }
    }

    // EDIT DATA
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_ktp = $_POST['no_ktp'];
        $no_hp = $_POST['no_hp'];
        $username = $_POST['username'];
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null; // Menggunakan password_hash()

        // Ambil data saat ini dari database berdasarkan ID
        $currentDataQuery = $conn->query("SELECT * FROM pasien WHERE id = '$id'");
        if ($currentDataQuery->num_rows > 0) {
            $currentData = $currentDataQuery->fetch_assoc();

            // Periksa apakah username atau no_ktp sudah digunakan oleh pasien lain
            $cekData = $conn->query("SELECT * FROM pasien WHERE (username = '$username' OR no_ktp = '$no_ktp') AND id != '$id'");

            $usernameUsed = false;
            $ktpUsed = false;

            while ($row = $cekData->fetch_assoc()) {
                if ($row['username'] === $username && $username !== $currentData['username']) {
                    $usernameUsed = true;
                }
                if ($row['no_ktp'] === $no_ktp && $no_ktp !== $currentData['no_ktp']) {
                    $ktpUsed = true;
                }
            }

            // Menentukan pesan error berdasarkan kondisi
            if ($usernameUsed && $ktpUsed) {
                $errorMessage = "Username dan No KTP sudah digunakan oleh pasien lain!";
            } elseif ($usernameUsed) {
                $errorMessage = "Username sudah digunakan oleh pasien lain!";
            } elseif ($ktpUsed) {
                $errorMessage = "No KTP sudah digunakan oleh pasien lain!";
            }

            // Jika ada konflik, tampilkan error
            if ($usernameUsed || $ktpUsed) {
                header("Location: kelola_pasien.php?message=error&info=" . urlencode($errorMessage));
                exit();
            }

            // Jika tidak ada konflik, lakukan update
            $passwordUpdate = $password ? ", password='$password'" : "";
            $result = $conn->query("UPDATE pasien SET 
                                    nama='$nama', 
                                    alamat='$alamat', 
                                    no_ktp='$no_ktp', 
                                    no_hp='$no_hp', 
                                    username='$username' 
                                    $passwordUpdate 
                                    WHERE id='$id'");

            if ($result) {
                header("Location: kelola_pasien.php?message=success&info=" . urlencode("Pasien berhasil diperbarui!"));
                exit();
            } else {
                header("Location: kelola_pasien.php?message=error&info=" . urlencode("Gagal memperbarui pasien!"));
                exit();
            }
        } else {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("Data pasien tidak ditemukan!"));
            exit();
        }
    }

    //HAPUS DATA
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $result = $conn->query("DELETE FROM pasien WHERE id='$id'");
        if ($result) {
            header("Location: kelola_pasien.php?message=success&info=" . urlencode("Pasien berhasil dihapus!"));
            exit();
        } else {
            header("Location: kelola_pasien.php?message=error&info=" . urlencode("Gagal menghapus pasien!"));
            exit();
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$pasienList = $conn->query("SELECT * FROM pasien WHERE nama LIKE '%$search%' OR no_ktp LIKE '%$search%' OR no_rm LIKE '%$search%' OR username LIKE '%$search%'");
if (!$pasienList) {
    die("Query gagal: " . $conn->error);
}

$adminName = $_SESSION['username'];

// If it's an AJAX request, only return the table rows
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $no = 1;
    ob_start(); // Start output buffering
    while ($row = $pasienList->fetch_assoc()): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= $row['id'] ?></td>
            <td class="nama-pasien"><?= htmlspecialchars($row['nama']) ?></td>
            <td class="alamat-pasien"><?= htmlspecialchars($row['alamat']) ?></td>
            <td><?= htmlspecialchars($row['no_ktp']) ?></td>
            <td><?= htmlspecialchars($row['no_hp']) ?></td>
            <td><?= htmlspecialchars($row['no_rm']) ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td>
                <div class="tombol-aksi">
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPatientModal<?= $row['id'] ?>">Edit</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endwhile;
    echo ob_get_clean(); 
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pasien - Admin</title>
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
        </div>

        <div class="container">
            <h1 class="mb-4">Kelola Pasien</h1>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex">
                    <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Pasien..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPatientModal"><i class="bi bi-person-fill-add"></i> Tambah Pasien</button>

            <!-- Tabel Pasien -->
            <table class="table-pasien table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>No KTP</th>
                        <th>No HP</th>
                        <th>No RM</th>
                        <th>Username</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $pasienList->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $row['id'] ?></td>
                        <td class="nama-pasien"><?= $row['nama'] ?></td>
                        <td class="alamat-pasien"><?= $row['alamat'] ?></td>
                        <td><?= $row['no_ktp'] ?></td>
                        <td><?= $row['no_hp'] ?></td>
                        <td><?= $row['no_rm'] ?></td>
                        <td><?= $row['username'] ?></td>
                        <td>
                            <div class="tombol-aksi">
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPatientModal<?= $row['id'] ?>">Edit</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                    <!-- Modal Edit -->
                    <div class="modal fade" id="editPatientModal<?= $row['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Pasien</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label>Nama</label>
                                            <input type="text" name="nama" class="form-control" value="<?= $row['nama'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>Alamat</label>
                                            <input type="text" name="alamat" class="form-control" value="<?= $row['alamat'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>No KTP</label>
                                            <input type="text" name="no_ktp" class="form-control" value="<?= $row['no_ktp'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>No HP</label>
                                            <input type="text" name="no_hp" class="form-control" value="<?= $row['no_hp'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>No RM</label>
                                            <input type="text" class="form-control readonly-input" value="<?= $row['no_rm'] ?>" disabled>
                                        </div>
                                        <div class="mb-3">
                                            <label>Username</label>
                                            <input type="text" name="username" class="form-control" value="<?= $row['username'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>Password (Kosongkan jika tidak ingin diubah)</label>
                                            <input type="password" name="password" class="form-control">
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

            <!-- Modal Tambah -->
            <div class="modal fade" id="addPatientModal" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Tambah Pasien</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label>Nama</label>
                                    <input type="text" name="nama" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Alamat</label>
                                    <input type="text" name="alamat" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>No KTP</label>
                                    <input type="number" name="no_ktp" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>No HP</label>
                                    <input type="number" name="no_hp" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>No RM</label>
                                    <input type="text" class="form-control readonly-input" value="<?= $no_rm_generate ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($_GET['message']) && isset($_GET['info'])): ?>
            <script>
                Swal.fire({
                    icon: '<?= $_GET['message'] === 'success' ? 'success' : ($_GET['message'] === 'warning' ? 'warning' : 'error') ?>',
                    title: '<?= htmlspecialchars($_GET['info']) ?>',
                    showConfirmButton: false,
                    timer: 2000 // Popup akan ditutup otomatis setelah 2 detik
                }).then(() => {
                    // Bersihkan URL setelah popup ditutup
                    window.history.replaceState(null, null, "kelola_pasien.php");
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
                const tableBody = document.querySelector('.table-pasien tbody');

                let timeoutId;
                searchInput.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        const searchTerm = this.value.toLowerCase();
                        
                        // Add X-Requested-With header to identify AJAX request
                        fetch(`kelola_pasien.php?search=${encodeURIComponent(searchTerm)}`, {
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
    </body>
    </html>

