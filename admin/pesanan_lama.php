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

// Handle AJAX requests for status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        $tracking_code = $_POST['tracking_code'];
        $status = $_POST['status'];
        $status_pembayaran = $_POST['status_pembayaran'];
        
        // Update all orders with the same tracking code
        $update_sql = "UPDATE pesanan SET status = ?, status_pembayaran = ? WHERE tracking_code = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sss", $status, $status_pembayaran, $tracking_code);
        
        if ($stmt->execute()) {
            // If status is completed, add to riwayat
            if ($status === 'selesai') {
                $tgl_selesai = date('Y-m-d');
                $get_orders = $conn->prepare("SELECT id, harga FROM pesanan WHERE tracking_code = ?");
                $get_orders->bind_param("s", $tracking_code);
                $get_orders->execute();
                $orders_result = $get_orders->get_result();
                
                while ($order = $orders_result->fetch_assoc()) {
                    $check_riwayat = $conn->prepare("SELECT id FROM riwayat WHERE id_pesanan = ?");
                    $check_riwayat->bind_param("i", $order['id']);
                    $check_riwayat->execute();
                    
                    if ($check_riwayat->get_result()->num_rows === 0) {
                        $insert_riwayat = $conn->prepare("INSERT INTO riwayat (id_pesanan, tgl_selesai, harga) VALUES (?, ?, ?)");
                        $insert_riwayat->bind_param("isd", $order['id'], $tgl_selesai, $order['harga']);
                        $insert_riwayat->execute();
                    }
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status']);
        }
        exit();
    }
}

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

// Inisialisasi variabel pencarian dan filter
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';

// PERBAIKAN: Filter periode waktu - jika ada filter manual, jangan gunakan periode
$period_filter = '';
$using_manual_date = !empty($date_from) || !empty($date_to);

if (!$using_manual_date) {
    $period_filter = isset($_GET['period']) ? $_GET['period'] : 'this_month'; // Default: bulan ini
}

// Fungsi untuk mendapatkan rentang tanggal berdasarkan periode
function getDateRangeByPeriod($period) {
    $today = date('Y-m-d');
    $current_month_start = date('Y-m-01');
    $current_month_end = date('Y-m-t');
    
    switch ($period) {
        case 'this_month':
            return [
                'from' => $current_month_start,
                'to' => $current_month_end,
                'label' => 'Bulan Ini'
            ];
        case 'two_months':
            $two_months_ago = date('Y-m-01', strtotime('-1 month'));
            return [
                'from' => $two_months_ago,
                'to' => $current_month_end,
                'label' => '2 Bulan Terakhir'
            ];
        case 'all':
            return [
                'from' => '',
                'to' => '',
                'label' => 'Semua Pesanan'
            ];
        case 'today':
            return [
                'from' => $today,
                'to' => $today,
                'label' => 'Hari Ini'
            ];
        case 'this_week':
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            return [
                'from' => $week_start,
                'to' => $week_end,
                'label' => 'Minggu Ini'
            ];
        case 'last_month':
            $last_month_start = date('Y-m-01', strtotime('-1 month'));
            $last_month_end = date('Y-m-t', strtotime('-1 month'));
            return [
                'from' => $last_month_start,
                'to' => $last_month_end,
                'label' => 'Bulan Lalu'
            ];
        default:
            return [
                'from' => $current_month_start,
                'to' => $current_month_end,
                'label' => 'Bulan Ini'
            ];
    }
}

// Dapatkan rentang tanggal berdasarkan periode yang dipilih (hanya jika tidak ada filter manual)
if (!$using_manual_date && !empty($period_filter)) {
    $period_range = getDateRangeByPeriod($period_filter);
    $date_from = $period_range['from'];
    $date_to = $period_range['to'];
} else if (!$using_manual_date) {
    // Default ke bulan ini jika tidak ada filter apapun
    $period_range = getDateRangeByPeriod('this_month');
    $date_from = $period_range['from'];
    $date_to = $period_range['to'];
    $period_filter = 'this_month';
} else {
    $period_range = ['label' => 'Filter Manual'];
}

