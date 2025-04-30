<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data admin dari sesi
$adminUsername = $_SESSION['username'];
$query_admin = $conn->prepare("SELECT id, username FROM admin WHERE username = ?");
$query_admin->bind_param("s", $adminUsername);
$query_admin->execute();
$result_admin = $query_admin->get_result();

if ($result_admin->num_rows === 0) {
    echo "Data admin tidak ditemukan. Silakan login kembali.";
    exit();
}

$adminData = $result_admin->fetch_assoc();
$adminId = $adminData['id'];
$adminName = $adminData['username'];

// Cek apakah ID pesanan ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: pesanan.php");
    exit();
}

$pesanan_id = $_GET['id'];

// Ambil data pesanan
$query_pesanan = "SELECT p.*, pk.nama as nama_paket, pk.id as id_paket, pl.nama as nama_pelanggan, 
                 pl.id as id_pelanggan, pl.no_hp 
                 FROM pesanan p 
                 JOIN paket pk ON p.id_paket = pk.id 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                 WHERE p.id = $pesanan_id";
$result_pesanan = $conn->query($query_pesanan);

if ($result_pesanan->num_rows === 0) {
    echo "Pesanan tidak ditemukan.";
    exit();
}

$pesanan = $result_pesanan->fetch_assoc();

// Cek apakah pesanan masih dalam status 'diproses'
if ($pesanan['status'] !== 'diproses') {
    $_SESSION['error_message'] = "Hanya pesanan dengan status 'Diproses' yang dapat diedit.";
    header("Location: detail_pesanan.php?id=$pesanan_id");
    exit();
}

// Ambil data layanan antar jemput jika ada
$query_layanan = "SELECT * FROM antar_jemput WHERE id_pesanan = $pesanan_id";
$result_layanan = $conn->query($query_layanan);
$layanan = $result_layanan->fetch_assoc();

// Ambil semua paket
$query_paket = "SELECT * FROM paket ORDER BY nama ASC";
$result_paket = $conn->query($query_paket);

// Ambil semua pelanggan
$query_pelanggan = "SELECT * FROM pelanggan ORDER BY nama ASC";
$result_pelanggan = $conn->query($query_pelanggan);

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $id_pelanggan = $_POST['id_pelanggan'];
    $id_paket = $_POST['id_paket'];
    $berat = str_replace(',', '.', $_POST['berat']); // Ganti koma dengan titik
    $berat = floatval($berat); // Konversi ke float
    $berat = round($berat, 2); // Bulatkan ke 2 desimal
    
    // Ambil harga paket
    $query_harga = "SELECT harga FROM paket WHERE id = $id_paket";
    $result_harga = $conn->query($query_harga);
    $harga_paket = $result_harga->fetch_assoc()['harga'];
    
    // Hitung total harga
    $total_harga = $harga_paket * $berat;
    
    // Update data pesanan
    $update_query = "UPDATE pesanan SET 
                    id_pelanggan = $id_pelanggan,
                    id_paket = $id_paket,
                    berat = $berat,
                    harga = $total_harga
                    WHERE id = $pesanan_id";
    
    if ($conn->query($update_query)) {
        // Jika ada layanan antar jemput
        if (isset($_POST['layanan']) && !empty($_POST['layanan'])) {
            $layanan_type = $_POST['layanan'];
            $alamat_jemput = isset($_POST['alamat_jemput']) ? $conn->real_escape_string($_POST['alamat_jemput']) : '';
            $alamat_antar = isset($_POST['alamat_antar']) ? $conn->real_escape_string($_POST['alamat_antar']) : '';
            
            // Cek apakah sudah ada layanan
            if ($layanan) {
                // Update layanan yang ada
                $update_layanan = "UPDATE layanan_antar_jemput SET 
                                  layanan = '$layanan_type',
                                  alamat_jemput = '$alamat_jemput',
                                  alamat_antar = '$alamat_antar'
                                  WHERE id_pesanan = $pesanan_id";
                $conn->query($update_layanan);
            } else {
                // Tambah layanan baru
                $insert_layanan = "INSERT INTO layanan_antar_jemput 
                                  (id_pesanan, layanan, alamat_jemput, alamat_antar, status) 
                                  VALUES 
                                  ($pesanan_id, '$layanan_type', '$alamat_jemput', '$alamat_antar', 'menunggu')";
                $conn->query($insert_layanan);
            }
        } else if ($layanan) {
            // Hapus layanan jika tidak dipilih lagi
            $delete_layanan = "DELETE FROM layanan_antar_jemput WHERE id_pesanan = $pesanan_id";
            $conn->query($delete_layanan);
        }
        
        $_SESSION['success_message'] = "Pesanan berhasil diperbarui.";
        header("Location: detail_pesanan.php?id=$pesanan_id");
        exit();
    } else {
        $error_message = "Gagal memperbarui pesanan: " . $conn->error;
    }
}

