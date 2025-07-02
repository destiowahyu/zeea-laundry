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

// Inisialisasi variabel filter
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES) : '';
$export_type = isset($_GET['export']) ? $_GET['export'] : '';

// Tentukan kondisi WHERE berdasarkan periode
$where_condition = "WHERE p.status_pembayaran = 'sudah_dibayar'"; // Hanya yang sudah dibayar
$date_condition = "";

if ($periode === 'harian') {
    // Tampilkan semua hari dalam bulan yang dipilih
    $date_condition = "MONTH(p.waktu) = MONTH('$tanggal') AND YEAR(p.waktu) = YEAR('$tanggal')";
    $periode_text = "Harian - " . date('F Y', strtotime($tanggal));
} else {
    // Tampilkan semua bulan dari Januari sampai bulan yang dipilih
    $date_condition = "YEAR(p.waktu) = YEAR('$bulan-01') AND MONTH(p.waktu) <= MONTH('$bulan-01')";
    $periode_text = "Bulanan - Januari sampai " . date('F Y', strtotime($bulan . '-01'));
}

$where_condition .= " AND $date_condition";

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
    $where_condition .= " AND p.status = '$status_filter'";
}

// Tambahkan pencarian jika ada
if (!empty($search)) {
    $search_query = "%" . $search . "%";
    $where_condition .= " AND (pl.nama LIKE '$search_query' OR p.tracking_code LIKE '$search_query' OR pl.no_hp LIKE '$search_query')";
}

// Query untuk data transaksi
$transaksi_query = "SELECT 
    p.id,
    p.tracking_code,
    p.harga,
    p.status,
    p.status_pembayaran,
    p.waktu,
    pl.nama as nama_pelanggan,
    pl.no_hp,
    GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
    COALESCE(aj.harga_custom, 5000) as harga_antar_jemput,
    aj.layanan as layanan_antar_jemput
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
JOIN paket pk ON p.id_paket = pk.id 
LEFT JOIN antar_jemput aj ON p.id = aj.id_pesanan
$where_condition
GROUP BY p.id
ORDER BY p.waktu DESC";

$transaksi_result = $conn->query($transaksi_query);