// PERBAIKAN: Query untuk statistik berdasarkan tracking_code (bukan individual pesanan)
$stats_query = "SELECT 
    COUNT(DISTINCT p.tracking_code) as total_pesanan,
    SUM(CASE WHEN p.status = 'diproses' THEN 1 ELSE 0 END) as status_diproses_items,
    SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as status_selesai_items,
    COUNT(DISTINCT CASE WHEN p.status_pembayaran = 'sudah_dibayar' THEN p.tracking_code END) as sudah_dibayar,
    COUNT(DISTINCT CASE WHEN p.status_pembayaran = 'belum_dibayar' THEN p.tracking_code END) as belum_dibayar,
    SUM(p.harga) as total_nilai,
    COUNT(DISTINCT CASE WHEN p.status = 'diproses' THEN p.tracking_code END) as status_diproses,
    COUNT(DISTINCT CASE WHEN p.status = 'selesai' THEN p.tracking_code END) as status_selesai
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
WHERE 1=1";

// Tambahkan kondisi filter untuk statistik
if (!empty($search)) {
    $search_query = "%" . $search . "%";
    $stats_query .= " AND (pl.nama LIKE '$search_query' OR pl.no_hp LIKE '$search_query' OR p.id LIKE '$search_query' OR p.tracking_code LIKE '$search_query')";
}

if (!empty($date_from) && !empty($date_to)) {
    $stats_query .= " AND DATE(p.waktu) BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $stats_query .= " AND DATE(p.waktu) >= '$date_from'";
} elseif (!empty($date_to)) {
    $stats_query .= " AND DATE(p.waktu) <= '$date_to'";
}

if (!empty($status_filter)) {
    $stats_query .= " AND p.status = '$status_filter'";
}

if (!empty($payment_filter)) {
    $stats_query .= " AND p.status_pembayaran = '$payment_filter'";
}

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Buat query untuk mendapatkan pesanan yang dikelompokkan berdasarkan tracking ID
// PERBAIKAN: Menyesuaikan kueri GROUP BY dengan sql_mode=only_full_group_by
$query = "SELECT 
          p.tracking_code,
          MIN(p.id) as id,
          MIN(p.waktu) as waktu, -- Menggunakan MIN() atau MAX()
          MIN(p.id_pelanggan) as id_pelanggan,
          MIN(p.status) as status,
          MIN(p.status_pembayaran) as status_pembayaran,
          MIN(pl.nama) as nama_pelanggan,
          MIN(pl.no_hp) as no_hp,
          COUNT(p.id) as jumlah_item,
          SUM(p.harga) as total_harga,
          GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
          GROUP_CONCAT(p.berat SEPARATOR ', ') as berat_list
        FROM 
          pesanan p 
        JOIN 
          paket pk ON p.id_paket = pk.id 
        JOIN 
          pelanggan pl ON p.id_pelanggan = pl.id 
        WHERE 1=1";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
    $search_query = "%" . $search . "%";
    $query .= " AND (pl.nama LIKE '$search_query' OR pl.no_hp LIKE '$search_query' OR p.id LIKE '$search_query' OR p.tracking_code LIKE '$search_query')";
}

// Tambahkan filter tanggal jika ada
if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(p.waktu) BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $query .= " AND DATE(p.waktu) >= '$date_from'";
} elseif (!empty($date_to)) {
    $query .= " AND DATE(p.waktu) <= '$date_to'";
}

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
    $query .= " AND p.status = '$status_filter'";
}

// Tambahkan filter status pembayaran jika ada
if (!empty($payment_filter)) {
    $query .= " AND p.status_pembayaran = '$payment_filter'";
}

