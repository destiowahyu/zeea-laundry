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
        $nama_paket = $_POST['nama_paket'];
        $harga = $_POST['harga'];

        // Handle file upload
        $icon = '';
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
            // Validasi ekstensi file
            $allowed_extensions = ['png', 'jpg', 'jpeg'];
            $file_extension = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $allowed_extensions)) {
                $upload_dir = '../assets/uploads/paket_icons/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
                }

                // Generate nama file unik dan move file ke folder tujuan
                $icon = uniqid('icon_') . '.' . $file_extension;
                move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon);
            } else {
                $message = 'Hanya file gambar (PNG, JPG, JPEG) yang diperbolehkan!';
                $type = 'error';
            }
        }

        if ($icon) {
            // Menambahkan data paket ke database dengan icon yang diupload
            $result = $conn->query("INSERT INTO paket (nama, harga, icon) VALUES ('$nama_paket', '$harga', '$icon')");
            if ($result) {
                $message = 'Paket berhasil ditambahkan!';
                $type = 'success';
            } else {
                $message = 'Gagal menambahkan paket!';
                $type = 'error';
            }
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_paket = $_POST['nama_paket'];
        $harga = $_POST['harga'];

        // Jika ada file icon yang diupload, gunakan file tersebut
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
            $icon = ''; // Reset jika mengupload file baru
            // Validasi ekstensi file
            $allowed_extensions = ['png', 'jpg', 'jpeg'];
            $file_extension = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $allowed_extensions)) {
                $upload_dir = '../assets/uploads/paket_icons/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
                }

                // Generate nama file unik dan move file ke folder tujuan
                $icon = uniqid('icon_') . '.' . $file_extension;
                move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon);
            } else {
                $message = 'Hanya file gambar (PNG, JPG, JPEG) yang diperbolehkan!';
                $type = 'error';
            }
        } else {
            // Jika tidak ada file yang diupload, gunakan icon lama
            $icon = $_POST['current_icon']; // Ambil icon lama dari input tersembunyi
        }

        // Update paket dengan atau tanpa perubahan icon
        $result = $conn->query("UPDATE paket SET nama='$nama_paket', harga='$harga', icon='$icon' WHERE id='$id'");
        if ($result) {
            $message = 'Paket berhasil diperbarui!';
            $type = 'success';
        } else {
            $message = 'Gagal memperbarui paket!';
            $type = 'error';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];

        // Ambil icon yang ada sebelum dihapus
        $result = $conn->query("SELECT icon FROM paket WHERE id='$id'");
        $paket = $result->fetch_assoc();

        // Jika icon ada, hapus file icon
        if ($paket && !empty($paket['icon'])) {
            $icon_path = '../assets/uploads/paket_icons/' . $paket['icon'];
            if (file_exists($icon_path)) {
                unlink($icon_path); // Menghapus file gambar
            }
        }

        // Hapus paket dari database
        $result = $conn->query("DELETE FROM paket WHERE id='$id'");
        if ($result) {
            $message = 'Paket berhasil dihapus!';
            $type = 'success';
        } else {
            $message = 'Gagal menghapus paket!';
            $type = 'error';
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$paketList = $conn->query("SELECT * FROM paket WHERE nama LIKE '%$search%' OR harga LIKE '%$search%'");
if (!$paketList) {
    die("Query gagal: " . $conn->error);
}

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// If it's an AJAX request, only return the table rows
if ($isAjax) {
    $no = 1;
    while ($row = $paketList->fetch_assoc()) {
        echo "<tr>
                <td>{$no}</td>
                <td>{$row['nama']}</td>
                <td>Rp " . number_format($row['harga'], 0, ',', '.') . "</td>
                <td><img src='../assets/uploads/paket_icons/{$row['icon']}' width='50px' alt='{$row['nama']}'></td>
                <td>
                    <button class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editPaketModal{$row['id']}'>Edit</button>
                    <button class='btn btn-danger btn-sm delete-btn' data-id='{$row['id']}'>Hapus</button>
                </td>
            </tr>";
        $no++;
    }
    exit; // Stop execution after sending AJAX response
}

$adminName = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Paket - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>

    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Kelola Paket Laundry</h1>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex">
                    <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Paket..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPaketModal"><i class="fas fa-box"></i> Tambah Paket</button>

            <table class="table-paket table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Paket</th>
                        <th>Harga</th>
                        <th>Icon</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="paketTableBody">
                    <?php 
                    $no = 1;
                    while ($row = $paketList->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                            <td><img src="../assets/uploads/paket_icons/<?= $row['icon'] ?>" width="50px" alt="<?= $row['nama'] ?>"></td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPaketModal<?= $row['id'] ?>">Edit</button>
                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $row['id'] ?>">Hapus</button>
                            </td>
                        </tr>

                        <div class="modal fade" id="editPaketModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editPaketModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="current_icon" value="<?= $row['icon'] ?>"> <!-- Keep old icon value -->
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editPaketModalLabel">Edit Paket</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label>Nama Paket</label>
                                                <input type="text" name="nama_paket" class="form-control" value="<?= $row['nama'] ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label>Harga</label>
                                                <input type="number" name="harga" class="form-control" value="<?= $row['harga'] ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label>Icon (Upload File)</label>
                                                <input type="file" name="icon" class="form-control" accept="image/*">
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

        <div class="modal fade" id="addPaketModal" tabindex="-1" aria-labelledby="addPaketModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPaketModalLabel">Tambah Paket</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Nama Paket</label>
                                <input type="text" name="nama_paket" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Harga</label>
                                <input type="number" name="harga" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Icon (Upload File)</label>
                                <input type="file" name="icon" class="form-control" accept="image/*" required>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // SweetAlert2 for delete confirmation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize delete buttons
            function initDeleteButtons() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        Swal.fire({
                            title: 'Apakah Anda yakin?',
                            text: "Anda akan menghapus paket ini!",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, Hapus!',
                            cancelButtonText: 'Batal',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = '';
                                form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete">`;
                                document.body.appendChild(form);
                                form.submit();
                            }
                        });
                    });
                });
            }

            // Initialize delete buttons on page load
            initDeleteButtons();

            // Real-time search
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('paketTableBody');

            let timeoutId;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    const searchTerm = this.value.toLowerCase();

                    // Add X-Requested-With header to identify AJAX request
                    fetch(`kelola_paket.php?search=${encodeURIComponent(searchTerm)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Update the table body with the search results
                        tableBody.innerHTML = html;
                        // Reinitialize delete buttons for the new content
                        initDeleteButtons();
                    })
                    .catch(error => console.error('Error:', error));
                }, 300); // Add debounce delay of 300ms
            });
        });
    </script>
</body>
</html>