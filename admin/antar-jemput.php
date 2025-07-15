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

// Check if required columns exist, if not create them
$check_columns = "SHOW COLUMNS FROM antar_jemput LIKE 'deleted_at'";
$result_check = $conn->query($check_columns);
if ($result_check->num_rows == 0) {
    // Add missing columns
    $alter_queries = [
        "ALTER TABLE antar_jemput ADD COLUMN nama_pelanggan VARCHAR(100) AFTER id_pesanan",
        "ALTER TABLE antar_jemput ADD COLUMN id_pelanggan INT AFTER nama_pelanggan", 
        "ALTER TABLE antar_jemput ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER harga_custom"
    ];
    
    foreach ($alter_queries as $query) {
        try {
            $conn->query($query);
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
}

// Check if antarjemput_status table exists
$check_table = "SHOW TABLES LIKE 'antarjemput_status'";
$result_table = $conn->query($check_table);
if ($result_table->num_rows == 0) {
    // Create antarjemput_status table
    $create_table = "CREATE TABLE antarjemput_status (
        id INT PRIMARY KEY AUTO_INCREMENT,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(50) NOT NULL
    )";
    $conn->query($create_table);
    
    // Insert default status
    $conn->query("INSERT INTO antarjemput_status (status, updated_by) VALUES ('active', 'system')");
}

// AUTO-FIX: Setiap reload halaman, pastikan semua antar_jemput yang statusnya 'selesai' status_pembayaran-nya juga 'sudah_dibayar'
$conn->query("UPDATE antar_jemput SET status_pembayaran = 'sudah_dibayar' WHERE status = 'selesai' AND status_pembayaran != 'sudah_dibayar'");

// FUNGSI UNTUK MENGIRIM PESAN WHATSAPP - DIPERBAIKI
function sendWhatsAppMessage($no_hp, $message) {
    // Pastikan direktori logs ada
    $log_dir = '../logs';
    if (!is_dir($log_dir)) {
        // Buat direktori logs jika belum ada
        if (!mkdir($log_dir, 0755, true)) {
            // Jika gagal membuat direktori, log ke error_log PHP
            error_log("Failed to create logs directory: $log_dir");
            return false;
        }
    }
    
    // Log pesan ke file untuk tracking
    $log_file = $log_dir . '/whatsapp_messages.log';
    $log_message = date('Y-m-d H:i:s') . " - Kirim ke $no_hp: $message\n";
    
    // Gunakan file_put_contents dengan error handling
    if (file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) === false) {
        // Jika gagal menulis ke file, log ke error_log PHP
        error_log("Failed to write WhatsApp log to: $log_file");
        return false;
    }
    
    return true;
}

// FUNGSI UNTUK FORMAT NOMOR HP UNTUK WHATSAPP
function formatPhoneForWhatsApp($phone) {
    // Hapus semua karakter non-digit
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Jika nomor dimulai dengan 0, ganti dengan 62
    if (substr($clean_phone, 0, 1) === '0') {
        $clean_phone = '62' . substr($clean_phone, 1);
    }
    
    // Jika nomor tidak dimulai dengan 62, tambahkan 62
    if (substr($clean_phone, 0, 2) !== '62') {
        $clean_phone = '62' . $clean_phone;
    }
    
    return $clean_phone;
}

// FUNGSI UNTUK MENDAPATKAN DETAIL PESANAN LENGKAP - DIPERBAIKI UNTUK MYSQL LAMA
function getOrderDetails($conn, $antar_jemput_id) {
    // Pertama ambil data antar_jemput
    $query_aj = "SELECT 
        aj.id,
        aj.id_pesanan, 
        aj.layanan,
        aj.alamat_antar,
        aj.alamat_jemput,
        aj.status,
        aj.waktu_antar,
        aj.waktu_jemput,
        aj.harga,
        COALESCE(pl.nama, aj.nama_pelanggan, 'Pelanggan') as nama_pelanggan,
        COALESCE(pl.no_hp, '') as no_hp,
        aj.id_pelanggan
    FROM antar_jemput aj 
    LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id
    WHERE aj.id = ?";
    
    $stmt_aj = $conn->prepare($query_aj);
    $stmt_aj->bind_param("i", $antar_jemput_id);
    $stmt_aj->execute();
    $result_aj = $stmt_aj->get_result();
    $antar_jemput_data = $result_aj->fetch_assoc();
    
    if (!$antar_jemput_data) {
        return null;
    }
    
    // Jika ada id_pesanan, ambil data pesanan berdasarkan id_pesanan
    if (!empty($antar_jemput_data['id_pesanan'])) {
        // Ambil tracking_code dari pesanan utama
        $query_tracking = "SELECT tracking_code FROM pesanan WHERE id = ?";
        $stmt_tracking = $conn->prepare($query_tracking);
        $stmt_tracking->bind_param("i", $antar_jemput_data['id_pesanan']);
        $stmt_tracking->execute();
        $result_tracking = $stmt_tracking->get_result();
        $tracking_data = $result_tracking->fetch_assoc();
        
        if ($tracking_data) {
            $tracking_code = $tracking_data['tracking_code'];
            
            // Ambil SEMUA pesanan dengan tracking_code yang sama - QUERY DIPERBAIKI
            $query_pesanan = "SELECT 
                p.id,
                p.tracking_code,
                p.berat,
                p.harga,
                p.status_pembayaran,
                p.waktu,
                pk.nama as nama_paket
            FROM pesanan p
            LEFT JOIN paket pk ON p.id_paket = pk.id
            WHERE p.tracking_code = ?
            ORDER BY p.id";
            
            $stmt_pesanan = $conn->prepare($query_pesanan);
            $stmt_pesanan->bind_param("s", $tracking_code);
            $stmt_pesanan->execute();
            $result_pesanan = $stmt_pesanan->get_result();
            
            // Kumpulkan data pesanan
            $pesanan_list = [];
            $total_berat = 0;
            $total_harga = 0;
            $paket_names = [];
            $berat_values = [];
            $harga_values = [];
            $status_pembayaran = 'sudah_dibayar'; // default
            
            while ($pesanan = $result_pesanan->fetch_assoc()) {
                $pesanan_list[] = $pesanan;
                $total_berat += (float)$pesanan['berat'];
                $total_harga += (float)$pesanan['harga'];
                $paket_names[] = $pesanan['nama_paket'];
                $berat_values[] = $pesanan['berat'];
                $harga_values[] = $pesanan['harga'];
                $status_pembayaran = $pesanan['status_pembayaran']; // ambil status terakhir
            }
            
            if (!empty($pesanan_list)) {
                // Gabungkan data antar_jemput dengan data pesanan
                return array_merge($antar_jemput_data, [
                    'tracking_code' => $tracking_code,
                    'total_berat' => $total_berat,
                    'total_harga_pesanan' => $total_harga,
                    'status_pembayaran' => $status_pembayaran,
                    'waktu_pesanan' => $pesanan_list[0]['waktu'],
                    'paket_list' => implode(', ', $paket_names),
                    'berat_list' => implode(', ', $berat_values),
                    'harga_list' => implode(', ', $harga_values),
                    // Tambahan agar harga_pesanan dan harga_custom selalu numerik
                    'harga_pesanan' => $total_harga,
                    'harga_custom' => isset($antar_jemput_data['harga']) ? (float)$antar_jemput_data['harga'] : 5000
                ]);
            }
        }
    }
    
    // Jika tidak ada pesanan terkait, buat tracking_code default
    $default_tracking = 'AJ-' . str_pad($antar_jemput_data['id'], 6, '0', STR_PAD_LEFT);
    
    return array_merge($antar_jemput_data, [
        'tracking_code' => $default_tracking,
        'total_berat' => 0,
        'total_harga_pesanan' => 0,
        'status_pembayaran' => 'sudah_dibayar',
        'waktu_pesanan' => $antar_jemput_data['waktu_antar'] ?: $antar_jemput_data['waktu_jemput'],
        'paket_list' => null,
        'berat_list' => null,
        'harga_list' => null,
        // Tambahan agar harga_pesanan dan harga_custom selalu numerik
        'harga_pesanan' => 0,
        'harga_custom' => isset($antar_jemput_data['harga']) ? (float)$antar_jemput_data['harga'] : 5000
    ]);
}

