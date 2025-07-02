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

// Function to generate a unique tracking code
function generateTrackingCode($conn, $customer_id) {
    // Format: ZL + YearMonthDay + 3-digit customer ID + 3 random alphanumeric
    $date_part = date('ymd');
    $customer_part = str_pad($customer_id, 3, '0', STR_PAD_LEFT);
    
    // Generate random alphanumeric suffix (3 characters)
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_suffix = '';
    for ($i = 0; $i < 3; $i++) {
        $random_suffix .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    $tracking_code = "ZL{$date_part}{$customer_part}{$random_suffix}";
    
    // Check if tracking code already exists
    $stmt = $conn->prepare("SELECT id FROM pesanan WHERE tracking_code = ?");
    $stmt->bind_param("s", $tracking_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If exists, regenerate with a different random suffix
    if ($result->num_rows > 0) {
        return generateTrackingCode($conn, $customer_id);
    }
    
    return $tracking_code;
}

// Proses form jika ada request AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Set header to return JSON response
    header('Content-Type: application/json');
    
    // Get form data
    $nama_pelanggan = $_POST['nama_pelanggan'];
    $no_hp = $_POST['no_hp'];
    
    // Format nomor HP untuk WhatsApp (tambahkan +62)
    if (substr($no_hp, 0, 1) === '0') {
        // Jika nomor HP dimulai dengan 0, ganti dengan +62
        $no_hp = '+62' . substr($no_hp, 1);
    } elseif (substr($no_hp, 0, 3) !== '+62') {
        // Jika tidak dimulai dengan +62, tambahkan +62
        $no_hp = '+62' . $no_hp;
    }
    
    $paket_items = isset($_POST['paket_items']) ? json_decode($_POST['paket_items'], true) : [];
    
    // Validate required fields
    if (empty($nama_pelanggan) || empty($no_hp) || empty($paket_items)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Semua field harus diisi dan minimal satu paket harus dipilih'
        ]);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if customer exists or create new one
        $pelanggan_id = $_POST['pelanggan_id'] ?? '';
        
        if (empty($pelanggan_id)) {
            // Check if customer with same phone number exists
            $stmt = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ?");
            $stmt->bind_param("s", $no_hp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $pelanggan = $result->fetch_assoc();
                $pelanggan_id = $pelanggan['id'];
                
                // Update customer name if needed
                $stmt = $conn->prepare("UPDATE pelanggan SET nama = ? WHERE id = ?");
                $stmt->bind_param("si", $nama_pelanggan, $pelanggan_id);
                $stmt->execute();
            } else {
                // Create new customer
                $stmt = $conn->prepare("INSERT INTO pelanggan (nama, no_hp) VALUES (?, ?)");
                $stmt->bind_param("ss", $nama_pelanggan, $no_hp);
                $stmt->execute();
                $pelanggan_id = $conn->insert_id;
            }
        }
        
        // Generate a unique tracking code for this order group
        $tracking_code = generateTrackingCode($conn, $pelanggan_id);
        
        // Create separate orders for each package item
        $total_harga = 0;
        $pesanan_ids = [];
        
        foreach ($paket_items as $item) {
            $berat = round(floatval($item['berat']), 2); // Round to 2 decimal places
            $paket_id = $item['paket_id'];
            $total_harga_item = floatval($item['total_harga']);
            $total_harga += $total_harga_item;
            
            // Check if paket custom already exists in database
            if ($paket_id === 'custom') {
                // Find existing custom package
                $stmt = $conn->prepare("SELECT id FROM paket WHERE nama = 'Paket Khusus'");
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Use existing custom package
                    $paket = $result->fetch_assoc();
                    $paket_id = $paket['id'];
                } else {
                    // Create new custom package if it doesn't exist
                    $custom_name = "Paket Khusus";
                    $custom_price = 0; // Default price 0, actual price will be stored in pesanan table
                    $custom_icon = "custom.png";
                    
                    // Copy icon file if it doesn't exist
                    $source_path = "../assets/images/custom.png";
                    $destination_path = "../assets/uploads/paket_icons/custom.png";
                    
                    if (!file_exists($destination_path) && file_exists($source_path)) {
                        copy($source_path, $destination_path);
                    }
                    
                    // Create custom package entry once
                    $stmt = $conn->prepare("INSERT INTO paket (nama, harga, keterangan, icon) VALUES (?, ?, 'Paket dengan harga kustom', ?)");
                    $stmt->bind_param("sds", $custom_name, $custom_price, $custom_icon);
                    $stmt->execute();
                    
                    $paket_id = $conn->insert_id;
                }
            }
            
            // Create order record with tracking code
            $status = 'diproses'; // Default status
            $waktu = date('Y-m-d H:i:s');
            
            // Add harga_custom for custom price
            $harga_custom = ($item['paket_id'] === 'custom') ? floatval($item['harga_per_kg']) : 0;
            
            // Insert order with tracking code
            $stmt = $conn->prepare("INSERT INTO pesanan (tracking_code, id_pelanggan, id_paket, berat, harga, status, waktu, harga_custom) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siidsssd", $tracking_code, $pelanggan_id, $paket_id, $berat, $total_harga_item, $status, $waktu, $harga_custom);
            $stmt->execute();
            
            $pesanan_ids[] = $conn->insert_id;
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pesanan berhasil dibuat',
            'tracking_code' => $tracking_code,
            'pesanan_id' => $pesanan_ids[0] // Return the first order ID for redirection
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    
    exit; // Stop execution after sending AJAX response
}

