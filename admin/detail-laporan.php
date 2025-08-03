<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$tracking_code = isset($_GET['tracking']) ? $_GET['tracking'] : '';

if (empty($tracking_code)) {
    header("Location: laporan-pemasukan.php");
    exit();
}

// Query untuk mendapatkan detail pesanan
$query = "SELECT 
    p.id,
    p.tracking_code,
    p.berat,
    p.harga,
    p.status,
    p.status_pembayaran,
    p.waktu,
    pl.nama as nama_pelanggan,
    pl.no_hp,
    pk.nama as nama_paket,
    pk.harga as harga_paket,
    pk.keterangan as keterangan_paket,
    aj.layanan as layanan_antar_jemput,
    aj.alamat_antar,
    aj.alamat_jemput,
    aj.harga as harga,
    aj.status as status_antar_jemput,
    aj.waktu_antar,
    aj.waktu_jemput
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
JOIN paket pk ON p.id_paket = pk.id 
LEFT JOIN antar_jemput aj ON p.id = aj.id_pesanan
WHERE p.tracking_code = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $tracking_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: laporan-pemasukan.php");
    exit();
}

$pesanan = $result->fetch_assoc();

// Ambil semua pesanan dengan tracking code yang sama
$query_all = "SELECT 
    p.id,
    p.berat,
    p.harga,
    p.harga_custom,
    pk.nama as nama_paket,
    pk.harga as harga_paket,
    pk.keterangan as keterangan_paket
FROM pesanan p 
JOIN paket pk ON p.id_paket = pk.id 
WHERE p.tracking_code = ?
ORDER BY p.id ASC";

$stmt_all = $conn->prepare($query_all);
$stmt_all->bind_param("s", $tracking_code);
$stmt_all->execute();
$result_all = $stmt_all->get_result();

$all_items = [];
$total_laundry = 0;
while ($item = $result_all->fetch_assoc()) {
    $all_items[] = $item;
    $total_laundry += $item['harga'];
}

// Hitung total harga
$total_harga = $total_laundry;
$harga_antar_jemput = 0;
if (!empty($pesanan['layanan_antar_jemput'])) {
    $harga_antar_jemput = $pesanan['harga'] ?? 5000;
    $total_harga += $harga_antar_jemput;
}

// Format functions
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function formatTanggalLengkap($tanggal) {
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
    
    return "$hari_ini, $tanggal_ini $bulan_ini $tahun_ini pukul $jam WIB";
}

function getStatusBadge($status) {
    $status = trim(strtolower($status));
    
    switch ($status) {
        case 'diproses':
            return '<span class="badge bg-warning text-dark fs-6">Sedang Diproses</span>';
        case 'selesai':
            return '<span class="badge bg-success fs-6">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge bg-danger fs-6">Dibatalkan</span>';
        default:
            return '<span class="badge bg-secondary fs-6">Unknown</span>';
    }
}

function getPembayaranBadge($status) {
    switch ($status) {
        case 'belum_dibayar':
            return '<span class="badge bg-danger fs-6">Belum Dibayar</span>';
        case 'sudah_dibayar':
            return '<span class="badge bg-success fs-6">Sudah Dibayar</span>';
        default:
            return '<span class="badge bg-secondary fs-6">Unknown</span>';
    }
}

