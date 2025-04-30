<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Ambil ID pendaftaran pasien dari URL
$id_daftar = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data pendaftaran pasien
$query = "
    SELECT dp.id, p.nama AS nama_pasien, dp.keluhan, dp.created_at, p.no_rm, dp.status
    FROM daftar_poli dp
    JOIN pasien p ON dp.id_pasien = p.id
    WHERE dp.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_daftar);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Data pendaftaran tidak ditemukan.'); window.history.back();</script>";
    exit();
}

$daftarData = $result->fetch_assoc();
$isSudahDiperiksa = $daftarData['status'] === 'Sudah Diperiksa';

// Ambil data pemeriksaan jika sudah diperiksa
$periksaData = null;
if ($isSudahDiperiksa) {
    $query_periksa = "
        SELECT tgl_periksa, catatan, biaya_periksa
        FROM periksa
        WHERE id_daftar_poli = ?
    ";
    $stmt_periksa = $conn->prepare($query_periksa);
    $stmt_periksa->bind_param("i", $id_daftar);
    $stmt_periksa->execute();
    $result_periksa = $stmt_periksa->get_result();
    if ($result_periksa->num_rows > 0) {
        $periksaData = $result_periksa->fetch_assoc();
    }
}

// Ambil obat yang sudah dipilih jika sudah diperiksa
$selected_obat_ids = [];
if ($isSudahDiperiksa) {
    $query_obat = "
        SELECT id_obat
        FROM detail_periksa
        WHERE id_periksa = (
            SELECT id FROM periksa WHERE id_daftar_poli = ?
        )
    ";
    $stmt_obat = $conn->prepare($query_obat);
    $stmt_obat->bind_param("i", $id_daftar);
    $stmt_obat->execute();
    $result_obat = $stmt_obat->get_result();
    while ($row = $result_obat->fetch_assoc()) {
        $selected_obat_ids[] = $row['id_obat'];
    }
}

// Ambil daftar obat dari database
$result_obat = $conn->query("SELECT id, nama_obat, harga FROM obat");
$obat_list = [];
while ($row = $result_obat->fetch_assoc()) {
    $obat_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Periksa Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Detail Periksa Pasien</h1>

    <!-- Informasi Pasien -->
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h4>Informasi Pasien</h4>
        </div>
        <div class="card-body">
            <p><strong>Nama Pasien:</strong> <?= htmlspecialchars($daftarData['nama_pasien']) ?></p>
            <p><strong>Nomor Rekam Medis:</strong> <?= htmlspecialchars($daftarData['no_rm']) ?></p>
            <p><strong>Waktu Pendaftaran:</strong> <?= htmlspecialchars($daftarData['created_at']) ?></p>
            <p><strong>Keluhan:</strong> <?= htmlspecialchars($daftarData['keluhan']) ?></p>
        </div>
    </div>

    <!-- Form Pemeriksaan -->
    <form method="post" class="mt-4">
        <div class="mb-3">
            <label for="tgl_periksa" class="form-label">Waktu Pemeriksaan:</label>
            <input type="datetime-local" id="tgl_periksa" name="tgl_periksa"
                   class="form-control" value="<?= $isSudahDiperiksa ? date('Y-m-d\TH:i', strtotime($periksaData['tgl_periksa'])) : '' ?>" required>
        </div>
        <div class="mb-3">
            <label for="catatan" class="form-label">Catatan Dokter:</label>
            <textarea id="catatan" name="catatan" class="form-control" rows="3" required><?= $isSudahDiperiksa ? htmlspecialchars($periksaData['catatan']) : '' ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Obat:</label>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#obatModal">
                Pilih Obat
            </button>
            <ul id="selected-obat-list" class="list-group mt-3">
                <!-- Obat yang dipilih akan muncul di sini -->
            </ul>
        </div>
        <div class="mb-3">
            <label class="form-label">Total Biaya:</label>
            <h5>Biaya Periksa: <span id="biaya-periksa">Rp 150,000</span></h5>
            <h5>Biaya Obat: <span id="biaya-obat">Rp 0</span></h5>
            <h5>Total Biaya: <span id="total-biaya">Rp 150,000</span></h5>
        </div>
        <button type="submit" class="btn btn-success w-100">Simpan</button>
    </form>
</div>

<!-- Modal Obat -->
<div class="modal fade" id="obatModal" tabindex="-1" aria-labelledby="obatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="obatModalLabel">Pilih Obat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="search-obat" class="form-control mb-3" placeholder="Cari Obat">
                <ul id="obat-list" class="list-group">
                    <?php foreach ($obat_list as $obat): ?>
                        <li class="list-group-item">
                            <input type="checkbox" class="form-check-input obat-checkbox" 
                                   data-id="<?= $obat['id'] ?>" 
                                   data-nama="<?= htmlspecialchars($obat['nama_obat']) ?>" 
                                   data-harga="<?= $obat['harga'] ?>" 
                                   <?= in_array($obat['id'], $selected_obat_ids) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($obat['nama_obat']) ?> (Rp <?= number_format($obat['harga']) ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        const selectedObat = new Map();

        // Update obat yang dipilih saat checkbox diubah
        $('.obat-checkbox').on('change', function () {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            const harga = parseInt($(this).data('harga'));

            if ($(this).is(':checked')) {
                selectedObat.set(id, { nama, harga });
            } else {
                selectedObat.delete(id);
            }
            updateSelectedObatList();
            calculateTotal();
        });

        // Update tampilan obat yang dipilih
        function updateSelectedObatList() {
            const list = $('#selected-obat-list');
            list.empty();
            selectedObat.forEach((obat) => {
                list.append(`<li class="list-group-item">${obat.nama} (Rp ${obat.harga.toLocaleString('id-ID')})</li>`);
            });
        }

        // Hitung total biaya
        function calculateTotal() {
            let totalBiayaObat = 0;
            selectedObat.forEach(obat => totalBiayaObat += obat.harga);
            const total = 150000 + totalBiayaObat;

            $('#biaya-obat').text('Rp ' + totalBiayaObat.toLocaleString('id-ID'));
            $('#total-biaya').text('Rp ' + total.toLocaleString('id-ID'));
        }

        // Filter obat berdasarkan pencarian
        $('#search-obat').on('input', function () {
            const searchValue = $(this).val().toLowerCase();
            $('#obat-list .list-group-item').filter(function () {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchValue));
            });
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