// AJAX endpoint untuk mencari pelanggan
if (isset($_GET['search_customer']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $search = $_GET['search_customer'];
    
    $query = "SELECT id, nama, no_hp FROM pelanggan WHERE nama LIKE ? ORDER BY nama ASC LIMIT 10";
    $stmt = $conn->prepare($query);
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    echo json_encode($customers);
    exit;
}

// Ambil data paket
$query_paket = "SELECT * FROM paket ORDER BY nama ASC";
$result_paket = $conn->query($query_paket);
$paket_data = [];
while ($row = $result_paket->fetch_assoc()) {
    $paket_data[] = $row;
}

// Format tanggal dan waktu dalam bahasa Indonesia
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pesanan - Admin Zeea Laundry</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.all.min.js"></script>
    <style>
        :root {
            --primary-color: #42c3cf;
            --primary-dark: #38adb8;
            --secondary-color: #6c757d;
            --success-color:rgb(82, 210, 95);
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --card-border-radius: 15px;
            --btn-border-radius: 30px;
            --input-border-radius: 10px;
            --transition-speed: 0.3s;
        }
        .content {
            padding: 60px 80px;
        }
        @media (max-width: 768px) {
            .content{
                padding : 60px 30px;
            }
        }
        
        .container {
            max-width: 1200px;
            padding: 10px 20px;
        }
        
        .card-container {
            background-color: white;
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        /* Step Indicator Styles */
        .step-indicator-container {
            margin-bottom: 40px;
            overflow-x: hidden;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 25px 20px;
        }
        
        .step {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color:rgb(234, 239, 233);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            position: relative;
            z-index: 2;
            transition: all var(--transition-speed) ease;
            border: 2px solid transparent;
        }
        
        .step.active {
            background-color: var(--primary-color);
            color: white;
            transform: scale(1.2);
            box-shadow: 0 0 15px rgba(66, 195, 207, 0.4);
        }
        
        .step.completed {
            background-color: #42c3cf;
            color: white;
        }
        
        .step-connector {
            height: 4px;
            background-color: #e9ecef;
            flex-grow: 1;
            margin: 0 -5px;
            z-index: 1;
            transition: background-color var(--transition-speed) ease;
        }
        
        .step-connector.active {
            background-color: var(--primary-color);
        }
        
        /* Step Container Styles */
        .step-container {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
            padding: 20px 0;
        }
        
        .step-container.active {
            display: block;
        }
        
        .step-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
        }
        
        /* Form Styles */
        .form-container {
            background-color: white;
            border-radius: var(--card-border-radius);
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            font-size: 16px;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control {
            padding: 12px 15px;
            font-size: 16px;
            border-radius: var(--input-border-radius);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(66, 195, 207, 0.25);
        }
        
        /* Weight Input Styles */
        .weight-input-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .weight-input {
            font-size: 28px;
            text-align: center;
            font-weight: bold;
            height: 70px;
            width: 150px;
            margin: 0 auto;
            border: 2px solid var(--primary-color);
            border-radius: var(--input-border-radius);
            color: #333;
        }
        
        .weight-controls {
            display: flex;
            justify-content: center;
            margin-top: 15px;
            gap: 20px;
        }
        
        .weight-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-bg);
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            transition: all var(--transition-speed) ease;
        }
        
        .weight-btn:hover {
            background-color: var(--primary-color);
            color: white;
            transform: scale(1.05);
        }
        
        /* Paket Card Styles */
        .paket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .paket-card {
            border: 2px solid var(--border-color);
            border-radius: var(--card-border-radius);
            padding: 20px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            background-color: white;
        }
        
        .paket-card:hover {
            transform: translateY(-5px);
            border-color: white;
            box-shadow: 0 0 15px rgba(66, 195, 207, 0.41), 0 0 30px rgba(66, 195, 207, 0.42);
        }
        
        .paket-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(66, 195, 207, 0.1);
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(66, 195, 207, 0.3);
        }
        
        .paket-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            object-fit: contain;
            transition: transform var(--transition-speed) ease;
        }
        
        .paket-card:hover .paket-icon {
            transform: scale(1.1);
        }
        
        .paket-name {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            text-align: center;
            color: #333;
        }
        
        .paket-price {
            color: var(--success-color);
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Custom Price Container */
        .custom-price-container {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
            margin-top: 30px;
            padding: 20px;
            border: 2px dashed var(--primary-color);
            border-radius: var(--card-border-radius);
            background-color: rgba(66, 195, 207, 0.05);
        }
        
        .custom-price-container.active {
            display: block;
        }
        
        /* Navigation Buttons */
        .navigation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-nav {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: var(--btn-border-radius);
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 150px;
            justify-content: center;
        }
        
        .btn-prev {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
        
        .btn-prev:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateX(-3px);
        }
        
        .btn-next {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .btn-next:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateX(3px);
        }
        
        .btn-submit {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
            padding: 15px 40px;
            font-size: 18px;
        }
        
        .btn-submit:hover {
            background-color: #2fbb4d;
            border-color: #2fbb4d;
            transform: translateX(5px);
            box-shadow: 0 0 15px rgba(66, 207, 87, 0.8), 0 0 30px rgba(66, 207, 87, 0.6);
        }
        
        /* Customer Suggestion */
        .customer-suggestion-container {
            position: relative;
        }
        
        .customer-suggestion {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 0 0 var(--input-border-radius) var(--input-border-radius);
            z-index: 1000;
            display: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .suggestion-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background-color var(--transition-speed) ease;
        }
        
        .suggestion-item:hover {
            background-color: var(--light-bg);
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        

        .phone-container {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
            margin-top: 20px;
        }
        
        .phone-container.active {
            display: block;
        }
        

        .summary-container {
            background-color: white;
            border-radius: var(--card-border-radius);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .summary-header {
            background-color: var(--light-bg);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-header h5 {
            margin: 0;
            color: #333;
            font-weight: 600;
        }
        
        .summary-body {
            padding: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-label {
            font-weight: 600;
            color: #495057;
        }
        
        .summary-value {
            color: #333;
        }
        
        .paket-items-container {
            margin-bottom: 20px;
        }
        
        .paket-item {
            background-color: var(--light-bg);
            border-radius: var(--card-border-radius);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid var(--primary-color);
            position: relative;
            transition: all var(--transition-speed) ease;
        }
        
        .paket-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .paket-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .paket-item-title {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }
        
        .paket-item-remove {
            color: var(--danger-color);
            cursor: pointer;
            font-size: 22px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all var(--transition-speed) ease;
        }
        
        .paket-item-remove:hover {
            background-color: rgba(220, 53, 69, 0.1);
            transform: scale(1.1);
        }
        
        .paket-item-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .paket-item-label {
            color: #6c757d;
        }
        
        .paket-item-value {
            font-weight: 500;
            color: #333;
        }
        
        /* Add More Container */
        .add-more-container {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            border: 2px dashed #3a9da6;
            border-radius: var(--card-border-radius);
            background-color: rgba(40, 159, 167, 0.05);
            transition: all var(--transition-speed) ease;
        }
        
        .add-more-container:hover {
            background-color: rgba(40, 167, 167, 0.1);
        }   
        
        .add-more-text {
            font-size: 16px;
            color: #495057;
            margin-bottom: 15px;
        }
        
        .btn-add-more {
            background-color: #42c3cf;
            border-color: #42c3cf;
            color: white;
            padding: 10px 25px;
            border-radius: var(--btn-border-radius);
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add-more:hover {
            color:#f5f5f5;
            background-color: #3a9da6;
            border-color: #3a9da6;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 140, 167, 0.3);
        }
        
        /* Total Summary */
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 22px;
            font-weight: 700;
            color: #42c3cf;
            border-top: 2px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .summary-total-label {
            color: #333;
        }
        
        /* Current Date */
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 20px;
        }
        
        /* Tracking Code Display */
        .tracking-code-display {
            background-color: rgba(66, 195, 207, 0.1);
            border: 2px dashed #42c3cf;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        
        .tracking-code-label {
            font-size: 16px;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .tracking-code-value {
            font-size: 20px;
            font-weight: bold;
            color: #42c3cf;
            letter-spacing: 1px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding: 10px 10px;
            }
            
            .card-container {
                padding: 20px 15px;
            }
            
            .step {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .step-title {
                font-size: 20px;
            }
            
            .navigation-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-nav {
                width: 100%;
                padding: 15px;
            }
            
            .weight-input {
                width: 120px;
                height: 60px;
                font-size: 24px;
            }
            
            .weight-controls {
                gap: 15px;
            }
            
            .weight-btn {
                width: 50px;
                height: 50px;
            }
            
            .paket-grid {
                grid-template-columns: 1fr;
            }
            
            .paket-card {
                padding: 15px;
            }
            
            .paket-icon {
                width: 60px;
                height: 60px;
            }
            
            .paket-name {
                font-size: 16px;
            }
            
            .summary-row {
                flex-direction: column;
                margin-bottom: 15px;
            }
            
            .summary-label {
                margin-bottom: 5px;
            }
            
            .summary-total {
                flex-direction: row;
                font-size: 20px;
            }
        }
        
        @media (min-width: 769px) and (max-width: 992px) {
            .paket-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .navigation-buttons {
                flex-direction: row;
            }
        }
        
        @media (min-width: 993px) {
            .navigation-buttons {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar-admin.php'; ?>

    <!-- Main Content -->
     
    <div class="content" id="content">

            <div class="back-button no-print">
                <a href="pesanan.php" class="btn btn-outline-secondary mb-4" id="backButton">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
                </a>
            </div>
            
            
            
            <div class="card-container">
            <div class="current-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
            </div>
            <h1 class="mb-4">Tambah Pesanan Baru</h1>
                <div class="step-indicator-container">
                    <div class="step-indicator">
                        <div class="step active" id="step-1">1</div>
                        <div class="step-connector"></div>
                        <div class="step" id="step-2">2</div>
                        <div class="step-connector"></div>
                        <div class="step" id="step-3">3</div>
                        <div class="step-connector"></div>
                        <div class="step" id="step-4">4</div>
                    </div>
                </div>
                
                <form id="orderForm">
                    <input type="hidden" name="pelanggan_id" id="pelanggan_id">
                    <input type="hidden" name="paket_items" id="paket_items">
                    
                    <!-- Step 1: Data Pelanggan (Nama) -->
                    <div class="step-container active" id="step1-container">
                        <h3 class="step-title">Data Pelanggan</h3>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="form-container">
                                    <div class="mb-4 customer-suggestion-container">
                                        <label for="nama_pelanggan" class="form-label">Nama Pelanggan</label>
                                        <input type="text" class="form-control" id="nama_pelanggan" name="nama_pelanggan" placeholder="Masukan Nama Pelanggan" required autocomplete="off">
                                        <div class="customer-suggestion" id="customerSuggestion"></div>
                                    </div>
                                    
                                    <div class="phone-container" id="phoneContainer">
                                        <div class="mb-4">
                                            <label for="no_hp" class="form-label">Nomor HP</label>
                                            <input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="+628xxxxxxxxxx" required>
                                            <small class="text-muted">Format: +628xxxxxxxxxx atau 08xxxxxxxxxx</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-primary btn-nav btn-next" id="to-step2">
                                Selanjutnya <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Berat Cucian -->
                    <div class="step-container" id="step2-container">
                        <h3 class="step-title">Masukkan Berat Cucian</h3>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="form-container">
                                    <div class="weight-input-container">
                                        <label for="berat" class="form-label">Berat (kg)</label>
                                        <input type="number" class="form-control weight-input" id="berat" name="berat" min="0.5" step="0.01" value="1.00" required>
                                        
                                        <div class="weight-controls">
                                            <button type="button" class="btn weight-btn" id="decrease-weight">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <button type="button" class="btn weight-btn" id="increase-weight">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-nav btn-prev" id="to-step1-from-2">
                                <i class="fas fa-arrow-left"></i> Sebelumnya
                            </button>
                            <button type="button" class="btn btn-primary btn-nav btn-next" id="to-step3">
                                Selanjutnya <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Pilih Paket -->
                    <div class="step-container" id="step3-container">
                        <h3 class="step-title">Pilih Paket Laundry</h3>
                        
                        <div class="paket-grid">
                            <?php if (count($paket_data) > 0): ?>
                                <?php foreach ($paket_data as $paket): ?>
                                    <?php if ($paket['nama'] !== 'Paket Khusus'): ?>
                                        <div class="paket-card" data-id="<?= $paket['id'] ?>" data-nama="<?= $paket['nama'] ?>" data-harga="<?= $paket['harga'] ?>">
                                            <img src="../assets/uploads/paket_icons/<?= $paket['icon'] ?>" alt="<?= $paket['nama'] ?>" class="paket-icon">
                                            <div class="paket-name"><?= $paket['nama'] ?></div>
                                            <div class="paket-price">Rp <?= number_format($paket['harga'], 0, ',', '.') ?> / kg</div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <!-- Custom Package Option -->
                                <div class="paket-card" data-id="custom" data-nama="Paket Khusus" data-harga="0">
                                    <div class="paket-icon d-flex align-items-center justify-content-center">
                                        <i class="fas fa-cog fa-3x text-primary"></i>
                                    </div>
                                    <div class="paket-name">Paket Khusus</div>
                                    <div class="paket-price">Harga Kustom</div>
                                </div>
                            <?php else: ?>
                                <div class="col-12 text-center">
                                    <p>Tidak ada paket tersedia. Silakan tambahkan paket terlebih dahulu.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Custom Price Input -->
                        <div class="custom-price-container" id="customPriceContainer">
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="custom_price" class="form-label">Harga Per Kg (Rp)</label>
                                        <input type="number" class="form-control" id="custom_price" name="custom_price" min="1000" step="500" value="10000">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-nav btn-prev" id="to-step2-from-3">
                                <i class="fas fa-arrow-left"></i> Sebelumnya
                            </button>
                            <button type="button" class="btn btn-primary btn-nav btn-next" id="to-step4" disabled>
                                Selanjutnya <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Konfirmasi dan Tambah Paket Lain -->
                    <div class="step-container" id="step4-container">
                        <h3 class="step-title">Konfirmasi Pesanan</h3>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="summary-container mb-4">
                                    <div class="summary-header">
                                        <h5>Data Pelanggan</h5>
                                    </div>
                                    <div class="summary-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="summary-label">Nama:</div>
                                                <div class="summary-value" id="summary-nama"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="summary-label">No. HP:</div>
                                                <div class="summary-value" id="summary-hp"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="summary-container">
                                    <div class="summary-header">
                                        <h5>Daftar Paket</h5>
                                    </div>
                                    <div class="summary-body">
                                        <div class="paket-items-container" id="paketItemsContainer">
                                            <!-- Paket items will be added here dynamically -->
                                        </div>
                                        
                                        <div class="add-more-container">
                                            <p class="add-more-text">Ingin menambah paket lain?</p>
                                            <button type="button" class="btn btn-add-more" id="addMorePaket">
                                                <i class="fas fa-plus-circle"></i> Tambah Paket Lain
                                            </button>
                                        </div>
                                        
                                        <div class="summary-total">
                                            <span class="summary-total-label">Total Pesanan:</span>
                                            <span id="summary-total">Rp 0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-nav btn-prev" id="to-step3-from-4">
                                <i class="fas fa-arrow-left"></i> Sebelumnya
                            </button>
                            <button type="submit" class="btn btn-success btn-nav btn-submit" id="submit-order">
                                <i class="fas fa-check-circle"></i> Buat Pesanan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
    </div>

    <script>
        // Store paket data
        const paketData = <?= json_encode($paket_data) ?>;
        
        // Store selected paket items
        let selectedPaketItems = [];
        // Flag to track if we're coming back from another page
        let isBackNavigation = false;
        // Flag to track if we're editing an existing item
        let isEditingItem = false;
        // Index of the item being edited
        let editingItemIndex = -1;
        
        $(document).ready(function() {
            // Check for back navigation using performance API if available
            if (window.performance && window.performance.navigation) {
                if (window.performance.navigation.type === 2) { // 2 is TYPE_BACK_FORWARD
                    isBackNavigation = true;
                }
            }
            
            // Clear selected items if coming back from another page
            if (isBackNavigation) {
                selectedPaketItems = [];
                sessionStorage.removeItem('pendingOrder');
            }
            
            // Step 1: Customer Name and Phone
            $('#nama_pelanggan').on('input', function() {
                const input = $(this).val().trim();
                
                // Show phone field when name is entered
                if (input.length > 0) {
                    $('#phoneContainer').addClass('active');
                } else {
                    $('#phoneContainer').removeClass('active');
                }
                
                // Customer name autocomplete with real-time AJAX
                if (input.length >= 2) {
                    // Make AJAX request to search for customers
                    $.ajax({
                        url: 'tambah-pesanan.php',
                        type: 'GET',
                        data: { search_customer: input },
                        dataType: 'json',
                        success: function(data) {
                            if (data.length > 0) {
                                let suggestionHtml = '';
                                data.forEach(customer => {
                                    suggestionHtml += `<div class="suggestion-item" data-id="${customer.id}" data-phone="${customer.no_hp}">${customer.nama}</div>`;
                                });
                                
                                $('#customerSuggestion').html(suggestionHtml).show();
                            } else {
                                $('#customerSuggestion').hide();
                            }
                        },
                        error: function() {
                            console.error('Error fetching customer data');
                        }
                    });
                } else {
                    $('#customerSuggestion').hide();
                }
                
                // Clear customer ID if input changes
                $('#pelanggan_id').val('');
            });
            
            // Handle suggestion click
            $(document).on('click', '.suggestion-item', function() {
                const customerId = $(this).data('id');
                const customerName = $(this).text();
                const customerPhone = $(this).data('phone');
                
                $('#nama_pelanggan').val(customerName);
                $('#no_hp').val(customerPhone);
                $('#pelanggan_id').val(customerId);
                $('#customerSuggestion').hide();
                
                // Show phone field and fill it
                $('#phoneContainer').addClass('active');
                
                // Show a success notification
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'Data pelanggan ditemukan!',
                    text: 'Nomor HP telah diisi otomatis',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true
                });
            });
            
            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#nama_pelanggan, #customerSuggestion').length) {
                    $('#customerSuggestion').hide();
                }
            });
            
            // Step navigation
            $('#to-step2').click(function() {
                if (validateStep1()) {
                    $('#step1-container').removeClass('active');
                    $('#step2-container').addClass('active');
                    $('#step-1').addClass('completed');
                    $('#step-2').addClass('active');
                    $('.step-connector:first').addClass('active');
                    updateSummary();
                }
            });
            
            $('#to-step1-from-2').click(function() {
                $('#step2-container').removeClass('active');
                $('#step1-container').addClass('active');
                $('#step-2').removeClass('active');
                $('#step-2').removeClass('completed');
                $('#step-3').removeClass('active');
                $('#step-3').removeClass('completed');
                $('#step-4').removeClass('active');
                $('#step-4').removeClass('completed');
                $('.step-connector').removeClass('active');
            });
            
            $('#to-step3').click(function() {
                if (validateStep2()) {
                    $('#step2-container').removeClass('active');
                    $('#step3-container').addClass('active');
                    $('#step-2').addClass('completed');
                    $('#step-3').addClass('active');
                    $('.step-connector:eq(1)').addClass('active');
                }
            });
            
            $('#to-step2-from-3').click(function() {
                $('#step3-container').removeClass('active');
                $('#step2-container').addClass('active');
                $('#step-3').removeClass('active');
                $('#step-3').removeClass('completed');
                $('.step-connector:eq(1)').removeClass('active');
            });
            
            $('#to-step4').click(function() {
                if (validateStep3()) {
                    // If we're editing an item, update it instead of adding a new one
                    if (isEditingItem && editingItemIndex >= 0) {
                        updatePaketItem(editingItemIndex);
                        isEditingItem = false;
                        editingItemIndex = -1;
                    } else {
                        // Add current paket to the list
                        addCurrentPaketToList();
                    }
                    
                    $('#step3-container').removeClass('active');
                    $('#step4-container').addClass('active');
                    $('#step-3').addClass('completed');
                    $('#step-4').addClass('active');
                    $('.step-connector:last').addClass('active');
                    
                    // Update summary
                    updateSummary();
                    renderPaketItems();
                    
                    // Save order data to session storage to prevent duplication
                    sessionStorage.setItem('pendingOrder', JSON.stringify({
                        customer: {
                            name: $('#nama_pelanggan').val(),
                            phone: $('#no_hp').val(),
                            id: $('#pelanggan_id').val()
                        },
                        items: selectedPaketItems
                    }));
                }
            });
            
            $('#to-step3-from-4').click(function() {
                $('#step4-container').removeClass('active');
                $('#step3-container').addClass('active');
                $('#step-4').removeClass('active');
                $('#step-4').removeClass('completed');
                $('.step-connector:last').removeClass('active');
                
                // Set editing mode to true - we're going back to edit
                isEditingItem = true;
                // Get the last item index (or the only item if there's just one)
                editingItemIndex = selectedPaketItems.length - 1;
                
                // If we have an item to edit, pre-select it
                if (editingItemIndex >= 0) {
                    const item = selectedPaketItems[editingItemIndex];
                    
                    // Set weight
                    $('#berat').val(item.berat.toFixed(2));
                    
                    // Select the package
                    $('.paket-card').removeClass('selected');
                    if (item.paket_id === 'custom') {
                        // For custom package
                        $('.paket-card[data-id="custom"]').addClass('selected');
                        $('#customPriceContainer').addClass('active');
                        $('#custom_price').val(item.harga_per_kg);
                    } else {
                        // For regular package
                        $(`.paket-card[data-id="${item.paket_id}"]`).addClass('selected');
                        $('#customPriceContainer').removeClass('active');
                    }
                    
                    // Enable the next button
                    $('#to-step4').prop('disabled', false);
                } else {
                    // Reset paket selection if no item to edit
                    resetPaketSelection();
                }
            });
            
            // Weight controls
            $('#increase-weight').click(function() {
                let weight = parseFloat($('#berat').val());
                weight = Math.round((weight + 0.01) * 100) / 100; // Round to 2 decimal places
                $('#berat').val(weight.toFixed(2));
            });
            
            $('#decrease-weight').click(function() {
                let weight = parseFloat($('#berat').val());
                if (weight > 0.5) {
                    weight = Math.round((weight - 0.01) * 100) / 100; // Round to 2 decimal places
                    $('#berat').val(weight.toFixed(2));
                }
            });
            
            // Handle manual weight input to ensure rounding to 2 decimal places
            $('#berat').on('change', function() {
                let weight = parseFloat($(this).val());
                if (!isNaN(weight)) {
                    weight = Math.round(weight * 100) / 100; // Round to 2 decimal places
                    $(this).val(weight.toFixed(2));
                }
            });
            
            // Custom price input
            $('#custom_price').on('input', function() {
                updatePaketPrice();
            });
            
            // Paket selection
            $('.paket-card').click(function() {
                $('.paket-card').removeClass('selected');
                $(this).addClass('selected');
                
                const paketId = $(this).data('id');
                
                // Show/hide custom price input
                if (paketId === 'custom') {
                    $('#customPriceContainer').addClass('active');
                } else {
                    $('#customPriceContainer').removeClass('active');
                }
                
                $('#to-step4').prop('disabled', false);
            });
            
            // Add more paket button
            $('#addMorePaket').click(function() {
                // Go back to step 2 to add another paket
                $('#step4-container').removeClass('active');
                $('#step2-container').addClass('active');
                $('#step-4').removeClass('active');
                $('#step-4').removeClass('completed');
                $('#step-3').addClass('completed');
                $('#step-2').addClass('active');
                $('.step-connector:last').removeClass('active');
                
                // Reset weight and paket selection
                $('#berat').val('1.00');
                resetPaketSelection();
                
                // Reset editing flags
                isEditingItem = false;
                editingItemIndex = -1;
            });
            
            // Remove paket item
            $(document).on('click', '.paket-item-remove', function() {
                const index = $(this).data('index');
                selectedPaketItems.splice(index, 1);
                renderPaketItems();
                updateSummary();
                
                // Update session storage
                if (selectedPaketItems.length > 0) {
                    sessionStorage.setItem('pendingOrder', JSON.stringify({
                        customer: {
                            name: $('#nama_pelanggan').val(),
                            phone: $('#no_hp').val(),
                            id: $('#pelanggan_id').val()
                        },
                        items: selectedPaketItems
                    }));
                } else {
                    sessionStorage.removeItem('pendingOrder');
                }
            });
            
            // Form submission
            $('#orderForm').on('submit', function(e) {
                e.preventDefault();
                
                if (validateFinalStep()) {
                    // Prepare paket items for submission
                    $('#paket_items').val(JSON.stringify(selectedPaketItems));
                    
                    const formData = $(this).serialize();
                    
                    $.ajax({
                        type: 'POST',
                        url: 'tambah-pesanan.php',
                        data: formData,
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                // Clear any pending order data from storage on success
                                sessionStorage.removeItem('pendingOrder');
                                
                                Swal.fire({
                                    title: 'Berhasil!',
                                    html: `
                                        <p>Pesanan berhasil dibuat</p>
                                        <div class="tracking-code-display mt-3">
                                            <div class="tracking-code-label">Kode Tracking:</div>
                                            <div class="tracking-code-value">${response.tracking_code}</div>
                                        </div>
                                    `,
                                    icon: 'success',
                                    confirmButtonText: 'Lihat Daftar Pesanan'
                                }).then(() => {
                                    window.location.href = 'pesanan.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Gagal!',
                                    text: response.message,
                                    icon: 'error'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'Terjadi kesalahan saat memproses pesanan: ' + error,
                                icon: 'error'
                            });
                        }
                    });
                }
            });
            
            // Check for unfinished order data on page load
            const pendingOrder = sessionStorage.getItem('pendingOrder');
            if (pendingOrder) {
                try {
                    const orderData = JSON.parse(pendingOrder);
                    
                    // Confirm if user wants to continue with previous order
                    Swal.fire({
                        title: 'Pesanan yang belum selesai',
                        text: 'Kami menemukan pesanan yang belum selesai. Apakah Anda ingin melanjutkan?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, lanjutkan',
                        cancelButtonText: 'Tidak, buat baru'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Restore customer data
                            $('#nama_pelanggan').val(orderData.customer.name);
                            $('#no_hp').val(orderData.customer.phone);
                            $('#pelanggan_id').val(orderData.customer.id);
                            $('#phoneContainer').addClass('active');
                            
                            // Restore items
                            selectedPaketItems = orderData.items;
                            
                            // Go to confirmation step
                            $('#step1-container').removeClass('active');
                            $('#step4-container').addClass('active');
                            $('#step-1').addClass('completed');
                            $('#step-2').addClass('completed');
                            $('#step-3').addClass('completed');
                            $('#step-4').addClass('active');
                            $('.step-connector').addClass('active');
                            
                            updateSummary();
                            renderPaketItems();
                        } else {
                            // Clear stored data
                            sessionStorage.removeItem('pendingOrder');
                            selectedPaketItems = [];
                        }
                    });
                } catch (e) {
                    console.error('Error parsing stored order data:', e);
                    sessionStorage.removeItem('pendingOrder');
                }
            }
        });
        
        // Add current paket to the list
        function addCurrentPaketToList() {
            const selectedPaket = $('.paket-card.selected');
            if (selectedPaket.length === 0) return;
            
            const paketId = selectedPaket.data('id');
            const paketNama = selectedPaket.data('nama');
            const berat = parseFloat($('#berat').val());
            
            let hargaPerKg = 0;
            let totalHarga = 0;
            
            if (paketId === 'custom') {
                hargaPerKg = parseFloat($('#custom_price').val());
                totalHarga = hargaPerKg * berat;
            } else {
                hargaPerKg = parseFloat(selectedPaket.data('harga'));
                totalHarga = hargaPerKg * berat;
            }
            
            // Add to selected items
            selectedPaketItems.push({
                paket_id: paketId,
                paket_nama: paketNama,
                berat: berat,
                harga_per_kg: hargaPerKg,
                total_harga: totalHarga
            });
        }
        
        // Update an existing paket item
        function updatePaketItem(index) {
            if (index < 0 || index >= selectedPaketItems.length) return;
            
            const selectedPaket = $('.paket-card.selected');
            if (selectedPaket.length === 0) return;
            
            const paketId = selectedPaket.data('id');
            const paketNama = selectedPaket.data('nama');
            const berat = parseFloat($('#berat').val());
            
            let hargaPerKg = 0;
            let totalHarga = 0;
            
            if (paketId === 'custom') {
                hargaPerKg = parseFloat($('#custom_price').val());
                totalHarga = hargaPerKg * berat;
            } else {
                hargaPerKg = parseFloat(selectedPaket.data('harga'));
                totalHarga = hargaPerKg * berat;
            }
            
            // Update the item
            selectedPaketItems[index] = {
                paket_id: paketId,
                paket_nama: paketNama,
                berat: berat,
                harga_per_kg: hargaPerKg,
                total_harga: totalHarga
            };
        }
        
        // Reset paket selection
        function resetPaketSelection() {
            $('.paket-card').removeClass('selected');
            $('#customPriceContainer').removeClass('active');
            $('#to-step4').prop('disabled', true);
        }
        
        // Update summary
        function updateSummary() {
            $('#summary-nama').text($('#nama_pelanggan').val());
            $('#summary-hp').text($('#no_hp').val());
            
            // Calculate total
            let total = 0;
            selectedPaketItems.forEach(item => {
                total += item.total_harga;
            });
            
            $('#summary-total').text('Rp ' + formatNumber(total));
        }
        
        // Render paket items
        function renderPaketItems() {
            let html = '';
            
            if (selectedPaketItems.length === 0) {
                html = '<div class="text-center text-muted">Belum ada paket yang dipilih</div>';
            } else {
                selectedPaketItems.forEach((item, index) => {
                    html += `
                        <div class="paket-item">
                            <div class="paket-item-header">
                                <div class="paket-item-title">${item.paket_nama}</div>
                                <div class="paket-item-remove" data-index="${index}">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <div class="paket-item-details">
                                <div class="paket-item-label">Berat:</div>
                                <div class="paket-item-value">${item.berat.toFixed(2)} kg</div>
                            </div>
                            <div class="paket-item-details">
                                <div class="paket-item-label">Harga per Kg:</div>
                                <div class="paket-item-value">Rp ${formatNumber(item.harga_per_kg)}</div>
                            </div>
                            <div class="paket-item-details">
                                <div class="paket-item-label">Total Harga:</div>
                                <div class="paket-item-value">Rp ${formatNumber(item.total_harga)}</div>
                            </div>
                        </div>
                    `;
                });
            }
            
            $('#paketItemsContainer').html(html);
        }
        
        // Update paket price based on weight and selected paket
        function updatePaketPrice() {
            const selectedPaket = $('.paket-card.selected');
            if (selectedPaket.length === 0) return;
            
            const paketId = selectedPaket.data('id');
            const berat = parseFloat($('#berat').val());
            
            let hargaPerKg = 0;
            
            if (paketId === 'custom') {
                hargaPerKg = parseFloat($('#custom_price').val());
            } else {
                hargaPerKg = parseFloat(selectedPaket.data('harga'));
            }
            
            const totalHarga = hargaPerKg * berat;
        }
        
        // Format number with thousand separator
        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }
        
        // Validate step 1
        function validateStep1() {
            const nama = $('#nama_pelanggan').val().trim();
            const noHp = $('#no_hp').val().trim();
            
            if (nama === '') {
                Swal.fire({
                    title: 'Error!',
                    text: 'Nama pelanggan harus diisi',
                    icon: 'error'
                });
                return false;
            }
            
            if (noHp === '') {
                Swal.fire({
                    title: 'Error!',
                    text: 'Nomor HP harus diisi',
                    icon: 'error'
                });
                return false;
            }
            
            // Validate phone number format
            const phoneRegex = /^(\+62|62|0)[0-9]{9,13}$/;
            if (!phoneRegex.test(noHp)) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Format nomor HP tidak valid',
                    icon: 'error'
                });
                return false;
            }
            
            return true;
        }
        
        // Validate step 2
        function validateStep2() {
            const berat = parseFloat($('#berat').val());
            
            if (isNaN(berat) || berat < 0.5) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Berat harus minimal 0.5 kg',
                    icon: 'error'
                });
                return false;
            }
            
            return true;
        }
        
        // Validate step 3
        function validateStep3() {
            const selectedPaket = $('.paket-card.selected');
            
            if (selectedPaket.length === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Silakan pilih paket laundry',
                    icon: 'error'
                });
                return false;
            }
            
            const paketId = selectedPaket.data('id');
            
            if (paketId === 'custom') {
                const customPrice = parseFloat($('#custom_price').val());
                
                if (isNaN(customPrice) || customPrice <= 0) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Harga kustom harus diisi dengan nilai lebih dari 0',
                        icon: 'error'
                    });
                    return false;
                }
            }
            
            return true;
        }
        
        // Validate final step
        function validateFinalStep() {
            if (selectedPaketItems.length === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Belum ada paket yang dipilih',
                    icon: 'error'
                });
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