// FUNGSI UNTUK GENERATE PESAN WHATSAPP DENGAN DETAIL LENGKAP
function generateWhatsAppMessageWithDetails($conn, $antar_jemput_id, $status) {
    $orderDetails = getOrderDetails($conn, $antar_jemput_id);
    
    if (!$orderDetails) {
        return generateWhatsAppMessage('Pelanggan', 'AJ-000000', $status);
    }
    
    $nama_pelanggan = $orderDetails['nama_pelanggan'];
    $tracking_code = $orderDetails['tracking_code'];
    $layanan = $orderDetails['layanan'];
    $alamat_antar = $orderDetails['alamat_antar'];
    $alamat_jemput = $orderDetails['alamat_jemput'];
    $waktu_antar = $orderDetails['waktu_antar'];
    $waktu_jemput = $orderDetails['waktu_jemput'];
    $harga = $orderDetails['harga'] ?: 5000;
    $paket_list = $orderDetails['paket_list'];
    $berat_list = $orderDetails['berat_list'];
    $harga_list = $orderDetails['harga_list'];
    $total_berat = $orderDetails['total_berat'];
    $total_harga_pesanan = $orderDetails['total_harga_pesanan'];
    $status_pembayaran = $orderDetails['status_pembayaran'];
    
    // Hitung total harga
    $harga_antar_jemput = $harga;
    $total_harga = $status_pembayaran === 'sudah_dibayar' ? $harga_antar_jemput : ($total_harga_pesanan + $harga_antar_jemput);
    
    // URL untuk tracking pelanggan
    $customerPageUrl = "https://laundry.destio.my.id/pelanggan/index.php?code=" . urlencode($tracking_code);
    
    $message = "*ZEEA LAUNDRY - LAYANAN ANTAR JEMPUT*\n\n";
    
    if ($status === 'dalam perjalanan') {
        $message .= "Halo *{$nama_pelanggan}*,\n\n";
        $message .= "*Tim Zeea Laundry sedang dalam perjalanan untuk mengantar cucian Anda.*\n";
        $message .= "Mohon bersiap dan pastikan ada yang bisa menerima di lokasi.\n\n";
    } elseif ($status === 'selesai') {
        $message .= "Halo *{$nama_pelanggan}*,\n\n";
        $message .= "*Layanan antar jemput Anda telah SELESAI!*\n";
        $message .= "Terima kasih telah menggunakan layanan kami.\n\n";
    }
    
    $message .= "*DETAIL PESANAN:*\n";
    $message .= "• Kode Tracking: *{$tracking_code}*\n";
    $message .= "• Layanan: *" . ucwords(str_replace('-', ' & ', $layanan)) . "*\n";
    
    // Detail alamat
    if ($alamat_antar && ($layanan === 'antar' || $layanan === 'antar-jemput')) {
        $message .= "• Alamat Antar: {$alamat_antar}\n";
        if ($waktu_antar && $waktu_antar !== '0000-00-00 00:00:00') {
            $message .= "• Waktu Antar: " . date('d/m/Y H:i', strtotime($waktu_antar)) . " WIB\n";
        }
    }
    
    if ($alamat_jemput && ($layanan === 'jemput' || $layanan === 'antar-jemput')) {
        $message .= "• Alamat Jemput: {$alamat_jemput}\n";
        if ($waktu_jemput && $waktu_jemput !== '0000-00-00 00:00:00') {
            $message .= "• Waktu Jemput: " . date('d/m/Y H:i', strtotime($waktu_jemput)) . " WIB\n";
        }
    }
    
    // Detail paket jika ada pesanan terkait
    if ($paket_list && $berat_list && $total_berat > 0) {
        $paket_array = explode(', ', $paket_list);
        $berat_array = explode(', ', $berat_list);
        $harga_array = explode(', ', $harga_list);
        
        $message .= "\n*DETAIL PAKET LAUNDRY:*\n";
        for ($i = 0; $i < count($paket_array); $i++) {
            $berat_value = isset($berat_array[$i]) ? $berat_array[$i] : '0';
            $harga_value = isset($harga_array[$i]) ? $harga_array[$i] : '0';
            $berat_formatted = number_format((float)$berat_value, 2, ',', '.');
            $harga_formatted = number_format((float)$harga_value, 0, ',', '.');
            $message .= "• {$paket_array[$i]}: {$berat_formatted} kg - Rp {$harga_formatted}\n";
        }
        
        $message .= "• *Total Berat: " . number_format($total_berat, 2, ',', '.') . " kg*\n";
    }
    
    // Detail harga
    $message .= "\n*DETAIL BIAYA:*\n";
    if ($total_harga_pesanan > 0) {
        if ($status_pembayaran === 'sudah_dibayar') {
            $message .= "• Biaya Laundry: Rp " . number_format($total_harga_pesanan, 0, ',', '.') . " *(Sudah Dibayar)*\n";
        } else {
            $message .= "• Biaya Laundry: Rp " . number_format($total_harga_pesanan, 0, ',', '.') . "\n";
        }
    }
    $message .= "• Biaya Antar/Jemput: Rp " . number_format($harga_antar_jemput, 0, ',', '.') . "\n";
    $message .= "• *Total yang harus dibayar: Rp " . number_format($total_harga, 0, ',', '.') . "*\n";
    
    if ($status_pembayaran === 'belum_dibayar' && $total_harga_pesanan > 0) {
        $message .= "\n*PERHATIAN:* Pembayaran laundry belum lunas. Total yang harus dibayar termasuk biaya laundry dan antar/jemput.\n";
    }
    
    $message .= "\n*CEK STATUS PESANAN:*\n";
    $message .= "Untuk informasi lebih lengkap, silakan kunjungi:\n";
    $message .= "{$customerPageUrl}\n\n";
    $message .= "Atau masukkan kode tracking *{$tracking_code}* di halaman pelanggan website kami.\n\n";
    
    if ($status === 'dalam perjalanan') {
        $message .= "Estimasi tiba: 15-30 menit dari sekarang.\n";
        $message .= "Jika ada kendala, kami akan menghubungi Anda.\n\n";
    }
    
    $message .= "Terima kasih telah mempercayakan layanan laundry kepada kami!\n\n";
    $message .= "*ZEEA LAUNDRY*\n";
    $message .= "RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n";
    $message .= "WhatsApp: 0895395442010\n";
    $message .= "Website: https://laundry.destio.my.id/";
    
    return $message;
}

// FUNGSI UNTUK GENERATE PESAN WHATSAPP SEDERHANA (FALLBACK)
function generateWhatsAppMessage($nama_pelanggan, $tracking_code, $status) {
    $message = "*ZEEA LAUNDRY - LAYANAN ANTAR JEMPUT*\n\n";

    if ($status === 'dalam perjalanan') {
        $message .= "*Tim Zeea Laundry sedang dalam perjalanan untuk mengantar cucian Anda.*\n";
        $message .= "Mohon bersiap dan pastikan ada yang bisa menerima di lokasi.\n\n";
    }

    $message .= "*Detail Pesanan:*\n";
    $message .= "• Tracking Code: {$tracking_code}\n";

    $message .= "Informasi selengkapnya silahkan kunjungi : https://laundry.destio.my.id/ \n\n";
    $message .= "*ZEEA LAUNDRY*\n";
    $message .= "RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n";
    $message .= "WhatsApp: 0895395442010\n";
    
    return $message;
}

// Tambahkan handler AJAX untuk mendapatkan detail pesanan (untuk JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_order_details'])) {
    header('Content-Type: application/json');
    
    $antar_jemput_id = $_POST['antar_jemput_id'];
    $orderDetails = getOrderDetails($conn, $antar_jemput_id);
    
    if ($orderDetails) {
        echo json_encode([
            'success' => true,
            'orderDetails' => $orderDetails
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data pesanan tidak ditemukan'
        ]);
    }
    exit();
}

// Handle toggle service status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_service'])) {
    $new_status = $_POST['service_status'];
    
    $stmt = $conn->prepare("UPDATE antarjemput_status SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = 1");
    $stmt->bind_param("ss", $new_status, $adminName);
    
    if ($stmt->execute()) {
        $success_message = "Status layanan antar jemput berhasil diperbarui!";
    } else {
        $error_message = "Gagal memperbarui status layanan: " . $stmt->error;
    }
    $stmt->close();
}

// Handle soft delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete'])) {
    $antar_jemput_id = $_POST['antar_jemput_id'];
    
    $stmt = $conn->prepare("UPDATE antar_jemput SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $antar_jemput_id);
    
    if ($stmt->execute()) {
        $success_message = "Data antar jemput berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus data: " . $stmt->error;
    }
    $stmt->close();
}

// Handle restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    $antar_jemput_id = $_POST['antar_jemput_id'];
    
    $stmt = $conn->prepare("UPDATE antar_jemput SET deleted_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $antar_jemput_id);
    
    if ($stmt->execute()) {
        $success_message = "Data antar jemput berhasil dipulihkan!";
    } else {
        $error_message = "Gagal memulihkan data: " . $stmt->error;
    }
    $stmt->close();
}