// Format tanggal dalam bahasa Indonesia
function formatTanggalIndonesia($tanggal) {
    $hari = array(
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
    );
    
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    $hari_ini = $hari[date('w', $timestamp)];
    $tanggal_ini = date('j', $timestamp);
    $bulan_ini = $bulan[date('n', $timestamp)];
    $tahun_ini = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari_ini, $tanggal_ini $bulan_ini $tahun_ini $jam";
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));
$tanggal_pesanan = formatTanggalIndonesia($pesanan['waktu']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesanan - Admin Zeea Laundry</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.all.min.js"></script>
    <style>
        .edit-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .edit-header {
            background-color: #42c3cf;
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .edit-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .edit-body {
            padding: 25px;
            background-color: white;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
        }
        
        .back-button {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .btn-action {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
        }
        
        .btn-save {
            background-color: #42c3cf;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #38adb8;
            color: white;
        }
        
        .layanan-options {
            margin-bottom: 15px;
        }
        
        .alamat-container {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-top: 15px;
            display: none;
        }
        
        .price-calculation {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .price-calculation .row {
            margin-bottom: 10px;
        }
        
        .price-calculation .total-price {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
        }
        
        .price-calculation .price-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <div class="current-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
            </div>
            
            <div class="back-button">
                <a href="detail_pesanan.php?id=<?php echo $pesanan_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Detail Pesanan
                </a>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="edit-card">
                <div class="edit-header">
                    <h2>Edit Pesanan #<?php echo $pesanan_id; ?></h2>
                </div>
                
                <div class="edit-body">
                    <form method="POST" id="editForm">
                        <div class="form-section">
                            <h3><i class="fas fa-user-circle"></i> Informasi Pelanggan</h3>
                            <div class="mb-3">
                                <label for="id_pelanggan" class="form-label">Pilih Pelanggan</label>
                                <select class="form-select" id="id_pelanggan" name="id_pelanggan" required>
                                    <?php while ($pelanggan = $result_pelanggan->fetch_assoc()): ?>
                                        <option value="<?php echo $pelanggan['id']; ?>" <?php echo ($pelanggan['id'] == $pesanan['id_pelanggan']) ? 'selected' : ''; ?>>
                                            <?php echo $pelanggan['nama']; ?> (<?php echo $pelanggan['no_hp']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-box-open"></i> Detail Paket</h3>
                            <div class="mb-3">
                                <label for="id_paket" class="form-label">Pilih Paket</label>
                                <select class="form-select" id="id_paket" name="id_paket" required>
                                    <?php while ($paket = $result_paket->fetch_assoc()): ?>
                                        <option value="<?php echo $paket['id']; ?>" data-harga="<?php echo $paket['harga']; ?>" <?php echo ($paket['id'] == $pesanan['id_paket']) ? 'selected' : ''; ?>>
                                            <?php echo $paket['nama']; ?> - Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/kg
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="berat" class="form-label">Berat (kg)</label>
                                <input type="text" class="form-control" id="berat" name="berat" value="<?php echo number_format($pesanan['berat'], 2, ',', ''); ?>" required>
                                <div class="form-text">Gunakan koma untuk desimal. Contoh: 2,5</div>
                            </div>
                            
                            <div class="price-calculation">
                                <div class="row">
                                    <div class="col-md-6">
                                        <span class="price-label">Harga per kg:</span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span id="hargaPerKg">Rp <?php echo number_format($pesanan['harga_paket'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <span class="price-label">Berat:</span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span id="beratDisplay"><?php echo number_format($pesanan['berat'], 2, ',', '.'); ?> kg</span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <span class="price-label">Total Harga:</span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="total-price" id="totalHarga">Rp <?php echo number_format($pesanan['harga'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-truck"></i> Layanan Antar/Jemput</h3>
                            
                            <div class="layanan-options">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="layanan" id="tanpa_layanan" value="" <?php echo (!$layanan) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tanpa_layanan">
                                        Tanpa Layanan Antar/Jemput
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="layanan" id="jemput" value="jemput" <?php echo ($layanan && $layanan['layanan'] === 'jemput') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jemput">
                                        Layanan Jemput
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="layanan" id="antar" value="antar" <?php echo ($layanan && $layanan['layanan'] === 'antar') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="antar">
                                        Layanan Antar
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="layanan" id="antar_jemput" value="antar_jemput" <?php echo ($layanan && $layanan['layanan'] === 'antar_jemput') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="antar_jemput">
                                        Layanan Antar & Jemput
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alamat-container" id="jemputContainer">
                                <div class="mb-3">
                                    <label for="alamat_jemput" class="form-label">Alamat Jemput</label>
                                    <textarea class="form-control" id="alamat_jemput" name="alamat_jemput" rows="3"><?php echo ($layanan && $layanan['alamat_jemput']) ? $layanan['alamat_jemput'] : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="alamat-container" id="antarContainer">
                                <div class="mb-3">
                                    <label for="alamat_antar" class="form-label">Alamat Antar</label>
                                    <textarea class="form-control" id="alamat_antar" name="alamat_antar" rows="3"><?php echo ($layanan && $layanan['alamat_antar']) ? $layanan['alamat_antar'] : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="detail_pesanan.php?id=<?php echo $pesanan_id; ?>" class="btn btn-outline-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-save">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
        
        // Fungsi untuk menampilkan/menyembunyikan form alamat
        function toggleAlamatContainers() {
            const selectedLayanan = document.querySelector('input[name="layanan"]:checked').value;
            const jemputContainer = document.getElementById('jemputContainer');
            const antarContainer = document.getElementById('antarContainer');
            
            // Sembunyikan semua container terlebih dahulu
            jemputContainer.style.display = 'none';
            antarContainer.style.display = 'none';
            
            // Tampilkan container sesuai dengan layanan yang dipilih
            if (selectedLayanan === 'jemput' || selectedLayanan === 'antar_jemput') {
                jemputContainer.style.display = 'block';
            }
            
            if (selectedLayanan === 'antar' || selectedLayanan === 'antar_jemput') {
                antarContainer.style.display = 'block';
            }
        }
        
        // Fungsi untuk menghitung total harga
        function calculateTotalPrice() {
            const paketSelect = document.getElementById('id_paket');
            const selectedOption = paketSelect.options[paketSelect.selectedIndex];
            const hargaPerKg = parseInt(selectedOption.getAttribute('data-harga'));
            
            let berat = document.getElementById('berat').value;
            // Ganti koma dengan titik untuk perhitungan
            berat = berat.replace(',', '.');
            berat = parseFloat(berat);
            
            if (isNaN(berat)) berat = 0;
            
            // Bulatkan ke 2 desimal
            berat = Math.round(berat * 100) / 100;
            
            const totalHarga = hargaPerKg * berat;
            
            // Update tampilan
            document.getElementById('hargaPerKg').textContent = 'Rp ' + hargaPerKg.toLocaleString('id-ID');
            document.getElementById('beratDisplay').textContent = berat.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).replace('.', ',') + ' kg';
            document.getElementById('totalHarga').textContent = 'Rp ' + totalHarga.toLocaleString('id-ID');
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi tampilan alamat
            toggleAlamatContainers();
            
            // Event listener untuk radio buttons layanan
            const layananRadios = document.querySelectorAll('input[name="layanan"]');
            layananRadios.forEach(radio => {
                radio.addEventListener('change', toggleAlamatContainers);
            });
            
            // Event listener untuk perubahan paket atau berat
            document.getElementById('id_paket').addEventListener('change', calculateTotalPrice);
            document.getElementById('berat').addEventListener('input', calculateTotalPrice);
            
            // Validasi form sebelum submit
            document.getElementById('editForm').addEventListener('submit', function(e) {
                let berat = document.getElementById('berat').value;
                berat = berat.replace(',', '.');
                berat = parseFloat(berat);
                
                if (isNaN(berat) || berat <= 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Berat harus berupa angka positif.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Validasi alamat jika layanan dipilih
                const selectedLayanan = document.querySelector('input[name="layanan"]:checked').value;
                
                if (selectedLayanan === 'jemput' || selectedLayanan === 'antar_jemput') {
                    const alamatJemput = document.getElementById('alamat_jemput').value.trim();
                    if (alamatJemput === '') {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Error!',
                            text: 'Alamat jemput harus diisi.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                }
                
                if (selectedLayanan === 'antar' || selectedLayanan === 'antar_jemput') {
                    const alamatAntar = document.getElementById('alamat_antar').value.trim();
                    if (alamatAntar === '') {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Error!',
                            text: 'Alamat antar harus diisi.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                }
                
                // Konfirmasi perubahan
                e.preventDefault();
                Swal.fire({
                    title: 'Konfirmasi',
                    text: 'Apakah Anda yakin ingin menyimpan perubahan pada pesanan ini?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('editForm').submit();
                    }
                });
            });
        });
    </script>
</body>
</html>