// HANDLE EXPORT - Jika ada parameter export, langsung export dan exit
if (!empty($export_type)) {
    if ($export_type === 'excel') {
        // Export to Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Laporan_Pemasukan_' . ($periode === 'harian' ? $tanggal : $bulan) . '.xls"');
        header('Cache-Control: max-age=0');
        
        echo '<html>';
        echo '<head><meta charset="UTF-8"></head>';
        echo '<body>';
        echo '<table border="1">';
        echo '<tr><td colspan="9" style="text-align: center; font-weight: bold; font-size: 16px;">LAPORAN PEMASUKAN ZEEA LAUNDRY</td></tr>';
        echo '<tr><td colspan="9" style="text-align: center; font-weight: bold;">' . $periode_text . '</td></tr>';
        echo '<tr><td colspan="9" style="text-align: center;">Dicetak pada: ' . date('d F Y H:i:s') . '</td></tr>';
        echo '<tr><td colspan="9"></td></tr>';
        
        // Header
        echo '<tr style="background-color: #42c3cf; color: white; font-weight: bold;">';
        echo '<td>No</td>';
        echo '<td>Tracking Code</td>';
        echo '<td>Nama Pelanggan</td>';
        echo '<td>No. HP</td>';
        echo '<td>Paket</td>';
        echo '<td>Harga</td>';
        echo '<td>Status</td>';
        echo '<td>Pembayaran</td>';
        echo '<td>Waktu</td>';
        echo '</tr>';
        
        // Data
        $no = 1;
        $total_pemasukan = 0;
        while ($data = $transaksi_result->fetch_assoc()) {
            $total_harga = $data['harga'];
            if (!empty($data['layanan_antar_jemput']) && $data['status_pembayaran'] === 'belum_dibayar') {
                $total_harga += $data['harga_antar_jemput'];
            }
            $total_pemasukan += $total_harga;
            
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $data['tracking_code'] . '</td>';
            echo '<td>' . $data['nama_pelanggan'] . '</td>';
            echo '<td>' . $data['no_hp'] . '</td>';
            echo '<td>' . $data['paket_list'] . '</td>';
            echo '<td>Rp ' . number_format($total_harga, 0, ',', '.') . '</td>';
            echo '<td>' . strip_tags(getStatusBadge($data['status'])) . '</td>';
            echo '<td>' . ($data['status_pembayaran'] === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar') . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($data['waktu'])) . '</td>';
            echo '</tr>';
        }
        
        // Total
        echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
        echo '<td colspan="5">TOTAL PEMASUKAN</td>';
        echo '<td>Rp ' . number_format($total_pemasukan, 0, ',', '.') . '</td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit();
        
    } elseif ($export_type === 'pdf') {
        // Export to PDF - Simple HTML to PDF
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Laporan Pemasukan</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h2 { margin: 5px 0; color: #42c3cf; }
                .header h3 { margin: 5px 0; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #42c3cf; color: white; font-weight: bold; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>LAPORAN PEMASUKAN ZEEA LAUNDRY</h2>
                <h3><?php echo $periode_text; ?></h3>
                <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">Tracking</th>
                        <th width="15%">Pelanggan</th>
                        <th width="12%">No. HP</th>
                        <th width="20%">Paket</th>
                        <th width="12%">Harga</th>
                        <th width="10%">Status</th>
                        <th width="14%">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $total_pemasukan = 0;
                    $transaksi_result->data_seek(0);
                    while ($data = $transaksi_result->fetch_assoc()): 
                        $total_harga = $data['harga'];
                        if (!empty($data['layanan_antar_jemput']) && $data['status_pembayaran'] === 'belum_dibayar') {
                            $total_harga += $data['harga_antar_jemput'];
                        }
                        $total_pemasukan += $total_harga;
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo $data['tracking_code']; ?></td>
                            <td><?php echo $data['nama_pelanggan']; ?></td>
                            <td><?php echo $data['no_hp']; ?></td>
                            <td><?php echo $data['paket_list']; ?></td>
                            <td class="text-right">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></td>
                            <td class="text-center"><?php echo strip_tags(getStatusBadge($data['status'])); ?></td>
                            <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($data['waktu'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td colspan="5" class="text-center">TOTAL PEMASUKAN</td>
                        <td class="text-right">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        // Output PDF menggunakan browser print
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        echo '<script>window.print();</script>';
        exit();
    }
}

// Jika bukan export, lanjutkan dengan tampilan normal
// Query untuk statistik
$stats_query = "SELECT 
    COUNT(*) as total_transaksi,
    SUM(p.harga) as total_pemasukan,
    AVG(p.harga) as rata_rata_transaksi,
    SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as transaksi_selesai,
    SUM(CASE WHEN p.status = 'selesai' THEN p.harga ELSE 0 END) as pemasukan_selesai,
    SUM(CASE WHEN p.status_pembayaran = 'sudah_dibayar' THEN 1 ELSE 0 END) as sudah_dibayar,
    SUM(CASE WHEN p.status_pembayaran = 'sudah_dibayar' THEN p.harga ELSE 0 END) as total_sudah_dibayar
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
$where_condition";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Query untuk data grafik
if ($periode === 'harian') {
    // Data semua hari dalam bulan yang dipilih
    $chart_query = "SELECT 
        DATE(p.waktu) as tanggal,
        SUM(p.harga) as total_harga,
        COUNT(*) as jumlah_transaksi
    FROM pesanan p 
    WHERE MONTH(p.waktu) = MONTH('$tanggal') AND YEAR(p.waktu) = YEAR('$tanggal') AND p.status_pembayaran = 'sudah_dibayar'
    GROUP BY DATE(p.waktu)
    ORDER BY DATE(p.waktu)";
} else {
    // Data semua bulan dari Januari sampai bulan yang dipilih
    $chart_query = "SELECT 
        DATE_FORMAT(p.waktu, '%Y-%m') as bulan,
        SUM(p.harga) as total_harga,
        COUNT(*) as jumlah_transaksi
    FROM pesanan p 
    WHERE YEAR(p.waktu) = YEAR('$bulan-01') AND MONTH(p.waktu) <= MONTH('$bulan-01') AND p.status_pembayaran = 'sudah_dibayar'
    GROUP BY DATE_FORMAT(p.waktu, '%Y-%m')
    ORDER BY DATE_FORMAT(p.waktu, '%Y-%m')";
}

$chart_result = $conn->query($chart_query);
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}

// Reset result pointer untuk tampilan
$transaksi_result = $conn->query($transaksi_query);

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

// Fungsi format tanggal
function formatTanggalSingkat($tanggal) {
    return date('d/m/Y H:i', strtotime($tanggal));
}

// Fungsi format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi badge status
function getStatusBadge($status) {
    // Bersihkan status dari whitespace dan ubah ke lowercase
    $status = trim(strtolower($status));
    
    switch ($status) {
        case 'menunggu':
            return '<span class="badge bg-warning text-dark">Menunggu</span>';
        case 'proses':
        case 'diproses':
        case 'sedang diproses':
            return '<span class="badge bg-info">Proses</span>';
        case 'selesai':
        case 'completed':
            return '<span class="badge bg-success">Selesai</span>';
        case 'dibatalkan':
        case 'cancelled':
        case 'batal':
            return '<span class="badge bg-danger">Dibatalkan</span>';
        default:
            // Debug: tampilkan status asli untuk troubleshooting
            return '<span class="badge bg-secondary" title="Status: ' . htmlspecialchars($status) . '">Unknown (' . htmlspecialchars($status) . ')</span>';
    }
}

// Fungsi badge pembayaran
function getPembayaranBadge($status) {
    switch ($status) {
        case 'belum_dibayar':
            return '<span class="badge bg-danger">Belum Dibayar</span>';
        case 'sudah_dibayar':
            return '<span class="badge bg-success">Sudah Dibayar</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pemasukan - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #42c3cf;
            margin-bottom: 1rem;
        }

        .periode-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        /* Statistics Dashboard */
        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }

        .stat-card.primary::before { background: #42c3cf; }
        .stat-card.success::before { background: #28a745; }
        .stat-card.info::before { background: #17a2b8; }
        .stat-card.warning::before { background: #ffc107; }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #42c3cf, #36b5c0); }
        .stat-icon.success { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.info { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-icon.warning { background: linear-gradient(135deg, #ffc107, #e0a800); }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-sublabel {
            font-size: 0.8rem;
            color: #adb5bd;
            margin-top: 0.5rem;
        }

        /* Filter Container */
        .filter-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .filter-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        .periode-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .periode-tab {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .periode-tab.active {
            background: #42c3cf;
            color: white;
            box-shadow: 0 2px 10px rgba(66, 195, 207, 0.3);
        }

        .periode-tab:hover:not(.active) {
            background: rgba(66, 195, 207, 0.1);
            color: #42c3cf;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .export-buttons i {
            color: #fff;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-export.excel {
            background: #28a745;
            color: white;
        }

        .btn-export.pdf {
            background: #dc3545;
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            color: white;
        }

        .table th {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
        }

        .table th:first-child {
            border-top-left-radius: 15px;
        }

        .table th:last-child {
            border-top-right-radius: 15px;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tr:hover {
            background-color: rgba(66, 195, 207, 0.05);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .tracking-id {
            font-weight: 600;
            color: #42c3cf;
            background: rgba(66, 195, 207, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px dashed #42c3cf;
            font-size: 0.85rem;
            display: inline-block;
        }

        .price-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 1.1rem;
        }

        .customer-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .customer-phone {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .paket-info {
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.4;
        }

        /* Mobile Cards */
        .mobile-cards {
            display: none;
        }

        .transaction-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }

        .transaction-card-header {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            padding: 1rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .transaction-card-body {
            padding: 1rem;
        }

        .transaction-item {
            display: flex;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .transaction-label {
            font-weight: 600;
            min-width: 100px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .transaction-value {
            flex: 1;
        }

        .no-data {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .stats-dashboard {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem 1rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .filter-container,
            .chart-container,
            .table-container {
                padding: 1.5rem;
            }

            .desktop-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .export-buttons {
                flex-direction: column;
                width: 100%;
            }

            .table-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
            }

            .periode-tabs {
                flex-direction: column;
                gap: 0.5rem;
            }

            .transaction-item {
                flex-direction: column;
            }

            .transaction-label {
                margin-bottom: 0.25rem;
                min-width: auto;
            }
        }

        @media (min-width: 769px) {
            .desktop-table {
                display: block;
            }

            .mobile-cards {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar-admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <div class="current-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
            </div>
            
            <h1 class="page-title">Laporan Pemasukan</h1>
            <div class="periode-subtitle">
                <i class="fas fa-chart-line me-2"></i><?php echo $periode_text; ?>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-dashboard">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['total_pemasukan'] ?? 0); ?></div>
                    <div class="stat-label">Total Pemasukan</div>
                    <div class="stat-sublabel">Semua Status</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['total_sudah_dibayar'] ?? 0); ?></div>
                    <div class="stat-label">Sudah Dibayar</div>
                    <div class="stat-sublabel"><?php echo $stats['sudah_dibayar'] ?? 0; ?> Transaksi</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_transaksi'] ?? 0; ?></div>
                    <div class="stat-label">Total Transaksi</div>
                    <div class="stat-sublabel"><?php echo $stats['transaksi_selesai'] ?? 0; ?> Selesai</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['rata_rata_transaksi'] ?? 0); ?></div>
                    <div class="stat-label">Rata-rata</div>
                    <div class="stat-sublabel">Per Transaksi</div>
                </div>
            </div>

            <!-- Filter Container -->
            <div class="filter-container">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filter Laporan
                </div>
                
                <form method="GET" action="" id="filterForm">
                    <div class="periode-tabs">
                        <button type="button" class="periode-tab <?php echo $periode === 'harian' ? 'active' : ''; ?>" onclick="setPeriode('harian')">
                            <i class="fas fa-calendar-day me-2"></i>Harian
                        </button>
                        <button type="button" class="periode-tab <?php echo $periode === 'bulanan' ? 'active' : ''; ?>" onclick="setPeriode('bulanan')">
                            <i class="fas fa-calendar-alt me-2"></i>Bulanan
                        </button>
                    </div>
                    
                    <input type="hidden" name="periode" id="periodeInput" value="<?php echo $periode; ?>">
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <?php echo $periode === 'harian' ? 'Pilih Tanggal' : 'Pilih Bulan'; ?>
                                </label>
                                <?php if ($periode === 'harian'): ?>
                                    <input type="date" class="form-control" name="tanggal" value="<?php echo $tanggal; ?>" max="<?php echo date('Y-m-d'); ?>">
                                <?php else: ?>
                                    <input type="month" class="form-control" name="bulan" value="<?php echo $bulan; ?>" max="<?php echo date('Y-m'); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status Pesanan</label>
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="menunggu" <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="proses" <?php echo $status_filter === 'proses' ? 'selected' : ''; ?>>Proses</option>
                                    <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="dibatalkan" <?php echo $status_filter === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cari Transaksi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Tracking/Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Chart Container -->
            <div class="chart-container">
                <div class="chart-title">
                    <span><i class="fas fa-chart-line"></i>Trend Pemasukan</span>
                </div>
                <div style="height: 400px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-title">
                    <span><i class="fas fa-table"></i>Detail Transaksi</span>
                    <div class="export-buttons">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn-export excel" target="_blank">
                            <i class="fas fa-file-excel"></i>Excel
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn-export pdf" target="_blank">
                            <i class="fas fa-file-pdf"></i>PDF
                        </a>
                    </div>
                </div>

                <?php if ($transaksi_result->num_rows > 0): ?>
                    <!-- Desktop Table -->
                    <div class="desktop-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th width="120">Tracking</th>
                                        <th width="150">Pelanggan</th>
                                        <th width="200">Paket</th>
                                        <th width="120">Harga</th>
                                        <th width="100">Status</th>
                                        <th width="120">Pembayaran</th>
                                        <th width="150">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $transaksi_result->data_seek(0);
                                    $no = 1;
                                    while ($row = $transaksi_result->fetch_assoc()): 
                                        $total_harga = $row['harga'];
                                        if (!empty($row['layanan_antar_jemput'])) {
                                            if ($row['status_pembayaran'] === 'belum_dibayar') {
                                                $total_harga += $row['harga_antar_jemput'];
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                            </td>
                                            <td>
                                                <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                                <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                            </td>
                                            <td>
                                                <div class="paket-info"><?php echo htmlspecialchars($row['paket_list']); ?></div>
                                                <?php if (!empty($row['layanan_antar_jemput'])): ?>
                                                    <small class="text-info">+ <?php echo ucfirst($row['layanan_antar_jemput']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="price-amount"><?php echo formatRupiah($total_harga); ?></div>
                                                <?php if (!empty($row['layanan_antar_jemput']) && $row['status_pembayaran'] === 'belum_dibayar'): ?>
                                                    <small class="text-muted">
                                                        Pesanan: <?php echo formatRupiah($row['harga']); ?><br>
                                                        <?php echo ucfirst($row['layanan_antar_jemput']); ?>: <?php echo formatRupiah($row['harga_antar_jemput']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($row['status']); ?></td>
                                            <td><?php echo getPembayaranBadge($row['status_pembayaran']); ?></td>
                                            <td><?php echo formatTanggalSingkat($row['waktu']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-cards">
                        <?php 
                        $transaksi_result->data_seek(0);
                        $no = 1;
                        while ($row = $transaksi_result->fetch_assoc()): 
                            $total_harga = $row['harga'];
                            if (!empty($row['layanan_antar_jemput'])) {
                                if ($row['status_pembayaran'] === 'belum_dibayar') {
                                    $total_harga += $row['harga_antar_jemput'];
                                }
                            }
                        ?>
                            <div class="transaction-card">
                                <div class="transaction-card-header">
                                    <div><strong>No. <?php echo $no++; ?></strong></div>
                                    <div><?php echo getStatusBadge($row['status']); ?></div>
                                </div>
                                
                                <div class="transaction-card-body">
                                    <div class="transaction-item">
                                        <div class="transaction-label">Tracking:</div>
                                        <div class="transaction-value">
                                            <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Pelanggan:</div>
                                        <div class="transaction-value">
                                            <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                            <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Paket:</div>
                                        <div class="transaction-value">
                                            <div class="paket-info"><?php echo htmlspecialchars($row['paket_list']); ?></div>
                                            <?php if (!empty($row['layanan_antar_jemput'])): ?>
                                                <small class="text-info">+ <?php echo ucfirst($row['layanan_antar_jemput']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Harga:</div>
                                        <div class="transaction-value">
                                            <div class="price-amount"><?php echo formatRupiah($total_harga); ?></div>
                                            <?php if (!empty($row['layanan_antar_jemput']) && $row['status_pembayaran'] === 'belum_dibayar'): ?>
                                                <small class="text-muted">
                                                    Pesanan: <?php echo formatRupiah($row['harga']); ?><br>
                                                    <?php echo ucfirst($row['layanan_antar_jemput']); ?>: <?php echo formatRupiah($row['harga_antar_jemput']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Pembayaran:</div>
                                        <div class="transaction-value"><?php echo getPembayaranBadge($row['status_pembayaran']); ?></div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Waktu:</div>
                                        <div class="transaction-value"><?php echo formatTanggalSingkat($row['waktu']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-chart-line"></i>
                        <h5>Tidak Ada Data Transaksi</h5>
                        <p>Tidak ada transaksi yang ditemukan untuk periode yang dipilih.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart Data
        const chartData = <?php echo json_encode($chart_data); ?>;
        const periode = '<?php echo $periode; ?>';
        
        // Setup Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => {
                    if (periode === 'harian') {
                        const date = new Date(item.tanggal);
                        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
                    } else {
                        const date = new Date(item.bulan + '-01');
                        return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
                    }
                }),
                datasets: [{
                    label: 'Pemasukan',
                    data: chartData.map(item => item.total_harga),
                    borderColor: '#42c3cf',
                    backgroundColor: 'rgba(66, 195, 207, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#42c3cf',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }, {
                    label: 'Jumlah Transaksi',
                    data: chartData.map(item => item.jumlah_transaksi),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#42c3cf',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Pemasukan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                } else {
                                    return 'Transaksi: ' + context.parsed.y + ' pesanan';
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            },
                            font: {
                                size: 11
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return value + ' transaksi';
                            },
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Functions
        function setPeriode(newPeriode) {
            document.getElementById('periodeInput').value = newPeriode;
            document.getElementById('filterForm').submit();
        }

        // Auto submit form when date changes
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"], input[type="month"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            });
        });
    </script>
</body>
</html>