// HANDLE AJAX STATUS UPDATE - FITUR BARU TANPA REFRESH - DIPERBAIKI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status_update'])) {
    header('Content-Type: application/json');
    
    $antar_jemput_id = $_POST['antar_jemput_id'];
    $current_status = $_POST['current_status'];
    
    // Tentukan status berikutnya
    $next_status = '';
    switch ($current_status) {
        case 'menunggu':
            $next_status = 'dalam perjalanan';
            break;
        case 'dalam perjalanan':
            $next_status = 'selesai';
            break;
        case 'selesai':
            $next_status = 'selesai'; // Tetap selesai
            break;
    }
    
    if ($next_status && $next_status !== $current_status) {
        // Mulai transaction
        $conn->begin_transaction();
        
        try {
            // Update status antar jemput
            if ($next_status === 'selesai') {
                $stmt = $conn->prepare("UPDATE antar_jemput SET status = ?, selesai_at = NOW() WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE antar_jemput SET status = ? WHERE id = ?");
            }
            $stmt->bind_param("si", $next_status, $antar_jemput_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal mengupdate status di database: " . $stmt->error);
            }
            
            // Ambil data antar jemput untuk mendapatkan info pelanggan dan pesanan
            $orderDetails = getOrderDetails($conn, $antar_jemput_id);
            
            if (!$orderDetails) {
                throw new Exception("Data antar jemput tidak ditemukan");
            }
            
            // Jika status berubah menjadi "selesai" dan ada pesanan terkait, update status pembayaran
            if ($next_status === 'selesai' && !empty($orderDetails['id_pesanan'])) {
                // Update semua pesanan dengan tracking_code yang sama
                $tracking_code = $orderDetails['tracking_code'];
                $stmt_payment = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'sudah_dibayar' WHERE tracking_code = ?");
                $stmt_payment->bind_param("s", $tracking_code);
                
                if (!$stmt_payment->execute()) {
                    throw new Exception("Gagal mengupdate status pembayaran: " . $stmt_payment->error);
                }
            }
            
            // Kirim WhatsApp dengan detail lengkap
            $whatsapp_sent = true;
            if ($next_status === 'dalam perjalanan' && !empty($orderDetails['no_hp'])) {
                $message = generateWhatsAppMessageWithDetails($conn, $antar_jemput_id, 'dalam perjalanan');
                $whatsapp_sent = sendWhatsAppMessage($orderDetails['no_hp'], $message);
                
                // Jika WhatsApp gagal dikirim, catat tapi jangan batalkan transaksi
                if (!$whatsapp_sent) {
                    error_log("WhatsApp message failed to send for order ID: " . $antar_jemput_id);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $response_message = 'Status berhasil diperbarui menjadi ' . ucfirst($next_status) . '!';
            if ($next_status === 'dalam perjalanan' && !empty($orderDetails['no_hp'])) {
                if ($whatsapp_sent) {
                    $response_message .= ' Pesan WhatsApp dengan detail lengkap telah dikirim ke pelanggan.';
                } else {
                    $response_message .= ' (Pesan WhatsApp gagal dikirim, tapi status berhasil diperbarui)';
                }
            }
            
            echo json_encode([
                'success' => true,
                'new_status' => $next_status,
                'message' => $response_message
            ]);
            
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            error_log("AJAX Status Update Error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Gagal memperbarui status: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Status sudah maksimal atau tidak valid'
        ]);
    }
    exit();
}

// Handle update status (modal) - DIPERBAIKI DENGAN DETAIL LENGKAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $antar_jemput_id = $_POST['antar_jemput_id'];
    $status_baru = $_POST['status_baru'];
    $status_lama = $_POST['status_lama'];
    
    // Mulai transaction
    $conn->begin_transaction();
    
    try {
        // Update status antar jemput
        if ($status_baru === 'selesai') {
            $stmt = $conn->prepare("UPDATE antar_jemput SET status = ?, selesai_at = NOW() WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE antar_jemput SET status = ? WHERE id = ?");
        }
        $stmt->bind_param("si", $status_baru, $antar_jemput_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengupdate status: " . $stmt->error);
        }
        
        // Ambil data antar jemput untuk mendapatkan info pelanggan dan pesanan
        $orderDetails = getOrderDetails($conn, $antar_jemput_id);
        
        if (!$orderDetails) {
            throw new Exception("Data antar jemput tidak ditemukan");
        }
        
        // Jika status berubah menjadi "selesai" dan ada pesanan terkait, update status pembayaran
        if ($status_baru === 'selesai' && !empty($orderDetails['id_pesanan'])) {
            // Update semua pesanan dengan tracking_code yang sama
            $tracking_code = $orderDetails['tracking_code'];
            $stmt_payment = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'sudah_dibayar' WHERE tracking_code = ?");
            $stmt_payment->bind_param("s", $tracking_code);
            
            if (!$stmt_payment->execute()) {
                throw new Exception("Gagal mengupdate status pembayaran: " . $stmt_payment->error);
            }
            
            $success_message = "Status antar jemput berhasil diperbarui menjadi selesai dan status pembayaran pesanan otomatis diubah menjadi sudah dibayar!";
        }
        
        // Kirim WhatsApp dengan detail lengkap jika status berubah menjadi "dalam perjalanan"
        if ($status_baru === 'dalam perjalanan' && $status_lama !== 'dalam perjalanan' && !empty($orderDetails['no_hp'])) {
            $message = generateWhatsAppMessageWithDetails($conn, $antar_jemput_id, 'dalam perjalanan');
            
            $whatsapp_sent = sendWhatsAppMessage($orderDetails['no_hp'], $message);
            
            if ($whatsapp_sent) {
                $success_message = "Status antar jemput berhasil diperbarui dan pesan notifikasi dengan detail lengkap telah dikirim ke pelanggan!";
            } else {
                $success_message = "Status antar jemput berhasil diperbarui! (Pesan WhatsApp gagal dikirim, silakan kirim manual)";
            }
        }
        
        // Jika tidak ada pesan khusus, set pesan default
        if (!isset($success_message)) {
            $success_message = "Status antar jemput berhasil diperbarui!";
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        error_log("Modal Status Update Error: " . $e->getMessage());
        $error_message = "Gagal memperbarui status: " . $e->getMessage();
    }
}

// Handle update harga
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_harga'])) {
    $antar_jemput_id = $_POST['antar_jemput_id'];
    $harga = $_POST['harga_custom']; // ambil dari input, tetap pakai name='harga_custom' di form
    // Cek status terlebih dahulu
    $check_status = $conn->prepare("SELECT status FROM antar_jemput WHERE id = ?");
    $check_status->bind_param("i", $antar_jemput_id);
    $check_status->execute();
    $status_result = $check_status->get_result();
    $status_data = $status_result->fetch_assoc();
    if ($status_data['status'] === 'selesai') {
        $error_message = "Tidak dapat mengubah harga karena status antar jemput sudah selesai!";
    } else {
        $stmt = $conn->prepare("UPDATE antar_jemput SET harga = ? WHERE id = ?");
        $stmt->bind_param("di", $harga, $antar_jemput_id);
        if ($stmt->execute()) {
            $success_message = "Harga antar jemput berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui harga: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get service status
$service_status_query = "SELECT status, updated_at, updated_by FROM antarjemput_status WHERE id = 1";
$service_status_result = $conn->query($service_status_query);
if ($service_status_result && $service_status_result->num_rows > 0) {
    $service_status = $service_status_result->fetch_assoc();
    $is_service_active = ($service_status['status'] === 'active');
} else {
    // Default values if table doesn't exist yet
    $service_status = ['status' => 'active', 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => 'system'];
    $is_service_active = true;
}

// Get statistics
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';
// PERBAIKAN: Mengubah kondisi deleted_at untuk menangani nilai NULL dengan benar
$deleted_condition = $show_deleted ? "aj.deleted_at IS NOT NULL" : "aj.deleted_at IS NULL";

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as menunggu,
    SUM(CASE WHEN status = 'dalam perjalanan' THEN 1 ELSE 0 END) as dalam_perjalanan,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN layanan = 'antar' THEN 1 ELSE 0 END) as antar,
    SUM(CASE WHEN layanan = 'jemput' THEN 1 ELSE 0 END) as jemput,
    SUM(CASE WHEN layanan = 'antar-jemput' THEN 1 ELSE 0 END) as antar_jemput
FROM antar_jemput aj WHERE $deleted_condition";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get deleted count
$deleted_count_query = "SELECT COUNT(*) as deleted_count FROM antar_jemput WHERE deleted_at IS NOT NULL"; // Juga perbaiki di sini
$deleted_count_result = $conn->query($deleted_count_query);
$deleted_count = $deleted_count_result->fetch_assoc()['deleted_count'];

// Inisialisasi variabel pencarian dan filter
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$layanan_filter = isset($_GET['layanan']) ? $_GET['layanan'] : '';

// Query untuk mendapatkan data antar jemput - DIPERBAIKI UNTUK MYSQL LAMA
$query = "SELECT 
            aj.id,
            aj.layanan,
            aj.alamat_antar,
            aj.alamat_jemput,
            aj.status,
            aj.waktu_antar,
            aj.waktu_jemput,
            aj.harga,
            aj.nama_pelanggan,
            aj.deleted_at,
            aj.status_pembayaran, -- ambil langsung dari antar_jemput
            COALESCE(p.tracking_code, CONCAT('AJ-', LPAD(aj.id, 6, '0'))) as tracking_code,
            COALESCE(p.status_pembayaran, 'sudah_dibayar') as status_pembayaran_pesanan, -- hanya untuk referensi, JANGAN dipakai di dropdown
            COALESCE(
                (SELECT SUM(p2.harga) FROM pesanan p2 WHERE p2.tracking_code = p.tracking_code), 
                0
            ) as harga_pesanan,
            COALESCE(p.waktu, aj.waktu_antar, aj.waktu_jemput) as waktu_pesanan,
            COALESCE(pl.nama, aj.nama_pelanggan, 'Pelanggan Tidak Dikenal') as nama_pelanggan_final,
            COALESCE(pl.no_hp, '-') as no_hp,
            COALESCE(
                (SELECT GROUP_CONCAT(pk2.nama SEPARATOR ', ') 
                 FROM pesanan p3 
                 LEFT JOIN paket pk2 ON p3.id_paket = pk2.id 
                 WHERE p3.tracking_code = p.tracking_code), 
                'Layanan Antar/Jemput'
            ) as paket_list
          FROM 
            antar_jemput aj
          LEFT JOIN 
            pesanan p ON aj.id_pesanan = p.id
          LEFT JOIN 
            pelanggan pl ON aj.id_pelanggan = pl.id
          WHERE $deleted_condition";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
    $search_query = "%" . $search . "%";
    $query .= " AND (aj.nama_pelanggan LIKE '$search_query' OR pl.nama LIKE '$search_query' OR pl.no_hp LIKE '$search_query' OR p.tracking_code LIKE '$search_query' OR CONCAT('AJ-', LPAD(aj.id, 6, '0')) LIKE '$search_query')";
}

// Tambahkan filter tanggal jika ada
if (!empty($date_from) && !empty($date_to)) {
    // Handle kasus ketika waktu_antar atau waktu_jemput bisa NULL
    $query .= " AND (DATE(aj.waktu_antar) BETWEEN '$date_from' AND '$date_to' OR DATE(aj.waktu_jemput) BETWEEN '$date_from' AND '$date_to' OR COALESCE(aj.waktu_antar, aj.waktu_jemput) IS NULL)";
} elseif (!empty($date_from)) {
    $query .= " AND (DATE(aj.waktu_antar) >= '$date_from' OR DATE(aj.waktu_jemput) >= '$date_from' OR COALESCE(aj.waktu_antar, aj.waktu_jemput) IS NULL)";
} elseif (!empty($date_to)) {
    $query .= " AND (DATE(aj.waktu_antar) <= '$date_to' OR DATE(aj.waktu_jemput) <= '$date_to' OR COALESCE(aj.waktu_antar, aj.waktu_jemput) IS NULL)";
}

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
    $query .= " AND aj.status = '$status_filter'";
}

// Tambahkan filter layanan jika ada
if (!empty($layanan_filter)) {
    $query .= " AND aj.layanan = '$layanan_filter'";
}

// Tambahkan pengurutan
$query .= " ORDER BY aj.id DESC";

// Eksekusi query
$result = $conn->query($query);

// Format tanggal Indonesia
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

function formatTanggalSingkat($tanggal) {
    if (empty($tanggal) || $tanggal === '0000-00-00 00:00:00') return '-'; // Handle explicit '0000-00-00 00:00:00'
    $timestamp = strtotime($tanggal);
    return date('d/m/Y H:i', $timestamp);
}

