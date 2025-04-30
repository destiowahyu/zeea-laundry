<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

$periksaPasien_page = 'periksa_pasien.php';

include '../includes/db.php';

// Ambil ID daftar_poli dari URL
$id_daftar = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id_daftar) {
    echo "ID daftar tidak valid. ";
    echo "Silahkan kembali ke halaman sebelumnya.";
    exit();
}

// Check if the patient has already been examined
$query_check = "SELECT dp.*, p.id AS id_periksa, p.tgl_periksa, p.catatan, p.biaya_periksa, pas.no_hp,
                       pas.nama AS nama_pasien, pas.no_rm
                FROM daftar_poli dp 
                LEFT JOIN periksa p ON dp.id = p.id_daftar_poli 
                JOIN pasien pas ON dp.id_pasien = pas.id
                WHERE dp.id = ?";
$stmt_check = $conn->prepare($query_check);
$stmt_check->bind_param("i", $id_daftar);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$data = $result_check->fetch_assoc();

$is_edit_mode = ($data['status'] === 'Sudah Diperiksa');

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $waktu_pemeriksaan = $_POST['waktu_pemeriksaan'];
    $catatan = $_POST['catatan'];
    $total_biaya = $_POST['total_biaya'];
    $obat_list = isset($_POST['obat']) ? json_decode($_POST['obat'], true) : [];

    if ($is_edit_mode) {
        // Update existing record
        $query = "UPDATE periksa SET tgl_periksa = ?, catatan = ?, biaya_periksa = ? WHERE id_daftar_poli = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdi", $waktu_pemeriksaan, $catatan, $total_biaya, $id_daftar);
        
        if ($stmt->execute()) {
            // Delete existing detail_periksa entries
            $delete_query = "DELETE FROM detail_periksa WHERE id_periksa = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $data['id_periksa']);
            $delete_stmt->execute();

            // Insert updated obat data
            if (!empty($obat_list)) {
                $query_detail = "INSERT INTO detail_periksa (id_periksa, id_obat, jumlah) VALUES (?, ?, ?)";
                $stmt_detail = $conn->prepare($query_detail);
                
                foreach ($obat_list as $obat) {
                    $stmt_detail->bind_param("iii", $data['id_periksa'], $obat['id'], $obat['jumlah']);
                    $stmt_detail->execute();
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Data pemeriksaan berhasil diperbarui.']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $conn->error]);
            exit();
        }
    } else {
        // Insert new record
        $query = "INSERT INTO periksa (id_daftar_poli, tgl_periksa, catatan, biaya_periksa) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issd", $id_daftar, $waktu_pemeriksaan, $catatan, $total_biaya);
    
        if ($stmt->execute()) {
            $id_periksa = $stmt->insert_id;
        
            // Insert data obat ke tabel detail_periksa
            if (!empty($obat_list)) {
                $query_detail = "INSERT INTO detail_periksa (id_periksa, id_obat, jumlah) VALUES (?, ?, ?)";
                $stmt_detail = $conn->prepare($query_detail);
            
                foreach ($obat_list as $obat) {
                    $stmt_detail->bind_param("iii", $id_periksa, $obat['id'], $obat['jumlah']);
                    $stmt_detail->execute();
                }
            }
        
            // Update status daftar_poli menjadi 'Sudah Diperiksa'
            $query_update = "UPDATE daftar_poli SET status = 'Sudah Diperiksa' WHERE id = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bind_param("i", $id_daftar);
            $stmt_update->execute();

            echo json_encode(['status' => 'success', 'message' => 'Data pemeriksaan berhasil disimpan.']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $conn->error]);
            exit();
        }
    }
}

// Ambil daftar obat
$query_obat = "SELECT id, nama_obat, kemasan, harga FROM obat";
$result_obat = $conn->query($query_obat);
$list_obat = $result_obat->fetch_all(MYSQLI_ASSOC);