$tanggal_sekarang = formatTanggalLengkap(date('Y-m-d H:i:s'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan <?php echo $pesanan['tracking_code']; ?> - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <style>
        .content {
            padding: 60px 80px;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 60px 30px;
            }
        }
        
        .detail-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .detail-header {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .detail-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .detail-body {
            padding: 25px;
            background-color: white;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .detail-section h3 i {
            margin-right: 10px;
            color: #42c3cf;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        .status-badges {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 10px;
        }
        
        .action-buttons .btn {
            flex: 1;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
        }
        
        .btn-whatsapp:hover {
            background-color: #128C7E;
            color: white;
        }
        
        .btn-print {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
        }
        
        .price-highlight {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
        }
        
        .customer-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .customer-info i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            color: #42c3cf;
        }
        
        .back-button {
            margin-bottom: 20px;
        }
        
        .paket-item {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #42c3cf;
        }
        
        .paket-item:last-child {
            margin-bottom: 0;
        }
        
        .paket-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .paket-item-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }
        
        .paket-item-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .paket-item-label {
            color: #666;
        }
        
        .paket-item-value {
            font-weight: 500;
        }
        
        .total-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        
        .tracking-code-container {
            background-color: rgba(66, 195, 207, 0.1);
            border: 2px dashed #42c3cf;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .tracking-code-label {
            font-size: 14px;
            color: #495057;
            margin-bottom: 5px;
            display: block;
        }
        
        .tracking-code-value {
            font-size: 18px;
            font-weight: bold;
            color: #42c3cf !important;
            letter-spacing: 1px;
            word-break: break-all;
            max-width: 100%;
            background-color: transparent !important;
            border: none !important;
            padding: 0 !important;
        }
        
        .btn-copy {
            white-space: nowrap;
            background-color: white;
            color: rgb(81, 81, 81);
            border: 1px solid #42c3cf;
            border-radius: 5px;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 16px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-copy:hover {
            transform: scale(1.1);
        }
        
        .btn-copy.copied {
            color: #42c3cf;
            animation: pulse 0.5s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .antar-jemput-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .price-breakdown {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .price-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 18px;
            color: #28a745;
            border-top: 2px solid #28a745;
            margin-top: 8px;
            padding-top: 15px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .detail-card {
                box-shadow: none;
                margin: 0;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
        }

        /* Mobile styles */
        @media (max-width: 576px) {
            .detail-header {
                padding: 15px;
            }
            
            .detail-header h2 {
                font-size: 18px;
                margin-bottom: 25px;
            }
            
            .status-badges {
                position: static;
                margin-top: 10px;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .detail-body {
                padding: 15px;
            }
            
            .detail-section h3 {
                font-size: 16px;
            }
            
            .paket-item {
                padding: 12px;
            }
            
            .paket-item-header {
                flex-direction: column;
            }
            
            .paket-item-id {
                margin-top: 5px;
                font-size: 12px;
            }
            
            .paket-item-details {
                flex-direction: column;
                margin-bottom: 8px;
            }
            
            .paket-item-value {
                margin-top: 2px;
            }
            
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 12px;
            }
            
            .detail-value {
                text-align: left;
                margin-top: 3px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .customer-info p {
                margin-bottom: 8px;
            }
            
            .total-section {
                padding: 12px;
            }
            
            .total-row {
                font-size: 16px;
            }
            
            .price-highlight {
                font-size: 20px;
            }
            
            .tracking-code-container {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
                background-color: rgba(66, 195, 207, 0.1) !important;
            }
            
            .tracking-code-container > div {
                flex: 1 1 auto;
                min-width: 200px;
            }
            
            .tracking-code-value {
                font-size: 16px;
                word-break: break-all;
                background-color: transparent !important;
                padding: 8px 0;
                border-radius: 5px;
                display: inline-block;
                max-width: 100%;
                color: #42c3cf !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar-admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="current-date no-print">
            <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
        </div>

        <div class="back-button no-print">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Laporan
            </a>
        </div>
        
        <div class="detail-card">
            <div class="detail-header">
                <h2>Detail Laporan Pesanan</h2>
                <div class="status-badges">
                    <?php echo getStatusBadge($pesanan['status']); ?>
                    <?php echo getPembayaranBadge($pesanan['status_pembayaran']); ?>
                </div>
            </div>
            
            <div class="detail-body">
                <!-- Tracking Code -->
                <div class="tracking-code-container">
                    <div>
                        <span class="tracking-code-label">Kode Tracking:</span>
                        <div class="tracking-code-value"><?php echo $pesanan['tracking_code']; ?></div>
                    </div>
                    <button type="button" class="btn-copy" data-clipboard-text="<?php echo $pesanan['tracking_code']; ?>" title="Salin Kode Tracking">
                        <i class="fas fa-copy"></i> Salin
                    </button>
                </div>
                
                <!-- Informasi Pelanggan -->
                <div class="detail-section">
                    <h3><i class="fas fa-user-circle"></i> Informasi Pelanggan</h3>
                    <div class="customer-info">
                        <p><i class="fas fa-user"></i> <strong>Nama:</strong> <?php echo htmlspecialchars($pesanan['nama_pelanggan']); ?></p>
                        <p><i class="fas fa-phone"></i> <strong>No. HP:</strong> <?php echo $pesanan['no_hp']; ?></p>
                    </div>
                </div>

                <!-- Detail Paket -->
                <div class="detail-section">
                    <h3><i class="fas fa-box-open"></i> Detail Paket Laundry</h3>
                    
                    <?php if (count($all_items) > 0): ?>
                        <?php foreach ($all_items as $index => $item): ?>
                            <div class="paket-item">
                                <div class="paket-item-header">
                                    <div class="paket-item-title"><?php echo htmlspecialchars($item['nama_paket']); ?></div>
                                   
                                </div>
                                <div class="paket-item-details">
                                    <span class="paket-item-label">Harga per Kg:</span>
                                    <span class="paket-item-value"><?php 
                                        if ($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) {
                                            echo formatRupiah($item['harga_custom']);
                                        } else {
                                            echo formatRupiah($item['harga_paket']);
                                        }
                                    ?></span>
                                </div>
                                <div class="paket-item-details">
                                    <span class="paket-item-label">Berat:</span>
                                    <span class="paket-item-value"><?php echo number_format($item['berat'], 2, ',', '.'); ?> kg</span>
                                </div>
                                <div class="paket-item-details">
                                    <span class="paket-item-label">Total Harga:</span>
                                    <span class="paket-item-value"><?php echo formatRupiah($item['harga']); ?></span>
                                </div>
                               
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted">Tidak ada data paket tersedia.</p>
                    <?php endif; ?>
                </div>

                <!-- Informasi Pesanan -->
                <div class="detail-section">
                    <h3><i class="fas fa-info-circle"></i> Informasi Pesanan</h3>
                    <div class="detail-item">
                        <span class="detail-label">Tanggal Pesanan:</span>
                        <span class="detail-value"><?php echo formatTanggalLengkap($pesanan['waktu']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status Pesanan:</span>
                        <span class="detail-value"><?php echo getStatusBadge($pesanan['status']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status Pembayaran:</span>
                        <span class="detail-value"><?php echo getPembayaranBadge($pesanan['status_pembayaran']); ?></span>
                    </div>
                </div>

                <!-- Layanan Antar Jemput -->
                <?php if (!empty($pesanan['layanan_antar_jemput'])): ?>
                    <div class="detail-section">
                        <h3><i class="fas fa-truck"></i> Layanan Antar Jemput</h3>
                        <div class="antar-jemput-info">
                            <div class="detail-item">
                                <span class="detail-label">Jenis Layanan:</span>
                                <span class="detail-value"><?php echo ucfirst(str_replace('-', ' ', $pesanan['layanan_antar_jemput'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Biaya Layanan:</span>
                                <span class="detail-value"><?php echo formatRupiah($harga_antar_jemput); ?></span>
                            </div>
                            <?php if (!empty($pesanan['alamat_antar'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Alamat Antar:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['alamat_antar']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($pesanan['alamat_jemput'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Alamat Jemput:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['alamat_jemput']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Rincian Harga -->
                <div class="detail-section">
                    <h3><i class="fas fa-calculator"></i> Rincian Pembayaran</h3>
                    <div class="price-breakdown">
                        <div class="price-item">
                            <span>Total Biaya Laundry</span>
                            <span><?php echo formatRupiah($total_laundry); ?></span>
                        </div>
                        
                        <?php if (!empty($pesanan['layanan_antar_jemput'])): ?>
                            <div class="price-item">
                                <span>Biaya <?php echo ucfirst(str_replace('-', ' ', $pesanan['layanan_antar_jemput'])); ?></span>
                                <span><?php echo formatRupiah($harga_antar_jemput); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="price-item">
                            <span>TOTAL PEMBAYARAN</span>
                            <span><?php echo formatRupiah($total_harga); ?></span>
                        </div>
                    </div>
                </div>

               
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize clipboard.js
        var clipboard = new ClipboardJS('.btn-copy');

        clipboard.on('success', function(e) {
            const button = e.trigger;
            const originalTitle = button.getAttribute('title');

            button.setAttribute('title', 'Tersalin!');
            button.classList.add('copied');

            setTimeout(function() {
                button.setAttribute('title', originalTitle);
                button.classList.remove('copied');
            }, 1500);

            e.clearSelection();
        });

       
    </script>
</body>
</html>