// FUNGSI UNTUK STATUS BADGE DENGAN PROGRESS YANG LEBIH JELAS - DIPERBAIKI
function getStatusBadgeWithClearProgress($status, $id, $is_deleted = false, $no_hp = '', $nama_pelanggan = '', $tracking_code = '') {
    if ($is_deleted) {
        return getStatusBadge($status);
    }
    
    $status_steps = [
        'menunggu' => ['step' => 1, 'total' => 3, 'color' => 'warning', 'next' => 'dalam perjalanan', 'label' => 'Step 1/3'],
        'dalam perjalanan' => ['step' => 2, 'total' => 3, 'color' => 'info', 'next' => 'selesai', 'label' => 'Step 2/3'],
        'selesai' => ['step' => 3, 'total' => 3, 'color' => 'success', 'next' => null, 'label' => 'Step 3/3']
    ];
    
    $current = $status_steps[$status];
    $progress_percent = ($current['step'] / $current['total']) * 100;
    $whatsapp_phone = formatPhoneForWhatsApp($no_hp);
    
    $html = '<div class="status-progress-wrapper" id="status-container-' . $id . '">';
    
    // Progress Steps Indicator
    $html .= '<div class="progress-steps-container">';
    $html .= '<div class="progress-steps">';
    
    // Step indicators
    for ($i = 1; $i <= 3; $i++) {
        $step_class = $i <= $current['step'] ? 'active' : 'inactive';
        $step_icon = '';
        
        switch ($i) {
            case 1:
                $step_icon = 'fas fa-clock';
                $step_label = 'Menunggu';
                break;
            case 2:
                $step_icon = 'fas fa-truck';
                $step_label = 'Perjalanan';
                break;
            case 3:
                $step_icon = 'fas fa-check-circle';
                $step_label = 'Selesai';
                break;
        }
        
        $html .= '<div class="progress-step ' . $step_class . '">';
        $html .= '<div class="step-circle">';
        $html .= '<i class="' . $step_icon . '"></i>';
        $html .= '</div>';
        $html .= '<div class="step-label">' . $step_label . '</div>';
        $html .= '</div>';
        
        // Add connector line (except for last step)
        if ($i < 3) {
            $connector_class = $i < $current['step'] ? 'completed' : 'pending';
            $html .= '<div class="step-connector ' . $connector_class . '"></div>';
        }
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    // Current Status and Action
    $html .= '<div class="status-action-row">';
    
    // Next action button
    if ($current['next']) {
        $next_info = [
            'dalam perjalanan' => ['text' => 'Mulai Perjalanan', 'class' => 'btn-info'],
            'selesai' => ['text' => 'Tandai Selesai', 'class' => 'btn-success']
        ];
        
        $next = $next_info[$current['next']];
        $html .= '<div class="next-action-btn">';
        $html .= '<button class="btn btn-sm ' . $next['class'] . ' status-next-clean" ';
        $html .= 'onclick="updateStatusInline(' . $id . ', \'' . $status . '\')" ';
        $html .= 'title="Ubah ke ' . ucfirst($current['next']) . '">';
        $html .= '<i class="fas fa-arrow-right me-1"></i>' . $next['text'];
        $html .= '</button>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Loading indicator (hidden by default)
    $html .= '<div class="status-loading-clean d-none" id="loading-' . $id . '">';
    $html .= '<div class="d-flex align-items-center justify-content-center py-2">';
    $html .= '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>';
    $html .= '<small class="text-muted">Memperbarui status...</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    // WhatsApp button untuk status "dalam perjalanan" - Terpisah dan rapi dengan wa.me
    if ($status === 'dalam perjalanan' && !empty($no_hp) && $no_hp !== '-') {
        $html .= '<div class="whatsapp-action-section">';
        $html .= '<button class="whatsapp-btn-clean" onclick="sendWhatsAppMessage(' . $id . ', \'' . $whatsapp_phone . '\', \'' . htmlspecialchars($nama_pelanggan, ENT_QUOTES) . '\', \'' . $tracking_code . '\')" title="Kirim Pesan WhatsApp">';
        $html .= '<i class="fab fa-whatsapp me-1"></i>Hubungi Pelanggan';
        $html .= '</button>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function getStatusBadge($status) {
    switch ($status) {
        case 'menunggu':
            return '<span class="badge bg-warning text-dark status-badge"><i class="fas fa-clock me-1"></i>Menunggu</span>';
        case 'dalam perjalanan':
            return '<span class="badge bg-info status-badge"><i class="fas fa-truck me-1"></i>Dalam Perjalanan</span>';
        case 'selesai':
            return '<span class="badge bg-success status-badge"><i class="fas fa-check-circle me-1"></i>Selesai</span>';
        default:
            return '<span class="badge bg-secondary status-badge">Unknown</span>';
    }
}

function getLayananBadge($layanan) {
    switch ($layanan) {
        case 'antar':
            return '<span class="badge bg-primary layanan-badge"><i class="fas fa-truck me-1"></i>Antar</span>';
        case 'jemput':
            return '<span class="badge bg-success layanan-badge"><i class="fas fa-home me-1"></i>Jemput</span>';
        case 'antar-jemput':
            return '<span class="badge bg-info layanan-badge"><i class="fas fa-exchange-alt me-1"></i>Antar & Jemput</span>';
        default:
            return '<span class="badge bg-secondary layanan-badge">Unknown</span>';
    }
}

function hitungTotalHarga($harga_pesanan, $harga_custom, $status_pembayaran) {
    $harga_antar_jemput = !empty($harga_custom) ? $harga_custom : 5000;
    
    if (empty($harga_pesanan) || $harga_pesanan == 0) {
        return $harga_antar_jemput;
    }
    
    if ($status_pembayaran === 'sudah_dibayar') {
        return $harga_antar_jemput;
    } else {
        return $harga_pesanan + $harga_antar_jemput;
    }
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));

// PHP: Handle update status via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_ajax'])) {
    $antar_jemput_id = intval($_POST['antar_jemput_id']);
    $status_baru = $_POST['status_baru'];
    $allowed = ['menunggu', 'dalam perjalanan', 'selesai'];
    if (!in_array($status_baru, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Status tidak valid']);
        exit();
    }
    if ($status_baru === 'selesai') {
        $stmt = $conn->prepare("UPDATE antar_jemput SET status = ?, selesai_at = NOW() WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE antar_jemput SET status = ? WHERE id = ?");
    }
    $stmt->bind_param("si", $status_baru, $antar_jemput_id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Status berhasil diperbarui']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal update status']);
    }
    exit();
}

// Tambahkan handler PHP untuk get_status_badge=1 agar AJAX bisa ambil badge/progress terbaru
if (isset($_GET['get_status_badge']) && $_GET['get_status_badge'] == '1' && isset($_GET['id'])) {
    include '../includes/db.php';
    $antar_id = intval($_GET['id']);
    $q = $conn->query("SELECT * FROM antar_jemput WHERE id = $antar_id");
    $row = $q->fetch_assoc();
    $is_deleted = !empty($row['deleted_at']) && $row['deleted_at'] !== '0000-00-00 00:00:00';
    $no_hp = $row['id_pelanggan'] ? $conn->query("SELECT no_hp FROM pelanggan WHERE id = {$row['id_pelanggan']}")->fetch_assoc()['no_hp'] : '';
    $nama_pelanggan = $row['id_pelanggan'] ? $conn->query("SELECT nama FROM pelanggan WHERE id = {$row['id_pelanggan']}")->fetch_assoc()['nama'] : '';
    $tracking_code = $row['id_pesanan'] ? $conn->query("SELECT tracking_code FROM pesanan WHERE id = {$row['id_pesanan']}")->fetch_assoc()['tracking_code'] : '';
    require_once __DIR__ . '/../admin/antar-jemput.php'; // pastikan fungsi getStatusBadgeWithClearProgress tersedia
    echo getStatusBadgeWithClearProgress($row['status'], $antar_id, $is_deleted, $no_hp, $nama_pelanggan, $tracking_code);
    exit();
}