// Kelompokkan berdasarkan tracking ID. Semua kolom non-agregat harus ada di sini.
// Karena kita mengelompokkan berdasarkan tracking_code, dan kolom lain seperti
// id_pelanggan, status, status_pembayaran, nama_pelanggan, no_hp, dan waktu
// cenderung konsisten untuk setiap tracking_code, kita menggunakan MIN()
// untuk memilih satu nilai per grup, yang merupakan praktik umum untuk ONLY_FULL_GROUP_BY.
$query .= " GROUP BY p.tracking_code";

// Tambahkan pengurutan
// PERBAIKAN: Mengurutkan berdasarkan alias 'waktu' dari fungsi agregat MIN(p.waktu)
$query .= " ORDER BY waktu DESC";

// Eksekusi query
$result = $conn->query($query);

// Fungsi untuk mendapatkan badge status
function getStatusBadge($status) {
    switch ($status) {
        case 'diproses':
            return '<span class="badge bg-warning text-dark status-badge status-processing">Diproses</span>';
        case 'selesai':
            return '<span class="badge bg-success status-badge">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge bg-danger status-badge">Dibatalkan</span>';
        default:
            return '<span class="badge bg-secondary status-badge">Unknown</span>';
    }
}

// Fungsi untuk mendapatkan badge status pembayaran
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'belum_dibayar':
            return '<span class="badge bg-danger status-badge payment-unpaid">Belum Dibayar</span>';
        case 'sudah_dibayar':
            return '<span class="badge bg-success status-badge">Sudah Dibayar</span>';
        default:
            return '<span class="badge bg-secondary status-badge">Unknown</span>';
    }
}

// Fungsi untuk format tanggal singkat
function formatTanggalSingkat($tanggal) {
    $timestamp = strtotime($tanggal);
    return date('d/m/Y H:i', $timestamp);
}

// Fungsi untuk mendapatkan ringkasan paket
function getPaketSummary($paket_list, $berat_list) {
    $paket_array = explode(', ', $paket_list);
    $berat_array = explode(', ', $berat_list);
    
    $summary = '';
    for ($i = 0; $i < count($paket_array); $i++) {
        $summary .= $paket_array[$i] . ' (' . number_format((float)$berat_array[$i], 2, ',', '.') . ' kg)';
        if ($i < count($paket_array) - 1) {
            $summary .= '<br>';
        }
    }
    
    return $summary;
}