// If in edit mode, fetch existing obat data
$existing_obat = [];
if ($is_edit_mode) {
    $query_existing_obat = "SELECT do.id_obat, o.nama_obat, o.kemasan, o.harga, do.jumlah 
                            FROM detail_periksa do 
                            JOIN obat o ON do.id_obat = o.id 
                            WHERE do.id_periksa = ?";
    $stmt_existing_obat = $conn->prepare($query_existing_obat);
    $stmt_existing_obat->bind_param("i", $data['id_periksa']);
    $stmt_existing_obat->execute();
    $result_existing_obat = $stmt_existing_obat->get_result();
    $existing_obat = $result_existing_obat->fetch_all(MYSQLI_ASSOC);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit_mode ? 'Edit' : 'Periksa' ?> Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4"><?= $is_edit_mode ? 'Edit' : 'Periksa' ?> Pasien</h1>
            <div class="welcome">
                Data Diri : <span><strong style="color: #42c3cf;"><?= htmlspecialchars($data['nama_pasien']) ?></strong></span>
            </div>

            <!-- Informasi Pasien -->
            <table class="table table-bordered mt-4">
                <tr>
                    <th>Nama Pasien</th>
                    <td><?= htmlspecialchars($data['nama_pasien']) ?></td>
                </tr>
                <tr>
                    <th>No RM</th>
                    <td><?= htmlspecialchars($data['no_rm']) ?></td>
                </tr>
                <tr>
                    <th>Keluhan</th>
                    <td><textarea class="form-control" rows="3" readonly><?= htmlspecialchars($data['keluhan'] ?? '-') ?></textarea></td>
                </tr>
                <tr>
                    <th>Waktu Pendaftaran</th>
                    <td><?= htmlspecialchars($data['created_at'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Nomor Antrian</th>
                    <td><?= htmlspecialchars($data['no_antrian'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>No HP</th>
                    <td><?= htmlspecialchars($data['no_hp'] ?? '-') ?></td>
                </tr>
            </table>

            <!-- Form Pemeriksaan -->
            <form id="periksaForm" action="<?= $_SERVER['PHP_SELF'] . '?id=' . $id_daftar ?>" method="POST">
                <input type="hidden" name="id_daftar" value="<?= $id_daftar ?>">

                <!-- Waktu Pemeriksaan -->
                <div class="mb-3">
                    <label for="waktu_pemeriksaan" class="form-label">Waktu Pemeriksaan</label>
                    <input type="datetime-local" name="waktu_pemeriksaan" id="waktu_pemeriksaan" class="form-control" required
                           value="<?= $is_edit_mode ? date('Y-m-d\TH:i', strtotime($data['tgl_periksa'])) : '' ?>">
                </div>

                <!-- Catatan Dokter -->
                <div class="mb-3">
                    <label for="catatan" class="form-label">Catatan Dokter</label>
                    <textarea name="catatan" id="catatan" rows="4" class="form-control" placeholder="Tulis catatan di sini" required><?= $is_edit_mode ? htmlspecialchars($data['catatan']) : '' ?></textarea>
                </div>

                <!-- Daftar Obat -->
                <div class="mb-3">
                    <label class="form-label"><strong>Tambahkan Obat :</strong></label>
                    <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#modalObat"><i class="bi bi-capsule"></i> Pilih Obat</button>
                    <div id="section-obat" style="display: none;">
                    <h4 class="form-label mt-3">Obat yang Diberikan : </h4>
                        <table class="table table-bordered" id="table-obat-selected">
                            <thead>
                                <tr>
                                    <th>Nama Obat</th>
                                    <th>Kemasan</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Obat yang dipilih akan ditambahkan di sini -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Total Biaya -->
                <div class="mb-3">
                    <label class="form-label">Detail Biaya</label>
                    <p>Biaya Pemeriksaan: Rp 150.000</p>
                    <p>Total Harga Obat: Rp <span id="total-harga-obat">0</span></p>
                    <p><strong>Total Biaya: Rp <span id="total-biaya">150000</span></strong></p>
                    <input type="hidden" name="total_biaya" id="input-total-biaya" value="150000">
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill"></i> Simpan</button>
                <a href="periksa_pasien.php" class="btn btn-secondary"><i class="bi bi-arrow-return-left"></i> Kembali</a>
            </form>
        </div>
    </div>

    <!-- Modal List Obat -->
    <div class="modal fade" id="modalObat" tabindex="-1" aria-labelledby="modalObatLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalObatLabel"><i class="bi bi-capsule"></i> Pilih Obat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Pencarian Obat -->
                    <div class="mb-3">
                        <input type="text" id="searchObat" class="form-control" placeholder="Cari Obat">
                    </div>
                    <!-- Tabel Obat -->
                    <table class="table table-bordered" id="tableObat">
                        <thead>
                            <tr>
                                <th>Nama Obat</th>
                                <th>Kemasan</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list_obat as $obat): ?>
                            <tr data-id="<?= $obat['id'] ?>">
                                <td><?= htmlspecialchars($obat['nama_obat']) ?></td>
                                <td><?= htmlspecialchars($obat['kemasan'] ?? 'Tidak ada informasi') ?></td>
                                <td><?= $obat['harga'] ?? 0 ?></td>
                                <td>
                                    <input type="number" class="form-control jumlah-obat" value="1" min="1">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-add-obat">
                                        Tambah
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
      <div id="notificationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
          <strong class="me-auto">Notifikasi</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="notificationMessage"></div>
      </div>
    </div>

    <script>
    $(document).ready(function () {
        const biayaPemeriksaan = 150000;
        let selectedObat = [];

        // Pre-populate data if in edit mode
        <?php if ($is_edit_mode): ?>
        <?php foreach ($existing_obat as $obat): ?>
        selectedObat.push({
            id: <?= $obat['id_obat'] ?>,
            nama: "<?= addslashes($obat['nama_obat']) ?>",
            harga: <?= $obat['harga'] ?>,
            jumlah: <?= $obat['jumlah'] ?>
        });
        <?php endforeach; ?>
        updateObatTable();
        updateTotal();

        // Update the button text for pre-selected medications
        selectedObat.forEach(obat => {
            $(`#tableObat tr[data-id="${obat.id}"] .btn-add-obat`)
                .text('Dipilih')
                .removeClass('btn-success')
                .addClass('btn-danger');
        });
        <?php endif; ?>

        // Filter pencarian obat
        $('#searchObat').on('keyup', function () {
            const value = $(this).val().toLowerCase();
            $('#tableObat tbody tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Tambahkan atau hapus obat ke tabel
        $(document).on('click', '.btn-add-obat', function () {
            const button = $(this);
            const row = button.closest('tr');
            const id = parseInt(row.data('id'));
            const nama = row.find('td:nth-child(1)').text();
            const kemasan = row.find('td:nth-child(2)').text();
            const harga = parseInt(row.find('td:nth-child(3)').text());
            const jumlahInput = row.find('.jumlah-obat');
            const jumlah = parseInt(jumlahInput.val());

            // Cek apakah obat sudah ada di tabel
            const existingIndex = selectedObat.findIndex(item => item.id === id);

            if (existingIndex === -1) {
                // Tambahkan obat baru ke tabel
                selectedObat.push({ id, nama, harga, jumlah });
                button.text('Dipilih').removeClass('btn-success').addClass('btn-danger');
            } else {
                // Hapus obat dari tabel jika sudah ada
                selectedObat.splice(existingIndex, 1);
                button.text('Tambah').removeClass('btn-danger').addClass('btn-success');
            }

            updateObatTable();
            updateTotal();
        });

        // Perbarui jumlah obat di tabel saat input diubah
        $(document).on('input', '.jumlah-obat', function () {
            const input = $(this);
            const row = input.closest('tr');
            const id = parseInt(row.data('id'));
            const jumlah = parseInt(input.val());

            // Update selectedObat array
            const index = selectedObat.findIndex(item => item.id === id);
            if (index !== -1) {
                selectedObat[index].jumlah = jumlah;
            }

            updateObatTable();
            updateTotal();
        });

        // Hapus obat dari tabel
        $(document).on('click', '.btn-remove-obat', function () {
            const row = $(this).closest('tr');
            const id = parseInt(row.data('id'));

            // Remove from selectedObat array
            const index = selectedObat.findIndex(item => item.id === id);
            if (index !== -1) {
                selectedObat.splice(index, 1);
            }

            updateObatTable();
            updateTotal();
        });

        // Perbarui tabel obat yang dipilih
        function updateObatTable() {
            const tbody = $('#table-obat-selected tbody');
            tbody.empty();

            selectedObat.forEach(obat => {
                tbody.append(`
                    <tr data-id="${obat.id}">
                        <td>${obat.nama}</td>
                        <td>${$(`#tableObat tr[data-id="${obat.id}"]`).find('td:nth-child(2)').text()}</td>
                        <td>${obat.harga}</td>
                        <td class="jumlah">${obat.jumlah}</td>
                        <td class="subtotal">${obat.harga * obat.jumlah}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-remove-obat">Hapus</button>
                        </td>
                    </tr>
                `);
            });

            $('#section-obat').toggle(selectedObat.length > 0);
        }

        // Perbarui total biaya
        function updateTotal() {
            let totalHargaObat = 0;

            selectedObat.forEach(obat => {
                totalHargaObat += obat.harga * obat.jumlah;
            });

            const totalBiaya = biayaPemeriksaan + totalHargaObat;
            $('#total-harga-obat').text(totalHargaObat.toLocaleString());
            $('#total-biaya').text(totalBiaya.toLocaleString());
            $('#input-total-biaya').val(totalBiaya);
        }

        function showNotification(message) {
            const toastEl = document.getElementById('notificationToast');
            const toast = new bootstrap.Toast(toastEl, { delay: 1200 });
            document.getElementById('notificationMessage').textContent = message;
            toast.show();
        }

        // Update the form submission to use AJAX
        $('#periksaForm').submit(function(e) {
            e.preventDefault();
            $('<input>').attr({
                type: 'hidden',
                name: 'obat',
                value: JSON.stringify(selectedObat)
            }).appendTo($(this));

            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showNotification(response.message);
                        setTimeout(function() {
                            window.location.href = 'periksa_pasien.php';
                        }, 1200);
                    } else {
                        showNotification(response.message);
                    }
                },
                error: function() {
                    showNotification('Terjadi kesalahan. Silakan coba lagi.');
                }
            });
        });
    });


    </script>
</body>
</html>