// 1. PHP HANDLER: Update status pembayaran antar-jemput (dan pesanan jika ada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    header('Content-Type: application/json');
    $antar_jemput_id = intval($_POST['antar_jemput_id']);
    $status_pembayaran = $_POST['status_pembayaran'];
    $tracking_code = $_POST['tracking_code'] ?? null;
    $success = false;
    $msg = '';
    
    // Update antar_jemput
    $stmt = $conn->prepare("UPDATE antar_jemput SET status_pembayaran = ? WHERE id = ?");
    $stmt->bind_param("si", $status_pembayaran, $antar_jemput_id);
    if ($stmt->execute()) {
        $success = true;
        $msg = 'Status pembayaran antar jemput berhasil diupdate';
        // Jika ada tracking_code, update juga semua pesanan terkait
        if ($tracking_code) {
            $stmt2 = $conn->prepare("UPDATE pesanan SET status_pembayaran = ? WHERE tracking_code = ?");
            $stmt2->bind_param("ss", $status_pembayaran, $tracking_code);
            $stmt2->execute();
        }
    } else {
        $msg = 'Gagal update status pembayaran antar jemput';
    }
    echo json_encode(['success'=>$success,'message'=>$msg]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Antar Jemput - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <style>
        /* Existing styles remain the same... */
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

        /* WhatsApp button styling - Enhanced */
        .whatsapp-btn-clean {
            background: #25d366;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
            width: 100%;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
        }

        .whatsapp-btn-clean:hover {
            background: #128c7e;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
        }

        .whatsapp-btn-clean:active {
            transform: translateY(0);
        }

        /* Enhanced WhatsApp action section */
        .whatsapp-action-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            width: 100%;
        }

        /* Loading state for WhatsApp button */
        .whatsapp-btn-clean.loading {
            background: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }

        .whatsapp-btn-clean.loading::after {
            content: '';
            width: 12px;
            height: 12px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success state for WhatsApp button */
        .whatsapp-btn-clean.success {
            background: #28a745;
            animation: pulse-success 0.6s ease-in-out;
        }

        @keyframes pulse-success {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Rest of existing styles... */
        .service-status-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .service-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .service-status-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }

        .service-status-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        .service-toggle {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #42c3cf;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .service-status-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .service-status-active {
            color: #28a745;
            font-weight: 600;
        }

        .service-status-inactive {
            color: #dc3545;
            font-weight: 600;
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

        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .view-btn {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .view-btn.active {
            background: #42c3cf;
            color: white;
            border-color: #42c3cf;
            box-shadow: 0 2px 10px rgba(66, 195, 207, 0.3);
        }

        .view-btn:hover:not(.active) {
            background: rgba(66, 195, 207, 0.1);
            color: #42c3cf;
            border-color: #42c3cf;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .status-progress-wrapper {
            min-width: 190px;
            max-width: 270px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .progress-steps-container {
            margin-bottom: 12px;
        }

        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin-bottom: 8px;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-bottom: 4px;
            transition: all 0.3s ease;
        }

        .progress-step.active .step-circle {
            background: #42c3cf;
            color: white;
            box-shadow: 0 2px 8px rgba(66, 195, 207, 0.3);
            margin-bottom: 10px;
        }

        .progress-step.inactive .step-circle {
            background: #e9ecef;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .step-label {
            font-size: 0.59rem;
            font-weight: 300;
            text-align: center;
            line-height: 1.2;
            max-width: 50px;
        }

        .progress-step.active .step-label {
            color: #42c3cf;
            font-weight: 600;
        }

        .progress-step.inactive .step-label {
            color: #6c757d;
        }

        .step-connector {
            flex: 1;
            height: 2px;
            margin: 0 4px;
            position: relative;
            top: -12px;
        }

        .step-connector.completed {
            background: #42c3cf;
        }

        .step-connector.pending {
            background: #e9ecef;
        }

        /* Status Action Row - Terpisah dan Rapi */
        .status-action-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .current-status-badge {
            width: 100%;
            text-align: center;
        }

        .status-badge-clean {
            font-size: 0.7rem;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
            display: inline-block;
            min-width: 120px;
        }

        .next-action-btn {
            width: 100%;
        }

        .status-next-clean {
            padding: 6px 12px;
            border-radius: 18px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            white-space: nowrap;
            width: 100%;
            color: white;
        }

        .status-next-clean:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            color: white;
        }

        .status-next-clean.btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .status-next-clean.btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .status-loading-clean {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            margin-top: 8px;
        }

        /* Optimasi lebar tabel */
        .table {
            margin-bottom: 0;
            font-size: 0.7rem;
            min-width: 600px;
        }

        .table th {
            background-color: #42c3cf;
            color: white;
            font-weight: 500;
            border: none;
            padding: 4px 2px;
            font-size: 0.7rem;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
            padding: 4px 2px;
            font-size: 0.7rem;
            line-height: 1.3;
        }

        .table th:first-child {
            border-top-left-radius: 10px;
        }

        .table th:last-child {
            border-top-right-radius: 10px;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .tracking-id {
            font-weight: bold;
            color: #42c3cf;
            background-color: rgba(66, 195, 207, 0.1);
            padding: 3px 6px;
            border-radius: 4px;
            border: 1px dashed #42c3cf;
            display: inline-block;
            font-size: 0.75rem;
        }

        /* Clean Address Styling */
        .address-container {
            max-width: 200px;
        }

        .address-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            line-height: 1.3;
        }

        .address-item.antar {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }

        .address-item.jemput {
            background: #e8f5e8;
            border-left: 3px solid #4caf50;
        }

        .address-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 0.75rem;
            color: white;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .address-icon.antar {
            background: #2196f3;
        }

        .address-icon.jemput {
            background: #4caf50;
        }

        .address-text {
            flex: 1;
            color: #424242;
            word-wrap: break-word;
        }

        .address-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 4px;
        }

        .price-container {
            text-align: center;
        }

        .price-total {
            font-weight: bold;
            color: #28a745;
            font-size: 1rem;
            margin-bottom: 6px;
        }

        .price-breakdown {
            font-size: 0.75rem;
            color: #6c757d;
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
        }

        .layanan-badge {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .btn-action {
            padding: 4px 8px;
            margin: 0 1px;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* Mobile Cards - Hidden on Desktop */
        .mobile-cards {
            display: none;
        }

        .order-card {
            border: 1px solid #dee2e6;
            border-radius: 15px;
            margin-bottom: 15px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .order-card.deleted {
            opacity: 0.7;
            border-color: #dc3545;
        }

        .order-card-header {
            background-color: #42c3cf;
            padding: 15px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-card-header.deleted {
            background-color: #dc3545;
        }

        .order-card-body {
            padding: 15px;
        }

        .order-card-item {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-card-label {
            font-weight: 600;
            min-width: 120px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .order-card-value {
            flex: 1;
        }

        .order-card-footer {
            display: flex;
            gap: 10px;
            padding: 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            flex-wrap: wrap;
        }

        .mobile-address-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid;
        }

        .mobile-address-item.antar {
            border-left-color: #007bff;
        }

        .mobile-address-item.jemput {
            border-left-color: #28a745;
        }

        /* WhatsApp button di kolom pelanggan */
        .customer-info {
            position: relative;
        }

        .whatsapp-mini-btn {
            background: #25d366;
            color: white;
            border: none;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.65rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            transition: all 0.3s ease;
            margin-top: 2px;
        }

        .whatsapp-mini-btn:hover {
            background: #128c7e;
            color: white;
            transform: scale(1.05);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .filter-container {
                padding: 1.25rem;
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

            .page-title {
                font-size: 2rem;
            }

            .order-card-item {
                flex-direction: column;
            }

            .order-card-label {
                margin-bottom: 5px;
                min-width: auto;
            }

            .service-status-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .view-toggle {
                flex-wrap: wrap;
            }

            .table-container {
                padding: 1rem;
            }

            .order-card-footer {
                flex-direction: column;
            }

            .order-card-footer .btn {
                margin-bottom: 5px;
            }

            /* PERBAIKAN: Mobile button styling */
            .order-card-footer .btn {
                color: white !important;
                text-decoration: none;
            }

            .order-card-footer .btn-info {
                background-color: #17a2b8 !important;
                border-color: #17a2b8 !important;
            }

            .order-card-footer .btn-primary {
                background-color: #007bff !important;
                border-color: #007bff !important;
            }

            .order-card-footer .btn-warning {
                background-color: #ffc107 !important;
                border-color: #ffc107 !important;
                color: #212529 !important;
            }

            .order-card-footer .btn-danger {
                background-color: #dc3545 !important;
                border-color: #dc3545 !important;
            }

            .order-card-footer .btn-success {
                background-color: #28a745 !important;
                border-color: #28a745 !important;
            }

            /* Mobile status progress wrapper */
            .status-progress-wrapper {
                min-width: 100%;
                max-width: 100%;
                margin: 10px 0;
            }
            
            .progress-steps {
                margin-bottom: 12px;
            }
            
            .step-circle {
                width: 24px;
                height: 24px;
                font-size: 0.7rem;
            }
            
            .step-label {
                font-size: 0.6rem;
                max-width: 45px;
            }
            
            .status-next-clean {
                font-size: 0.75rem;
                padding: 8px 12px;
            }
            
            .whatsapp-btn-clean {
                font-size: 0.75rem;
                padding: 8px 12px;
            }
        }

        @media (max-width: 576px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
            }

            .view-toggle {
                flex-direction: column;
            }
        }

        /* Desktop Only - Show Table */
        @media (min-width: 769px) {
            .desktop-table {
                display: block;
            }

            .mobile-cards {
                display: none;
            }
        }

        /* Success/Error Message Styling */
        .status-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .status-message.show {
            transform: translateX(0);
        }

        .status-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Tambahan/penyesuaian style dari pesanan.php agar konsisten */
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
        .table tr.table-danger {
            background-color: rgba(220, 53, 69, 0.05);
            opacity: 0.7;
        }
        .table tr.table-danger:hover {
            background-color: rgba(220, 53, 69, 0.1);
        }
        .stat-card.active {
            box-shadow: 0 0 0 3px #42c3cf44, 0 8px 25px rgba(0,0,0,0.15);
            border: 2px solid #42c3cf;
            z-index: 2;
        }
        .stat-card.active .stat-icon {
            filter: brightness(1.1) drop-shadow(0 0 6px #42c3cf66);
        }
        .status-dropdown.payment-status-select {
            border: 2px solid #ffc107;
            background: #fffbe6;
            color: #b8860b;
            transition: all 0.2s;
            font-size: 0.8rem !important;
            padding: 2px 6px !important;
            font-weight: 500;
        }
        .status-dropdown.payment-sudah_dibayar {
            border: 2px solid #28a745 !important;
            background: #eafaf1 !important;
            color: #218838 !important;
        }
        .status-dropdown.payment-belum_dibayar {
            border: 2px solid #fd7e14 !important;
            background: #fff5eb !important;
            color: #d35400 !important;
        }
        .status-dropdown.payment-status-select:focus {
            box-shadow: 0 0 0 2px #42c3cf44;
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
            
            <h1 class="page-title">Manajemen Antar Jemput</h1>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Service Status Container -->
            <div class="service-status-container">
                <div class="service-status-header">
                    <div class="service-status-title">
                        <i class="fas fa-cog"></i>Status Layanan Antar Jemput
                    </div>
                    <form method="POST" class="service-toggle">
                        <span class="<?php echo $is_service_active ? 'service-status-active' : 'service-status-inactive'; ?>">
                            <?php echo $is_service_active ? 'AKTIF' : 'NONAKTIF'; ?>
                        </span>
                        <label class="toggle-switch">
                            <input type="hidden" name="service_status" value="<?php echo $is_service_active ? 'inactive' : 'active'; ?>">
                            <input type="checkbox" <?php echo $is_service_active ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <span class="slider"></span>
                        </label>
                        <input type="hidden" name="toggle_service" value="1">
                    </form>
                </div>
                <div class="service-status-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Status Saat Ini:</strong> 
                            <span class="<?php echo $is_service_active ? 'service-status-active' : 'service-status-inactive'; ?>">
                                <?php echo $is_service_active ? 'Layanan Aktif' : 'Layanan Nonaktif'; ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Terakhir Diperbarui:</strong> 
                            <?php echo formatTanggalSingkat($service_status['updated_at']); ?> 
                            oleh <?php echo $service_status['updated_by']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-dashboard">
                <a href="?status=&show_deleted=0" style="text-decoration:none;" class="stat-card primary<?php echo ($status_filter == '' && !$show_deleted) ? ' active' : ''; ?>">
                    <div class="stat-icon primary">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Semua Pesanan</div>
                </a>
                <a href="?status=<?php echo ($status_filter == 'menunggu' && !$show_deleted) ? '' : 'menunggu'; ?>&show_deleted=0" style="text-decoration:none;" class="stat-card warning<?php echo ($status_filter == 'menunggu' && !$show_deleted) ? ' active' : ''; ?>">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['menunggu']; ?></div>
                    <div class="stat-label">Menunggu</div>
                </a>
                <a href="?status=<?php echo ($status_filter == 'dalam perjalanan' && !$show_deleted) ? '' : 'dalam perjalanan'; ?>&show_deleted=0" style="text-decoration:none;" class="stat-card info<?php echo ($status_filter == 'dalam perjalanan' && !$show_deleted) ? ' active' : ''; ?>">
                    <div class="stat-icon info">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['dalam_perjalanan']; ?></div>
                    <div class="stat-label">Perjalanan</div>
                </a>
                <a href="?status=<?php echo ($status_filter == 'selesai' && !$show_deleted) ? '' : 'selesai'; ?>&show_deleted=0" style="text-decoration:none;" class="stat-card success<?php echo ($status_filter == 'selesai' && !$show_deleted) ? ' active' : ''; ?>">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['selesai']; ?></div>
                    <div class="stat-label">Selesai</div>
                </a>
                <?php if ($deleted_count > 0): ?>
                <a href="?show_deleted=<?php echo $show_deleted ? '0' : '1'; ?>" style="text-decoration:none;" class="stat-card danger<?php echo $show_deleted ? ' active' : ''; ?>">
                    <div class="stat-icon danger">
                        <i class="fas fa-trash"></i>
                    </div>
                    <div class="stat-number"><?php echo $deleted_count; ?></div>
                    <div class="stat-label">Terhapus</div>
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Filter Container -->
            <div class="filter-container">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filter & Pencarian
                </div>

                <!-- View Toggle -->
                <div class="view-toggle">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => '0'])); ?>" 
                       class="view-btn <?php echo !$show_deleted ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i>Data Aktif
                    </a>
                    <?php if ($deleted_count > 0): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['show_deleted' => '1'])); ?>" 
                       class="view-btn <?php echo $show_deleted ? 'active' : ''; ?>">
                        <i class="fas fa-trash me-1"></i>Data Terhapus (<?php echo $deleted_count; ?>)
                    </a>
                    <?php endif; ?>
                </div>
                
                <form method="GET" action="">
                    <input type="hidden" name="show_deleted" value="<?php echo $show_deleted ? '1' : '0'; ?>">
                    <div class="row">
                        <div class="col-12 col-md-3">
                            <div class="mb-3">
                                <label for="search" class="form-label fw-semibold">Cari</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Tracking/Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rentang Tanggal</label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control date-picker" name="date_from" 
                                           placeholder="Dari" value="<?php echo htmlspecialchars($date_from); ?>">
                                    <input type="text" class="form-control date-picker" name="date_to" 
                                           placeholder="Sampai" value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-2">
                            <div class="mb-3">
                                <label for="status" class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="menunggu" <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="dalam perjalanan" <?php echo $status_filter === 'dalam perjalanan' ? 'selected' : ''; ?>>Perjalanan</option>
                                    <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-2">
                            <div class="mb-3">
                                <label for="layanan" class="form-label fw-semibold">Layanan</label>
                                <select class="form-select" id="layanan" name="layanan">
                                    <option value="">Semua Layanan</option>
                                    <option value="antar" <?php echo $layanan_filter === 'antar' ? 'selected' : ''; ?>>Antar</option>
                                    <option value="jemput" <?php echo $layanan_filter === 'jemput' ? 'selected' : ''; ?>>Jemput</option>
                                    <option value="antar-jemput" <?php echo $layanan_filter === 'antar-jemput' ? 'selected' : ''; ?>>Antar & Jemput</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <a href="antar-jemput.php" class="btn btn-outline-secondary">Reset</a>
                                    <button type="submit" class="btn" style="background-color: #42c3cf; color: white;">Filter</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <div class="table-title">
                    <span><i class="fas fa-truck-moving"></i>Daftar Antar Jemput</span>
                    <div class="text-muted">
                        Total: <?php echo $stats['total']; ?> data |
                        Nilai: Rp <?php echo number_format($stats['total_nilai'] ?? 0, 0, ',', '.'); ?>
                    </div>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <!-- Desktop Table -->
                    <div class="desktop-table">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:28px;">No</th>
                                        <th style="width:80px;">Tracking</th>
                                        <th style="width:90px;">Pelanggan</th>
                                        <th style="width:120px;">Alamat</th>
                                        <th style="width:80px;">Waktu</th>
                                        <th style="width:65px;">Harga</th>
                                        <th style="width:120px;">Status</th>
                                        <th style="width:60px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $result->data_seek(0);
                                    $no = 1;
                                    while ($row = $result->fetch_assoc()): 
                                        $total_harga = hitungTotalHarga($row['harga_pesanan'], $row['harga'], $row['status_pembayaran']);
                                        $harga_antar_jemput = !empty($row['harga']) ? $row['harga'] : 5000;
                                        $is_deleted = !empty($row['deleted_at']) && $row['deleted_at'] !== '0000-00-00 00:00:00'; // Perbaikan: Cek juga string '0000-00-00 00:00:00'
                                        $is_selesai = $row['status'] === 'selesai';
                                        $whatsapp_phone = formatPhoneForWhatsApp($row['no_hp']);
                                    ?>
                                        <tr class="<?php echo $is_deleted ? 'table-danger' : ''; ?>" data-antar-id="<?php echo $row['id']; ?>">
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <div class="tracking-id-container">
                                                    <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                                    <button type="button" class="btn btn-sm btn-copy" data-clipboard-text="<?php echo $row['tracking_code']; ?>" title="Salin Kode Tracking">
                                                      <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                                <?php if ($is_deleted): ?>
                                                    <small class="text-danger" style="font-size: 0.75rem;">Dihapus</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; font-size: 0.9rem; line-height: 1.3; margin-bottom: 4px;">
                                                    <?php echo $row['nama_pelanggan_final']; ?>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.8rem;">
                                                    <?php echo $row['no_hp']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="address-container">
                                                    <div style="margin-bottom:10px;">
                                                        <?php echo getLayananBadge($row['layanan']); ?>
                                                    </div>
                                                    <?php if ($row['layanan'] === 'antar' || $row['layanan'] === 'antar-jemput'): ?>
                                                        <div class="address-item antar">
                                                            <div class="address-icon antar">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                            </div>
                                                            <div class="address-text">
                                                                <?php echo htmlspecialchars($row['alamat_antar']); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($row['layanan'] === 'jemput' || $row['layanan'] === 'antar-jemput'): ?>
                                                        <div class="address-item jemput">
                                                            <div class="address-icon jemput">
                                                                <i class="fas fa-home"></i>
                                                            </div>
                                                            <div class="address-text">
                                                                <?php echo htmlspecialchars($row['alamat_jemput']); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.85rem;">
                                                <?php if (!empty($row['waktu_antar']) && $row['waktu_antar'] !== '0000-00-00 00:00:00'): ?>
                                                    <div><?php echo date('H:i d/m/Y', strtotime($row['waktu_antar'])); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['waktu_jemput']) && $row['waktu_jemput'] !== '0000-00-00 00:00:00'): ?>
                                                    <div><?php echo date('H:i d/m/Y', strtotime($row['waktu_jemput'])); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="price-container">
                                                    <div class="text-success fw-bold" style="font-size: 1rem; margin-bottom: 6px;">
                                                        Rp <?php echo number_format($total_harga, 0, ',', '.'); ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: #6c757d; line-height: 1.3; margin-bottom: 8px;">
                                                        <?php if ($row['status_pembayaran'] === 'sudah_dibayar'): ?>
                                                            <div>Antar/Jemput: Rp <?php echo number_format($harga_antar_jemput, 0, ',', '.'); ?></div>
                                                        <?php else: ?>
                                                            <div>Pesanan: Rp <?php echo number_format($row['harga_pesanan'], 0, ',', '.'); ?></div>
                                                            <div>Antar/Jemput: Rp <?php echo number_format($harga_antar_jemput, 0, ',', '.'); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!$is_deleted && $row['status'] === 'menunggu'): ?>
                                                        <button class="btn btn-sm btn-warning" style="padding: 3px 8px; font-size: 0.7rem;"
                                                                data-bs-toggle="modal" data-bs-target="#editHargaModal"
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-harga="<?php echo $harga_antar_jemput; ?>"
                                                                data-tracking="<?php echo $row['tracking_code']; ?>"
                                                                title="Edit Harga">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    <?php else: ?>
                                                        <small class="text-muted" style="font-size: 0.7rem;">
                                                            <i class="fas fa-lock"></i> Harga Terkunci
                                                        </small>
                                                    <?php endif; ?>
                                                    <!-- DROPDOWN STATUS PEMBAYARAN MANUAL -->
                                                    <?php if (!$is_deleted && $row['status'] !== 'selesai'): ?>
                                                        <select class="status-dropdown payment-status-select payment-<?php echo $row['status_pembayaran']; ?>" 
                                                            data-antar-id="<?php echo $row['id']; ?>" 
                                                            data-tracking="<?php echo $row['tracking_code']; ?>" 
                                                            style="margin-top:6px; font-size:0.9rem; border-radius:8px; padding:4px 10px; width:100%; font-weight:600;">
                                                            <option value="belum_dibayar" <?php echo $row['status_pembayaran'] === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                                            <option value="sudah_dibayar" <?php echo $row['status_pembayaran'] === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center;" class="status-cell">
                                                <?php echo getStatusBadgeWithClearProgress($row['status'], $row['id'], $is_deleted, $row['no_hp'], $row['nama_pelanggan_final'], $row['tracking_code']); ?>
                                            </td>
                                            
                                            <td>
                                                <div class="btn-group-vertical" role="group" style="gap: 3px;">
                                                    <?php if ($is_deleted): ?>
                                                        <button class="btn btn-sm btn-success" style="padding: 4px 8px; font-size: 0.75rem;" 
                                                                onclick="restoreData(<?php echo $row['id']; ?>, '<?php echo $row['tracking_code']; ?>')" 
                                                                title="Pulihkan">
                                                            <i class="fas fa-undo"></i> Pulihkan
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-info" style="padding: 7px 8px; font-size: 0.75rem; margin-bottom: 2px; color: white; border-radius: 10px;" 
                                                                data-bs-toggle="modal" data-bs-target="#detailModal"
                                                                onclick="showDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                                                title="Detail">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" style="padding: 7px 8px; font-size: 0.75rem; margin-bottom: 2px; color: white; border-radius: 10px;" 
                                                                onclick="deleteData(<?php echo $row['id']; ?>, '<?php echo $row['tracking_code']; ?>')" 
                                                                title="Hapus">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </button>
                                                    <?php endif; ?>
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
                            $total_harga = hitungTotalHarga($row['harga_pesanan'], $row['harga'], $row['status_pembayaran']);
                            $harga_antar_jemput = !empty($row['harga']) ? $row['harga'] : 5000;
                            $is_deleted = !empty($row['deleted_at']) && $row['deleted_at'] !== '0000-00-00 00:00:00'; // Perbaikan: Cek juga string '0000-00-00 00:00:00'
                            $is_selesai = $row['status'] === 'selesai';
                            $whatsapp_phone = formatPhoneForWhatsApp($row['no_hp']);
                        ?>
                            <div class="order-card <?php echo $is_deleted ? 'deleted' : ''; ?>" data-antar-id="<?php echo $row['id']; ?>">
                                <div class="order-card-header <?php echo $is_deleted ? 'deleted' : ''; ?>">
                                    <div><strong>No. <?php echo $no++; ?></strong></div>
                                    <div><?php echo getLayananBadge($row['layanan']); ?></div>
                                </div>
                                
                                <div class="order-card-body">
                                    <div class="order-card-item">
                                        <div class="order-card-label">Tracking:</div>
                                        <div class="order-card-value">
                                            <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                            <?php if ($is_deleted): ?>
                                                <br><small class="text-danger">Dihapus: <?php echo formatTanggalSingkat($row['deleted_at']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Pelanggan:</div>
                                        <div class="order-card-value">
                                            <?php echo $row['nama_pelanggan_final']; ?><br>
                                            <small class="text-muted"><?php echo $row['no_hp']; ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['layanan'] === 'antar' || $row['layanan'] === 'antar-jemput'): ?>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Alamat Antar:</div>
                                        <div class="order-card-value">
                                            <div class="mobile-address-item antar">
                                                <div style="font-weight: 600; color: #007bff; margin-bottom: 5px;">
                                                    <i class="fas fa-map-marker-alt me-2"></i>Alamat Antar
                                                </div>
                                                <div><?php echo htmlspecialchars($row['alamat_antar']); ?></div>
                                                <?php if (!empty($row['waktu_antar']) && $row['waktu_antar'] !== '0000-00-00 00:00:00'): ?>
                                                <small class="text-muted">Waktu: <?php echo formatTanggalSingkat($row['waktu_antar']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['layanan'] === 'jemput' || $row['layanan'] === 'antar-jemput'): ?>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Alamat Jemput:</div>
                                        <div class="order-card-value">
                                            <div class="mobile-address-item jemput">
                                                <div style="font-weight: 600; color: #28a745; margin-bottom: 5px;">
                                                    <i class="fas fa-home me-2"></i>Alamat Jemput
                                                </div>
                                                <div><?php echo htmlspecialchars($row['alamat_jemput']); ?></div>
                                                <?php if (!empty($row['waktu_jemput']) && $row['waktu_jemput'] !== '0000-00-00 00:00:00'): ?>
                                                <small class="text-muted">Waktu: <?php echo formatTanggalSingkat($row['waktu_jemput']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="order-card-item">
                                        <div class="order-card-label">Total Harga:</div>
                                        <div class="order-card-value">
                                            <div class="price-total">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></div>
                                            <div class="price-breakdown">
                                                <?php if ($row['status_pembayaran'] === 'sudah_dibayar'): ?>
                                                    Antar/Jemput: Rp <?php echo number_format($harga_antar_jemput, 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    Pesanan: Rp <?php echo number_format($row['harga_pesanan'], 0, ',', '.'); ?><br>
                                                    Antar/Jemput: Rp <?php echo number_format($harga_antar_jemput, 0, ',', '.'); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$is_deleted && $row['status'] === 'menunggu'): ?>
                                                <button class="btn btn-warning btn-sm w-100 mt-2" data-bs-toggle="modal" data-bs-target="#editHargaModal"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-harga="<?php echo $harga_antar_jemput; ?>"
                                                        data-tracking="<?php echo $row['tracking_code']; ?>">
                                                    <i class="fas fa-edit"></i> Edit Harga
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted"><i class="fas fa-lock"></i> Harga Terkunci</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="order-card-item">
                                        <div class="order-card-label">Status:</div>
                                        <div class="order-card-value status-mobile"><?php echo getStatusBadgeWithClearProgress($row['status'], $row['id'], $is_deleted, $row['no_hp'], $row['nama_pelanggan_final'], $row['tracking_code']); ?></div>
                                    </div>
                                    <?php if (!$is_deleted && !$is_selesai): ?>
                                        <select class="status-dropdown payment-status-select payment-<?php echo $row['status_pembayaran']; ?>" 
                                            data-antar-id="<?php echo $row['id']; ?>" 
                                            data-tracking="<?php echo $row['tracking_code']; ?>" 
                                            style="margin-top:6px; font-size:0.9rem; border-radius:8px; padding:4px 10px; width:100%; font-weight:600;">
                                            <option value="belum_dibayar" <?php echo $row['status_pembayaran'] === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                            <option value="sudah_dibayar" <?php echo $row['status_pembayaran'] === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="order-card-footer">
                                    <?php if ($is_deleted): ?>
                                        <button class="btn btn-success btn-sm flex-fill" 
                                                onclick="restoreData(<?php echo $row['id']; ?>, '<?php echo $row['tracking_code']; ?>')">
                                            <i class="fas fa-undo"></i> Pulihkan
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-info btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#detailModal"
                                                onclick="showDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="deleteData(<?php echo $row['id']; ?>, '<?php echo $row['tracking_code']; ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-truck"></i>
                        <h5>Tidak Ada Data</h5>
                        <p><?php echo $show_deleted ? 'Tidak ada data antar jemput yang terhapus.' : 'Tidak ada data antar jemput yang ditemukan.'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail (Sekaligus Edit Status) -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#42c3cf;color:#fff;">
                    <h5 class="modal-title">Detail Antar Jemput</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Detail content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Harga -->
    <div class="modal fade" id="editHargaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Harga Antar Jemput</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="antar_jemput_id" id="edit_harga_id">
                        <div class="mb-3">
                            <label class="form-label">Tracking Code</label>
                            <input type="text" class="form-control" id="edit_harga_tracking" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="harga_custom" class="form-label">Harga Antar/Jemput (Rp)</label>
                            <input type="number" class="form-control" name="harga_custom" id="harga_custom" min="0" step="500" required>
                            <div class="form-text">Harga default: Rp 5.000. Ubah jika alamat terlalu jauh.</div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Harga tidak dapat diubah setelah status antar jemput menjadi "Selesai".
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_harga" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
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

            // Handle edit harga modal
            const editHargaModal = document.getElementById('editHargaModal');
            editHargaModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const harga = button.getAttribute('data-harga');
                const tracking = button.getAttribute('data-tracking');

                document.getElementById('edit_harga_id').value = id;
                document.getElementById('harga_custom').value = harga;
                document.getElementById('edit_harga_tracking').value = tracking;
            });

            var clipboard = new ClipboardJS('.btn-copy');
            clipboard.on('success', function(e) {
              const button = e.trigger;
              const originalTitle = button.getAttribute('title') || 'Salin';
              button.setAttribute('title', 'Tersalin!');
              button.classList.add('copied');
              setTimeout(function() {
                button.setAttribute('title', originalTitle);
                button.classList.remove('copied');
              }, 1200);
              e.clearSelection();
            });
        });

        // FITUR BARU: Inline Status Update dengan AJAX
        function updateStatusInline(id, currentStatus) {
            const container = document.getElementById('status-container-' + id);
            const loadingElement = document.getElementById('loading-' + id);
            
            // Show loading
            container.querySelector('.status-action-row').style.display = 'none';
            loadingElement.classList.remove('d-none');
            
            // Prepare form data
            const formData = new FormData();
            formData.append('antar_jemput_id', id);
            formData.append('current_status', currentStatus);
            formData.append('ajax_status_update', '1');
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showStatusMessage(data.message, 'success');
                    
                    // Reload page after short delay to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showStatusMessage(data.message, 'error');
                    
                    // Hide loading and show original content
                    loadingElement.classList.add('d-none');
                    container.querySelector('.status-action-row').style.display = 'flex';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatusMessage('Terjadi kesalahan saat memperbarui status', 'error');
                
                // Hide loading and show original content
                loadingElement.classList.add('d-none');
                container.querySelector('.status-action-row').style.display = 'flex';
            });
        }

        // FUNGSI UNTUK MENGIRIM WHATSAPP MESSAGE DENGAN WA.ME
        function sendWhatsAppMessage(id, phone, name, tracking) {
            const button = document.querySelector(`[onclick*="sendWhatsAppMessage(${id}"]`);
            
            if (!button) {
                console.error('Button not found for ID:', id);
                return;
            }
            
            // Add loading state
            button.classList.add('loading');
            button.innerHTML = '<i class="fab fa-whatsapp me-1"></i>Mengirim...';
            button.disabled = true;
            
            // Get order details via AJAX
            const formData = new FormData();
            formData.append('get_order_details', '1');
            formData.append('antar_jemput_id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Generate detailed WhatsApp message
                    const orderDetails = data.orderDetails;
                    const message = generateDetailedWhatsAppMessage(orderDetails);
                    
                    // Open WhatsApp with wa.me
                    const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                    window.open(whatsappUrl, '_blank');
                    
                    // Show success state
                    button.classList.remove('loading');
                    button.classList.add('success');
                    button.innerHTML = '<i class="fas fa-check me-1"></i>Terkirim!';
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        button.classList.remove('success');
                        button.innerHTML = '<i class="fab fa-whatsapp me-1"></i>Hubungi Pelanggan';
                        button.disabled = false;
                    }, 3000);
                    
                    showStatusMessage('WhatsApp berhasil dibuka dengan pesan detail lengkap!', 'success');
                } else {
                    throw new Error(data.message || 'Gagal mendapatkan detail pesanan');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Fallback: Generate simple message and open WhatsApp
                const simpleMessage = `*ZEEA LAUNDRY - LAYANAN ANTAR JEMPUT*\n\nHalo *${name}*,\n\nTim Zeea Laundry sedang dalam perjalanan untuk mengantar cucian Anda.\nMohon bersiap dan pastikan ada yang bisa menerima di lokasi.\n\n*Detail Pesanan:*\n• Kode Tracking: *${tracking}*\n\nInformasi selengkapnya silahkan kunjungi : https://laundry.destio.my.id/\n\n*ZEEA LAUNDRY*\nRT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\nWhatsApp: 0895395442010`;
                
                const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(simpleMessage)}`;
                window.open(whatsappUrl, '_blank');
                
                // Reset button
                button.classList.remove('loading');
                button.innerHTML = '<i class="fab fa-whatsapp me-1"></i>Hubungi Pelanggan';
                button.disabled = false;
                
                showStatusMessage('WhatsApp dibuka dengan pesan sederhana (detail lengkap tidak tersedia)', 'error');
            });
        }

        // Function to generate detailed WhatsApp message
        function generateDetailedWhatsAppMessage(orderDetails) {
            const customerPageUrl = `https://laundry.destio.my.id/pelanggan/index.php?code=${encodeURIComponent(orderDetails.tracking_code)}`;
            
            let message = "*ZEEA LAUNDRY - LAYANAN ANTAR JEMPUT*\n\n";
            message += `Halo *${orderDetails.nama_pelanggan}*,\n\n`;
            message += "*Tim Zeea Laundry sedang dalam perjalanan untuk mengantar cucian Anda.*\n";
            message += "Mohon bersiap dan pastikan ada yang bisa menerima di lokasi.\n\n";
            
            message += "*DETAIL PESANAN:*\n";
            message += `• Kode Tracking: *${orderDetails.tracking_code}*\n`;
            message += `• Layanan: *${orderDetails.layanan.charAt(0).toUpperCase() + orderDetails.layanan.slice(1).replace('-', ' & ')}*\n`;
            
            // Detail alamat
            if (orderDetails.alamat_antar && (orderDetails.layanan === 'antar' || orderDetails.layanan === 'antar-jemput')) {
                message += `• Alamat Antar: ${orderDetails.alamat_antar}\n`;
                if (orderDetails.waktu_antar && orderDetails.waktu_antar !== '0000-00-00 00:00:00') {
                    const waktuAntar = new Date(orderDetails.waktu_antar).toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    message += `• Waktu Antar: ${waktuAntar} WIB\n`;
                }
            }
            
            if (orderDetails.alamat_jemput && (orderDetails.layanan === 'jemput' || orderDetails.layanan === 'antar-jemput')) {
                message += `• Alamat Jemput: ${orderDetails.alamat_jemput}\n`;
                if (orderDetails.waktu_jemput && orderDetails.waktu_jemput !== '0000-00-00 00:00:00') {
                    const waktuJemput = new Date(orderDetails.waktu_jemput).toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    message += `• Waktu Jemput: ${waktuJemput} WIB\n`;
                }
            }
            
            // Detail paket jika ada
            if (orderDetails.paket_list && orderDetails.berat_list) {
                const paketArray = orderDetails.paket_list.split(', ');
                const beratArray = orderDetails.berat_list.split(', ');
                
                message += "\n*DETAIL PAKET LAUNDRY:*\n";
                for (let i = 0; i < paketArray.length; i++) {
                    const beratValue = beratArray[i] || '0';
                    const beratFormatted = parseFloat(beratValue).toFixed(2).replace('.', ',');
                    message += `• ${paketArray[i]}: ${beratFormatted} kg\n`;
                }
            }
            
            // Detail harga
            const hargaAntarJemput = orderDetails.harga_custom || 5000;
            const totalHargaPesanan = orderDetails.total_harga_pesanan || 0;
            const statusPembayaran = orderDetails.status_pembayaran || 'sudah_dibayar';
            const totalHarga = statusPembayaran === 'sudah_dibayar' ? hargaAntarJemput : (totalHargaPesanan + hargaAntarJemput);
            
            message += "\n*DETAIL BIAYA:*\n";
            if (totalHargaPesanan > 0) {
                if (statusPembayaran === 'sudah_dibayar') {
                    message += `• Biaya Laundry: Rp ${totalHargaPesanan.toLocaleString('id-ID')} *(Sudah Dibayar)*\n`;
                } else {
                    message += `• Biaya Laundry: Rp ${totalHargaPesanan.toLocaleString('id-ID')}\n`;
                }
            }
            message += `• Biaya Antar/Jemput: Rp ${hargaAntarJemput.toLocaleString('id-ID')}\n`;
            message += `• *Total yang harus dibayar: Rp ${totalHarga.toLocaleString('id-ID')}*\n`;
            
            if (statusPembayaran === 'belum_dibayar' && totalHargaPesanan > 0) {
                message += "\n*PERHATIAN:* Pembayaran laundry belum lunas. Total yang harus dibayar termasuk biaya laundry dan antar/jemput.\n";
            }
            
            message += "\n*CEK STATUS PESANAN:*\n";
            message += "Untuk informasi lebih lengkap, silakan kunjungi:\n";
            message += `${customerPageUrl}\n\n`;
            message += `Atau masukkan kode tracking *${orderDetails.tracking_code}* di halaman pelanggan website kami.\n\n`;
            
            message += "Estimasi tiba: 15-30 menit dari sekarang.\n";
            message += "Jika ada kendala, kami akan menghubungi Anda.\n\n";
            
            message += "Terima kasih telah mempercayakan layanan laundry kepada kami!\n\n";
            message += "*ZEEA LAUNDRY*\n";
            message += "RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n";
            message += "WhatsApp: 0895395442010\n";
            message += "Website: https://laundry.destio.my.id/";
            
            return message;
        }

        // Function to show status messages
        function showStatusMessage(message, type) {
            // Remove existing message if any
            const existingMessage = document.querySelector('.status-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create new message element
            const messageElement = document.createElement('div');
            messageElement.className = `status-message ${type}`;
            messageElement.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span>${message}</span>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; font-size: 1.2rem; cursor: pointer; margin-left: 10px;">×</button>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(messageElement);
            
            // Show message
            setTimeout(() => {
                messageElement.classList.add('show');
            }, 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                if (messageElement.parentElement) {
                    messageElement.classList.remove('show');
                    setTimeout(() => {
                        if (messageElement.parentElement) {
                            messageElement.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Show detail modal (dengan dropdown status & tombol simpan)
        function showDetail(data) {
            // Ambil data terbaru via AJAX
            const content = document.getElementById('detailContent');
            const formData = new FormData();
            formData.append('get_order_details', '1');
            formData.append('antar_jemput_id', data.id);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (!res.success) {
                    content.innerHTML = `<div class='alert alert-danger'>${res.message || 'Gagal mengambil detail pesanan.'}</div>`;
                    return;
                }
                const d = res.orderDetails;
                // --- FIX: pastikan harga numerik agar tidak NaN ---
                const hargaPesanan = Number(d.harga_pesanan) || 0;
                const hargaCustom = Number(d.harga_custom) || 5000;
                const totalHarga = d.status_pembayaran === 'sudah_dibayar' 
                    ? hargaCustom 
                    : (hargaPesanan + hargaCustom);
                let alamatHtml = '';
                if (d.layanan === 'antar' || d.layanan === 'antar-jemput') {
                    alamatHtml += `
                        <div class="mb-3">
                            <label class="form-label fw-bold text-primary">
                                <i class="fas fa-map-marker-alt me-2"></i>Alamat Antar
                            </label>
                            <div class="form-control-plaintext border rounded p-2 bg-light">
                                ${d.alamat_antar || '-'}
                            </div>
                            ${d.waktu_antar && d.waktu_antar !== '0000-00-00 00:00:00' ? `<small class="text-muted">Waktu: ${formatTanggalSingkat(d.waktu_antar)}</small>` : ''}
                        </div>
                    `;
                }
                if (d.layanan === 'jemput' || d.layanan === 'antar-jemput') {
                    alamatHtml += `
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-home me-2"></i>Alamat Jemput
                            </label>
                            <div class="form-control-plaintext border rounded p-2 bg-light">
                                ${d.alamat_jemput || '-'}
                            </div>
                        </div>
                    `;
                    // Tambahkan waktu penjemputan
                    if (d.waktu_jemput && d.waktu_jemput !== '0000-00-00 00:00:00') {
                        const waktuJemput = new Date(d.waktu_jemput);
                        const waktuJemputStr = waktuJemput.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' ' + waktuJemput.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
                        alamatHtml += `<div class="mb-2"><span class="fw-bold">Waktu Penjemputan:</span> <span class="text-primary">${waktuJemputStr}</span></div>`;
                    }
                }
                content.innerHTML = `
                    <form id="formUpdateStatus" autocomplete="off">
                    <input type="hidden" name="antar_jemput_id" value="${d.id}">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tracking Code</label>
                                <div class="form-control-plaintext">
                                    <span class="tracking-id">${d.tracking_code}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Pelanggan</label>
                                <div class="form-control-plaintext">${d.nama_pelanggan}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">No. HP</label>
                                <div class="form-control-plaintext">${d.no_hp}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Layanan</label>
                                <div class="form-control-plaintext">${getLayananBadgeText(d.layanan)}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select class="form-select" name="status_baru" id="status_baru_modal" data-antar-id="${d.id}" required>
                                    <option value="menunggu"${d.status === 'menunggu' ? ' selected' : ''}>Menunggu</option>
                                    <option value="dalam perjalanan"${d.status === 'dalam perjalanan' ? ' selected' : ''}>Perjalanan</option>
                                    <option value="selesai"${d.status === 'selesai' ? ' selected' : ''}>Selesai</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Total Harga</label>
                                <div class="form-control-plaintext">
                                    <span class="text-success fw-bold fs-5">Rp ${totalHarga.toLocaleString('id-ID')}</span>
                                    <br><small class="text-muted">
                                        ${d.status_pembayaran === 'sudah_dibayar' 
                                            ? `Antar/Jemput: Rp ${hargaCustom.toLocaleString('id-ID')}` 
                                            : `Pesanan: Rp ${hargaPesanan.toLocaleString('id-ID')}<br>Antar/Jemput: Rp ${hargaCustom.toLocaleString('id-ID')}`
                                        }
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${alamatHtml}
                    </form>
                `;
            });
        }

        function getLayananBadgeText(layanan) {
            switch (layanan) {
                case 'antar': return '<span class="badge bg-primary">Antar</span>';
                case 'jemput': return '<span class="badge bg-success">Jemput</span>';
                case 'antar-jemput': return '<span class="badge bg-info">Antar & Jemput</span>';
                default: return '<span class="badge bg-secondary">Unknown</span>';
            }
        }

        function getStatusBadgeText(status) {
            switch (status) {
                case 'menunggu': return '<span class="badge bg-warning text-dark">Menunggu</span>';
                case 'dalam perjalanan': return '<span class="badge bg-info">Dalam Perjalanan</span>';
                case 'selesai': return '<span class="badge bg-success">Selesai</span>';
                default: return '<span class="badge bg-secondary">Unknown</span>';
            }
        }

        function formatTanggalSingkat(tanggal) {
            if (!tanggal || tanggal === '0000-00-00 00:00:00') return '-';
            const date = new Date(tanggal);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function deleteData(id, tracking) {
            if (confirm(`Apakah Anda yakin ingin menghapus data antar jemput dengan tracking ${tracking}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="antar_jemput_id" value="${id}">
                    <input type="hidden" name="soft_delete" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function restoreData(id, tracking) {
            if (confirm(`Apakah Anda yakin ingin memulihkan data antar jemput dengan tracking ${tracking}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="antar_jemput_id" value="${id}">
                    <input type="hidden" name="restore" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Event listener: update status langsung saat dropdown berubah
        const statusSelect = document.getElementById('status_baru_modal');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const antarId = this.getAttribute('data-antar-id');
                const statusBaru = this.value;
                const formData = new FormData();
                formData.append('update_status_ajax', '1');
                formData.append('antar_jemput_id', antarId);
                formData.append('status_baru', statusBaru);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(dataRes => {
                    if (dataRes.success) {
                        showStatusMessage(dataRes.message, 'success');
                        // Update cell status di tabel (desktop)
                        fetch(window.location.pathname + `?get_status_badge=1&id=${antarId}`)
                            .then(r => r.text())
                            .then(html => {
                                // Desktop
                                const statusCell = document.querySelector(`tr[data-antar-id="${antarId}"] td.status-cell`);
                                if (statusCell) statusCell.innerHTML = html;
                                // Mobile
                                const mobileStatus = document.querySelector(`.order-card[data-antar-id="${antarId}"] .order-card-value.status-mobile`);
                                if (mobileStatus) mobileStatus.innerHTML = html;
                            });
                    } else {
                        showStatusMessage(dataRes.message, 'error');
                    }
                })
                .catch(() => showStatusMessage('Gagal update status', 'error'));
            });
        }

        // Event listener: update status langsung saat dropdown di modal detail berubah
        document.addEventListener('shown.bs.modal', function(e) {
            if (e.target && e.target.id === 'detailModal') {
                var statusSelect = document.getElementById('status_baru_modal');
                if (statusSelect) {
                    statusSelect.onchange = function() {
                        const antarId = this.getAttribute('data-antar-id');
                        const statusBaru = this.value;
                        const formData = new FormData();
                        formData.append('update_status_ajax', '1');
                        formData.append('antar_jemput_id', antarId);
                        formData.append('status_baru', statusBaru);
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(dataRes => {
                            if (dataRes.success) {
                                showStatusMessage(dataRes.message, 'success');
                                setTimeout(() => window.location.reload(), 1000); // reload otomatis setelah update status sukses
                                // ...update cell status/harga jika ingin tanpa reload...
                            } else {
                                showStatusMessage(dataRes.message, 'error');
                            }
                        })
                        .catch(() => showStatusMessage('Gagal update status', 'error'));
                    };
                }
            }
        });

        document.querySelectorAll('.status-dropdown[data-antar-id]').forEach(function(drop) {
            drop.addEventListener('change', function() {
                var antarId = this.getAttribute('data-antar-id');
                var tracking = this.getAttribute('data-tracking');
                var value = this.value;
                this.disabled = true;
                fetch(window.location.href, {
                    method: 'POST',
                    body: new URLSearchParams({
                        update_payment_status: 1,
                        antar_jemput_id: antarId,
                        tracking_code: tracking,
                        status_pembayaran: value
                    })
                })
                .then(r => r.json())
                .then(data => {
                    showStatusMessage(data.message, data.success ? 'success' : 'error');
                    if (data.success) setTimeout(()=>window.location.reload(), 1000);
                    else this.disabled = false;
                })
                .catch(()=>{
                    showStatusMessage('Gagal update status pembayaran', 'error');
                    this.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