// Cek apakah ada filter aktif
$has_active_filters = !empty($search) || !empty($status_filter) || !empty($payment_filter) || $using_manual_date || ($period_filter !== 'this_month' && !$using_manual_date);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            margin-bottom: 2rem;
        }

        /* Statistics Dashboard */
        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
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
            height: 4px;
        }

        .stat-card.primary::before { background: #42c3cf; }
        .stat-card.success::before { background: #28a745; }
        .stat-card.warning::before { background: #ffc107; }
        .stat-card.info::before { background: #17a2b8; }
        .stat-card.danger::before { background: #dc3545; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #42c3cf, #36b5c0); }
        .stat-icon.success { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.warning { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .stat-icon.info { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-icon.danger { background: linear-gradient(135deg, #dc3545, #c82333); }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Add Order Button */
        .btn-add-order {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(66, 195, 207, 0.3);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-add-order:hover {
            background: linear-gradient(135deg, #38adb8, #2ea3ae);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(66, 195, 207, 0.4);
            color: white;
        }

        .btn-add-order i {
            font-size: 1.2rem;
        }

        /* PERBAIKAN: Period Filter Buttons dalam Filter Container */
        .period-filter-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .period-filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .period-filter-title i {
            margin-right: 0.5rem;
            color: #42c3cf;
        }

        .period-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .period-btn {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            padding: 0.5rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-btn:hover {
            background: rgba(66, 195, 207, 0.1);
            color: #42c3cf;
            border-color: #42c3cf;
            text-decoration: none;
        }

        .period-btn.active {
            background: #42c3cf;
            color: white;
            border-color: #42c3cf;
            box-shadow: 0 4px 15px rgba(66, 195, 207, 0.3);
        }

        .period-btn i {
            font-size: 0.85rem;
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

        /* Current Filter Info */
        .current-filter-info {
            background: linear-gradient(135deg, rgba(66, 195, 207, 0.1), rgba(66, 195, 207, 0.05));
            border: 1px solid rgba(66, 195, 207, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .current-filter-info h6 {
            color: #42c3cf;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .current-filter-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
        }

        .status-processing {
            animation: pulse-warning 2s infinite;
        }

        .payment-unpaid {
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-warning {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(255, 193, 7, 0); }
        }

        @keyframes pulse-danger {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
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

        .tracking-id-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-copy {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .btn-copy:hover {
            color: #42c3cf;
            background: rgba(66, 195, 207, 0.1);
        }

        .btn-copy.copied {
            color: #28a745;
            animation: pulse 0.5s;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .multiple-items-badge {
            background: #ff9800;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
            font-weight: bold;
        }

        .paket-list {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
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

        .price-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 1.1rem;
        }

        /* Quick Status Update Styles */
        .status-dropdown {
            min-width: 120px;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-dropdown:focus {
            border-color: #42c3cf;
            box-shadow: 0 0 0 0.2rem rgba(66, 195, 207, 0.25);
        }

        .status-dropdown.status-diproses {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .status-dropdown.status-selesai {
            background: #d1edff;
            border-color: #28a745;
        }

        .status-dropdown.payment-belum_dibayar {
            background: #f8d7da;
            border-color: #dc3545;
        }

        .status-dropdown.payment-sudah_dibayar {
            background: #d4edda;
            border-color: #28a745;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-action {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-action:hover {
            background: linear-gradient(135deg, #38adb8, #2ea3ae);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 195, 207, 0.3);
            color: white;
        }

        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
        }

        .btn-whatsapp:hover {
            background: linear-gradient(135deg, #128C7E, #075E54);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            color: white;
        }

        /* Mobile Cards */
        .mobile-cards {
            display: none;
        }

        .order-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .order-card-header {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            padding: 1rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-card-body {
            padding: 1rem;
        }

        .order-card-item {
            display: flex;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-card-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .order-card-label {
            font-weight: 600;
            min-width: 100px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .order-card-value {
            flex: 1;
        }

        .order-card-footer {
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #f0f0f0;
        }

        .mobile-tracking-container {
            background: rgba(66, 195, 207, 0.1);
            border: 1px dashed #42c3cf;
            border-radius: 8px;
            padding: 0.75rem;
            margin: 0.75rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-tracking-code {
            font-weight: 600;
            color: #42c3cf;
            font-size: 0.9rem;
        }

        .mobile-btn-copy {
            background: #42c3cf;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s ease;
        }

        .mobile-btn-copy:hover {
            background: #38adb8;
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

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #42c3cf;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .filter-container,
            .table-container {
                padding: 1.5rem;
            }

            .desktop-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .btn-add-order {
                width: 100%;
                justify-content: center;
                margin-bottom: 1.5rem;
            }

            .period-buttons {
                justify-content: center;
            }

            .period-btn {
                font-size: 0.8rem;
                padding: 0.4rem 1rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn-action,
            .btn-whatsapp {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
            }

            .order-card-item {
                flex-direction: column;
            }

            .order-card-label {
                margin-bottom: 0.25rem;
                min-width: auto;
            }

            .period-buttons {
                flex-direction: column;
            }

            .period-btn {
                justify-content: center;
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
            
            <!-- URUTAN BARU: 1. Judul -->
            <h1 class="page-title">Daftar Pesanan</h1>

            <!-- URUTAN BARU: 2. Tombol Tambah Pesanan -->
            <a href="tambah-pesanan.php" class="btn-add-order">
                <i class="fas fa-plus-circle"></i> Tambah Pesanan Baru
            </a>

            <!-- URUTAN BARU: 3. Statistics Dashboard -->
            <div class="stats-dashboard">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_pesanan'] ?? 0; ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['sudah_dibayar'] ?? 0; ?></div>
                    <div class="stat-label">Sudah Dibayar</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['status_diproses'] ?? 0; ?></div>
                    <div class="stat-label">Diproses</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['status_selesai'] ?? 0; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>

            <!-- Current Filter Info -->
            <?php if ($has_active_filters): ?>
            <div class="current-filter-info">
                <h6><i class="fas fa-info-circle me-2"></i>Filter Aktif</h6>
                <p>
                    <?php if ($using_manual_date): ?>
                        <strong>Periode:</strong> Filter Manual
                        <?php if (!empty($date_from) && !empty($date_to)): ?>
                            (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)
                        <?php elseif (!empty($date_from)): ?>
                            (Dari <?php echo date('d/m/Y', strtotime($date_from)); ?>)
                        <?php elseif (!empty($date_to)): ?>
                            (Sampai <?php echo date('d/m/Y', strtotime($date_to)); ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        <strong>Periode:</strong> <?php echo $period_range['label']; ?>
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>
                        | <strong>Pencarian:</strong> "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                    <?php if (!empty($status_filter)): ?>
                        | <strong>Status:</strong> <?php echo ucfirst($status_filter); ?>
                    <?php endif; ?>
                    <?php if (!empty($payment_filter)): ?>
                        | <strong>Pembayaran:</strong> <?php echo $payment_filter === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar'; ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- URUTAN BARU: 4. Filter Container (Gabungan) -->
            <div class="filter-container">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filter Pesanan
                </div>

                <!-- Period Filter Section -->
                <div class="period-filter-section">
                    <div class="period-filter-title">
                        <i class="fas fa-calendar-alt"></i>Filter Periode Cepat
                    </div>
                    <div class="period-buttons">
                        <a href="?period=today<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'today' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day"></i>Hari Ini
                        </a>
                        <a href="?period=this_week<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'this_week' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week"></i>Minggu Ini
                        </a>
                        <a href="?period=this_month<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'this_month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar"></i>Bulan Ini
                        </a>
                        <a href="?period=last_month<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'last_month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-minus"></i>Bulan Lalu
                        </a>
                        <a href="?period=two_months<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'two_months' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>2 Bulan Terakhir
                        </a>
                        <a href="?period=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($payment_filter) ? '&payment=' . urlencode($payment_filter) : ''; ?>" 
                           class="period-btn <?php echo $period_filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-infinity"></i>Semua Pesanan
                        </a>
                    </div>
                </div>
                
                <!-- Advanced Filter Form -->
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="mb-3">
                                <label for="search" class="form-label fw-semibold">Cari Pesanan</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Kode Tracking/Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rentang Tanggal Manual</label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control date-picker" name="date_from" placeholder="Dari" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                                    <input type="text" class="form-control date-picker" name="date_to" placeholder="Sampai" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                                </div>
                                <small class="text-muted">Akan menimpa filter periode di atas</small>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-4">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label fw-semibold">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Semua Status</option>
                                            <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="payment" class="form-label fw-semibold">Pembayaran</label>
                                        <select class="form-select" id="payment" name="payment">
                                            <option value="">Semua Status</option>
                                            <option value="belum_dibayar" <?php echo $payment_filter === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                            <option value="sudah_dibayar" <?php echo $payment_filter === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="pesanan.php" class="btn btn-outline-secondary">Reset Semua</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- URUTAN BARU: 5. Table Container -->
            <div class="table-container">
                <div class="table-title">
                    <span><i class="fas fa-table"></i>Daftar Pesanan</span>
                    <div class="text-muted">
                        Total: <?php echo $stats['total_pesanan']; ?> pesanan | 
                        Nilai: Rp <?php echo number_format($stats['total_nilai'] ?? 0, 0, ',', '.'); ?>
                    </div>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <!-- Desktop Table -->
                    <div class="desktop-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="60">No</th>
                                        <th width="180">Kode Tracking</th>
                                        <th width="120">Tanggal</th>
                                        <th width="150">Pelanggan</th>
                                        <th width="200">Paket</th>
                                        <th width="120">Total</th>
                                        <th width="120">Status</th>
                                        <th width="140">Pembayaran</th>
                                        <th width="160">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $result->data_seek(0);
                                    $no = 1;
                                    while ($row = $result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <?php if (!empty($row['tracking_code'])): ?>
                                                    <div class="tracking-id-container">
                                                        <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                                        <button type="button" class="btn-copy" data-clipboard-text="<?php echo $row['tracking_code']; ?>" title="Salin Kode Tracking">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatTanggalSingkat($row['waktu']); ?></td>
                                            <td>
                                                <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                                <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                            </td>
                                            <td>
                                                <?php if ($row['jumlah_item'] > 1): ?>
                                                    <div>
                                                        Beberapa Pesanan <span class="multiple-items-badge"><?php echo $row['jumlah_item']; ?></span>
                                                    </div>
                                                    <div class="paket-list">
                                                        <?php echo getPaketSummary($row['paket_list'], $row['berat_list']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php echo $row['paket_list']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="price-amount">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></div>
                                            </td>
                                            <td>
                                                <select class="status-dropdown status-<?php echo $row['status']; ?>" 
                                                        data-tracking="<?php echo $row['tracking_code']; ?>" 
                                                        data-type="status">
                                                    <option value="diproses" <?php echo $row['status'] === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                                    <option value="selesai" <?php echo $row['status'] === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                    <option value="dibatalkan" <?php echo $row['status'] === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="status-dropdown payment-<?php echo $row['status_pembayaran']; ?>" 
                                                        data-tracking="<?php echo $row['tracking_code']; ?>" 
                                                        data-type="payment">
                                                    <option value="belum_dibayar" <?php echo $row['status_pembayaran'] === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                                    <option value="sudah_dibayar" <?php echo $row['status_pembayaran'] === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="detail-pesanan.php?tracking=<?php echo urlencode($row['tracking_code']); ?>" class="btn-action" title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn-whatsapp" 
                                                            data-phone="<?php echo str_replace(['+', ' '], '', $row['no_hp']); ?>"
                                                            data-name="<?php echo htmlspecialchars($row['nama_pelanggan']); ?>"
                                                            data-tracking="<?php echo $row['tracking_code']; ?>"
                                                            data-status="<?php echo $row['status']; ?>"
                                                            data-payment="<?php echo $row['status_pembayaran']; ?>"
                                                            data-total="<?php echo number_format($row['total_harga'], 0, ',', '.'); ?>"
                                                            data-date="<?php echo formatTanggalIndonesia($row['waktu']); ?>"
                                                            data-paket="<?php echo htmlspecialchars($row['paket_list']); ?>"
                                                            data-berat="<?php echo $row['berat_list']; ?>"
                                                            title="WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Mobile Card View -->
                    <div class="mobile-cards">
                        <?php
                        $result->data_seek(0);
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <div class="order-card">
                                <div class="order-card-header">
                                    <div><strong>No. <?php echo $no++; ?></strong></div>
                                    <div><?php echo formatTanggalSingkat($row['waktu']); ?></div>
                                </div>
                                
                                <?php if (!empty($row['tracking_code'])): ?>
                                <div class="mobile-tracking-container">
                                    <div class="mobile-tracking-code"><?php echo $row['tracking_code']; ?></div>
                                    <button type="button" class="mobile-btn-copy" data-clipboard-text="<?php echo $row['tracking_code']; ?>">
                                        <i class="fas fa-copy"></i> Salin
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="order-card-body">
                                    <div class="order-card-item">
                                        <div class="order-card-label">Pelanggan:</div>
                                        <div class="order-card-value">
                                            <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                            <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Paket:</div>
                                        <div class="order-card-value">
                                            <?php if ($row['jumlah_item'] > 1): ?>
                                                <div>
                                                    Beberapa Pesanan <span class="multiple-items-badge"><?php echo $row['jumlah_item']; ?></span>
                                                </div>
                                                <div class="paket-list">
                                                    <?php echo getPaketSummary($row['paket_list'], $row['berat_list']); ?>
                                                </div>
                                            <?php else: ?>
                                                <?php echo $row['paket_list']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Total:</div>
                                        <div class="order-card-value">
                                            <div class="price-amount">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Status:</div>
                                        <div class="order-card-value">
                                            <select class="status-dropdown status-<?php echo $row['status']; ?>" 
                                                    data-tracking="<?php echo $row['tracking_code']; ?>" 
                                                    data-type="status">
                                                <option value="diproses" <?php echo $row['status'] === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                                <option value="selesai" <?php echo $row['status'] === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="dibatalkan" <?php echo $row['status'] === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Pembayaran:</div>
                                        <div class="order-card-value">
                                            <select class="status-dropdown payment-<?php echo $row['status_pembayaran']; ?>" 
                                                    data-tracking="<?php echo $row['tracking_code']; ?>" 
                                                    data-type="payment">
                                                <option value="belum_dibayar" <?php echo $row['status_pembayaran'] === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                                <option value="sudah_dibayar" <?php echo $row['status_pembayaran'] === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="order-card-footer">
                                    <div class="action-buttons">
                                        <a href="detail-pesanan.php?tracking=<?php echo urlencode($row['tracking_code']); ?>" class="btn-action">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <button type="button" class="btn-whatsapp" 
                                                data-phone="<?php echo str_replace(['+', ' '], '', $row['no_hp']); ?>"
                                                data-name="<?php echo htmlspecialchars($row['nama_pelanggan']); ?>"
                                                data-tracking="<?php echo $row['tracking_code']; ?>"
                                                data-status="<?php echo $row['status']; ?>"
                                                data-payment="<?php echo $row['status_pembayaran']; ?>"
                                                data-total="<?php echo number_format($row['total_harga'], 0, ',', '.'); ?>"
                                                data-date="<?php echo formatTanggalIndonesia($row['waktu']); ?>"
                                                data-paket="<?php echo htmlspecialchars($row['paket_list']); ?>"
                                                data-berat="<?php echo $row['berat_list']; ?>">
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-search"></i>
                        <h5>Tidak Ada Pesanan</h5>
                        <p>Tidak ada pesanan yang ditemukan untuk filter yang dipilih.</p>
                        <a href="tambah-pesanan.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tambah Pesanan Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date picker
            flatpickr(".date-picker", {
                dateFormat: "Y-m-d",
                locale: "id",
                allowInput: true,
                altInput: true,
                altFormat: "d F Y",
                maxDate: "today"
            });
            
            // Initialize clipboard.js for both desktop and mobile
            var clipboard = new ClipboardJS('.btn-copy, .mobile-btn-copy');
            
            clipboard.on('success', function(e) {
                const button = e.trigger;
                const originalTitle = button.getAttribute('title') || 'Salin';
                
                button.setAttribute('title', 'Tersalin!');
                button.classList.add('copied');
                
                // For mobile buttons
                if (button.classList.contains('mobile-btn-copy')) {
                    button.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
                    button.style.backgroundColor = '#28a745';
                }
                
                setTimeout(function() {
                    button.setAttribute('title', originalTitle);
                    button.classList.remove('copied');
                    
                    // Reset mobile button
                    if (button.classList.contains('mobile-btn-copy')) {
                        button.innerHTML = '<i class="fas fa-copy"></i> Salin';
                        button.style.backgroundColor = '';
                    }
                }, 1500);
                
                e.clearSelection();
            });

            // Handle status dropdown changes
            $('.status-dropdown').on('change', function() {
                const trackingCode = $(this).data('tracking');
                const type = $(this).data('type');
                const newValue = $(this).val();
                const $dropdown = $(this);
                
                // Get current values
                const $statusDropdown = $(`select[data-tracking="${trackingCode}"][data-type="status"]`);
                const $paymentDropdown = $(`select[data-tracking="${trackingCode}"][data-type="payment"]`);
                
                const currentStatus = $statusDropdown.val();
                const currentPayment = $paymentDropdown.val();
                
                // Show loading
                $dropdown.prop('disabled', true);
                
                // Send AJAX request
                $.ajax({
                    url: 'pesanan.php',
                    method: 'POST',
                    data: {
                        action: 'update_status',
                        tracking_code: trackingCode,
                        status: currentStatus,
                        status_pembayaran: currentPayment
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update dropdown classes
                            $statusDropdown.removeClass('status-diproses status-selesai status-dibatalkan')
                                          .addClass('status-' + currentStatus);
                            $paymentDropdown.removeClass('payment-belum_dibayar payment-sudah_dibayar')
                                           .addClass('payment-' + currentPayment);
                            
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            // Revert dropdown value
                            $dropdown.val($dropdown.data('original-value'));
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        // Revert dropdown value
                        $dropdown.val($dropdown.data('original-value'));
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Terjadi kesalahan saat memperbarui status'
                        });
                    },
                    complete: function() {
                        $dropdown.prop('disabled', false);
                    }
                });
            });

            // Store original values for revert functionality
            $('.status-dropdown').each(function() {
                $(this).data('original-value', $(this).val());
            });

            // Handle WhatsApp button clicks
            $('.btn-whatsapp').on('click', function() {
                const phone = $(this).data('phone');
                const name = $(this).data('name');
                const tracking = $(this).data('tracking');
                const status = $(this).data('status');
                const payment = $(this).data('payment');
                const total = $(this).data('total');
                const date = $(this).data('date');
                const paket = $(this).data('paket');
                const berat = $(this).data('berat');

                let message = '';

                if (status === 'diproses') {
                    // Message for processing orders
                    message = `*INI ADALAH PESAN OTOMATIS DARI SISTEM LAYANAN ZEEA LAUNDRY*

Halo *${name}*,

Pesanan laundry Anda telah masuk ke sistem dan sedang diproses.

*Detail Pesanan:*
- Kode Tracking: ${tracking}
- Status: Sedang Diproses
- Total: Rp ${total}

Kami akan menghubungi Anda kembali ketika pesanan sudah selesai.

Terima kasih telah menggunakan jasa Zeea Laundry.

Salam, *Zeea Laundry*
RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219
WhatsApp: 0895395442010`;
                } else if (status === 'selesai') {
                    // Message for completed orders
                    const paketArray = paket.split(', ');
                    const beratArray = berat.split(', ');
                    let paketDetails = '';
                    
                    for (let i = 0; i < paketArray.length; i++) {
                        paketDetails += `- ${paketArray[i]}: ${parseFloat(beratArray[i]).toFixed(2)} kg\n`;
                    }

                    message = `*INI ADALAH PESAN OTOMATIS DARI SISTEM LAYANAN ZEEA LAUNDRY*

Halo *${name}*,
Pesanan laundry Anda telah *SELESAI*:

*Detail Pesanan:*
- Kode Tracking: ${tracking}
- Tanggal Pesanan: ${date}

*Detail Paket:*
${paketDetails}
*Total Harga: Rp ${total}*
*Status Pembayaran: ${payment === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar'}*

Silakan ambil cucian Anda di toko kami.

Terima kasih telah menggunakan jasa Zeea Laundry.

Salam, *Zeea Laundry*
RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219
WhatsApp: 0895395442010`;
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Perhatian!',
                        text: 'WhatsApp hanya dapat dikirim untuk pesanan dengan status "Diproses" atau "Selesai".'
                    });
                    return;
                }

                // Open WhatsApp
                window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
            });
        });
    </script>
</body>
</html>
