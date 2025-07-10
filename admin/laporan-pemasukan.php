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
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$jenis_laporan = isset($_GET['jenis']) ? $_GET['jenis'] : 'semua'; // semua, pesanan, antar_jemput
$export_type = isset($_GET['export']) ? $_GET['export'] : '';

// Fungsi untuk mendapatkan data laporan berdasarkan periode dan jenis
function getLaporanData($conn, $periode, $bulan, $tahun, $jenis_laporan) {
    if ($periode === 'harian') {
        // Ambil semua tanggal dalam bulan yang dipilih
        $start_date = $bulan . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        if ($jenis_laporan === 'pesanan') {
            // Hanya pesanan laundry
            $query = "SELECT 
                DATE(p.waktu) as tanggal,
                SUM(p.harga) as total_harga,
                COUNT(DISTINCT p.id) as jumlah_pesanan,
                0 as jumlah_antar_jemput
            FROM pesanan p 
            WHERE DATE(p.waktu) BETWEEN ? AND ? 
            AND p.status_pembayaran = 'sudah_dibayar'
            AND p.deleted_at IS NULL
            GROUP BY DATE(p.waktu)
            ORDER BY DATE(p.waktu) ASC";
            
        } elseif ($jenis_laporan === 'antar_jemput') {
            // Hanya layanan antar jemput
            $query = "SELECT 
                DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) as tanggal,
                SUM(COALESCE(aj.harga, 5000)) as total_harga,
                0 as jumlah_pesanan,
                COUNT(DISTINCT aj.id) as jumlah_antar_jemput
            FROM antar_jemput aj 
            WHERE DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) BETWEEN ? AND ? 
            AND aj.status = 'selesai'
            -- PERBAIKAN: Mengubah kondisi deleted_at untuk menangani NULL dengan benar
            AND aj.deleted_at IS NULL
            GROUP BY DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)))
            ORDER BY DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) ASC";
            
        } else {
            // Gabungan semua (pesanan + antar jemput)
            $query = "SELECT 
                tanggal,
                SUM(total_harga) as total_harga,
                SUM(jumlah_pesanan) as jumlah_pesanan,
                SUM(jumlah_antar_jemput) as jumlah_antar_jemput
            FROM (
                SELECT 
                    DATE(p.waktu) as tanggal,
                    SUM(p.harga) as total_harga,
                    COUNT(DISTINCT p.id) as jumlah_pesanan,
                    0 as jumlah_antar_jemput
                FROM pesanan p 
                WHERE DATE(p.waktu) BETWEEN ? AND ? 
                AND p.status_pembayaran = 'sudah_dibayar'
                AND p.deleted_at IS NULL
                GROUP BY DATE(p.waktu)
                
                UNION ALL
                
                SELECT 
                    DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) as tanggal,
                    SUM(COALESCE(aj.harga, 5000)) as total_harga,
                    0 as jumlah_pesanan,
                    COUNT(DISTINCT aj.id) as jumlah_antar_jemput
                FROM antar_jemput aj 
                WHERE DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) BETWEEN ? AND ? 
                AND aj.status = 'selesai'
                -- PERBAIKAN: Mengubah kondisi deleted_at untuk menangani NULL dengan benar
                AND aj.deleted_at IS NULL
                GROUP BY DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)))
            ) combined
            GROUP BY tanggal
            ORDER BY tanggal ASC";
        }
        
        $stmt = $conn->prepare($query);
        if ($jenis_laporan === 'semua') {
            $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data_exists = [];
        while ($row = $result->fetch_assoc()) {
            $data_exists[$row['tanggal']] = $row;
        }
        
        // Generate semua tanggal dalam bulan
        $laporan_data = [];
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        
        while ($current_date <= $end_date_obj) {
            $date_str = $current_date->format('Y-m-d');
            if (isset($data_exists[$date_str])) {
                $laporan_data[] = $data_exists[$date_str];
            } else {
                $laporan_data[] = [
                    'tanggal' => $date_str,
                    'total_harga' => 0,
                    'jumlah_pesanan' => 0,
                    'jumlah_antar_jemput' => 0
                ];
            }
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $laporan_data;
        
    } else {
        // Laporan bulanan - tampilkan semua bulan dalam tahun
        if ($jenis_laporan === 'pesanan') {
            // Hanya pesanan laundry
            $query = "SELECT 
                DATE_FORMAT(p.waktu, '%Y-%m') as bulan,
                SUM(p.harga) as total_harga,
                COUNT(DISTINCT p.id) as jumlah_pesanan,
                0 as jumlah_antar_jemput
            FROM pesanan p 
            WHERE YEAR(p.waktu) = ? 
            AND p.status_pembayaran = 'sudah_dibayar'
            AND p.deleted_at IS NULL
            GROUP BY DATE_FORMAT(p.waktu, '%Y-%m')
            ORDER BY DATE_FORMAT(p.waktu, '%Y-%m') ASC";
            
        } elseif ($jenis_laporan === 'antar_jemput') {
            // Hanya layanan antar jemput
            $query = "SELECT 
                DATE_FORMAT(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)), '%Y-%m') as bulan,
                SUM(COALESCE(aj.harga, 5000)) as total_harga,
                0 as jumlah_pesanan,
                COUNT(DISTINCT aj.id) as jumlah_antar_jemput
            FROM antar_jemput aj 
            WHERE YEAR(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = ? 
            AND aj.status = 'selesai'
            -- PERBAIKAN: Mengubah kondisi deleted_at untuk menangani NULL dengan benar
            AND aj.deleted_at IS NULL
            GROUP BY DATE_FORMAT(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)), '%Y-%m')
            ORDER BY DATE_FORMAT(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)), '%Y-%m') ASC";
            
        } else {
            // Gabungan semua (pesanan + antar jemput)
            $query = "SELECT 
                bulan,
                SUM(total_harga) as total_harga,
                SUM(jumlah_pesanan) as jumlah_pesanan,
                SUM(jumlah_antar_jemput) as jumlah_antar_jemput
            FROM (
                SELECT 
                    DATE_FORMAT(p.waktu, '%Y-%m') as bulan,
                    SUM(p.harga) as total_harga,
                    COUNT(DISTINCT p.id) as jumlah_pesanan,
                    0 as jumlah_antar_jemput
                FROM pesanan p 
                WHERE YEAR(p.waktu) = ? 
                AND p.status_pembayaran = 'sudah_dibayar'
                AND p.deleted_at IS NULL
                GROUP BY DATE_FORMAT(p.waktu, '%Y-%m')
                
                UNION ALL
                
                SELECT 
                    DATE_FORMAT(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)), '%Y-%m') as bulan,
                    SUM(COALESCE(aj.harga, 5000)) as total_harga,
                    0 as jumlah_pesanan,
                    COUNT(DISTINCT aj.id) as jumlah_antar_jemput
                FROM antar_jemput aj 
                WHERE YEAR(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = ? 
                AND aj.status = 'selesai'
                -- PERBAIKAN: Mengubah kondisi deleted_at untuk menangani NULL dengan benar
                AND aj.deleted_at IS NULL
                GROUP BY DATE_FORMAT(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)), '%Y-%m')
            ) combined
            GROUP BY bulan
            ORDER BY bulan ASC";
        }
        
        $stmt = $conn->prepare($query);
        if ($jenis_laporan === 'semua') {
            $stmt->bind_param("ss", $tahun, $tahun);
        } else {
            $stmt->bind_param("s", $tahun);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data_exists = [];
        while ($row = $result->fetch_assoc()) {
            $data_exists[$row['bulan']] = $row;
        }
        
        // Generate semua bulan dalam tahun
        $laporan_data = [];
        for ($i = 1; $i <= 12; $i++) {
            $month_str = $tahun . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            if (isset($data_exists[$month_str])) {
                $laporan_data[] = $data_exists[$month_str];
            } else {
                $laporan_data[] = [
                    'bulan' => $month_str,
                    'total_harga' => 0,
                    'jumlah_pesanan' => 0,
                    'jumlah_antar_jemput' => 0
                ];
            }
        }
        
        return $laporan_data;
    }
}

// HANDLE EXPORT - Jika ada parameter export, langsung export dan exit
if (!empty($export_type)) {
    $laporan_data = getLaporanData($conn, $periode, $bulan, $tahun, $jenis_laporan);
    
    if ($export_type === 'excel') {
        // Export to Excel
        $filename = "Laporan_Pemasukan_" . ucfirst($jenis_laporan) . "_" . ($periode === 'harian' ? str_replace('-', '_', $bulan) : $tahun) . ".xls";
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo '<html>';
        echo '<head><meta charset="UTF-8"></head>';
        echo '<body>';
        echo '<table border="1">';
        echo '<tr><td colspan="5" style="text-align: center; font-weight: bold; font-size: 16px;">LAPORAN PEMASUKAN ZEEA LAUNDRY</td></tr>';
        echo '<tr><td colspan="5" style="text-align: center; font-weight: bold;">';
        if ($periode === 'harian') {
            echo 'Periode: ' . formatBulanIndonesia($bulan);
        } else {
            echo 'Periode: Tahun ' . $tahun;
        }
        echo '</td></tr>';
        echo '<tr><td colspan="5" style="text-align: center;">Jenis Laporan: ' . getJenisLaporanText($jenis_laporan) . '</td></tr>';
        echo '<tr><td colspan="5" style="text-align: center;">Dicetak pada: ' . date('d F Y H:i:s') . '</td></tr>';
        echo '<tr><td colspan="5"></td></tr>';
        
        // Header
        echo '<tr style="background-color: #42c3cf; color: white; font-weight: bold;">';
        echo '<td>' . ($periode === 'harian' ? 'Tanggal' : 'Bulan') . '</td>';
        echo '<td>Pesanan Laundry</td>';
        echo '<td>Antar Jemput</td>';
        echo '<td>Total Transaksi</td>';
        echo '<td>Total Pemasukan</td>';
        echo '</tr>';
        
        // Data
        $total_pemasukan = 0;
        $total_pesanan = 0;
        $total_antar_jemput = 0;
        
        foreach ($laporan_data as $row) {
            $pemasukan = $periode === 'harian' ? $row['total_harga'] : $row['total_harga'];
            $pesanan = $periode === 'harian' ? $row['jumlah_pesanan'] : $row['jumlah_pesanan'];
            $antar_jemput = $periode === 'harian' ? $row['jumlah_antar_jemput'] : $row['jumlah_antar_jemput'];
            
            $total_pemasukan += $pemasukan;
            $total_pesanan += $pesanan;
            $total_antar_jemput += $antar_jemput;
            
            echo '<tr>';
            if ($periode === 'harian') {
                echo '<td>' . formatTanggalSingkat($row['tanggal']) . '</td>';
            } else {
                echo '<td>' . formatBulanIndonesia($row['bulan']) . '</td>';
            }
            echo '<td style="text-align: center;">' . $pesanan . '</td>';
            echo '<td style="text-align: center;">' . $antar_jemput . '</td>';
            echo '<td style="text-align: center;">' . ($pesanan + $antar_jemput) . '</td>';
            echo '<td style="text-align: right;">' . number_format($pemasukan, 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        
        // Total
        echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
        echo '<td>TOTAL</td>';
        echo '<td style="text-align: center;">' . $total_pesanan . '</td>';
        echo '<td style="text-align: center;">' . $total_antar_jemput . '</td>';
        echo '<td style="text-align: center;">' . ($total_pesanan + $total_antar_jemput) . '</td>';
        echo '<td style="text-align: right;">' . number_format($total_pemasukan, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit();
        
    } elseif ($export_type === 'pdf') {
        // Export to PDF - Simple HTML to PDF using browser print
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Laporan Pemasukan</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px; 
                    margin: 20px;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                }
                .header h2 { 
                    margin: 5px 0; 
                    color: #42c3cf; 
                    font-size: 18px;
                }
                .header h3 { 
                    margin: 5px 0; 
                    color: #666; 
                    font-size: 14px;
                }
                .header p {
                    margin: 10px 0;
                    font-size: 11px;
                    color: #888;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 10px; 
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                    text-align: left; 
                }
                th { 
                    background-color: #42c3cf; 
                    color: white; 
                    font-weight: bold; 
                    text-align: center;
                }
                .total-row { 
                    background-color: #f8f9fa; 
                    font-weight: bold; 
                }
                .text-center { 
                    text-align: center; 
                }
                .text-right { 
                    text-align: right; 
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>LAPORAN PEMASUKAN ZEEA LAUNDRY</h2>
                <h3><?php 
                    if ($periode === 'harian') {
                        echo 'Periode: ' . formatBulanIndonesia($bulan);
                    } else {
                        echo 'Periode: Tahun ' . $tahun;
                    }
                ?></h3>
                <p>Jenis Laporan: <?php echo getJenisLaporanText($jenis_laporan); ?></p>
                <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="25%"><?php echo $periode === 'harian' ? 'Tanggal' : 'Bulan'; ?></th>
                        <th width="15%">Pesanan Laundry</th>
                        <th width="15%">Antar Jemput</th>
                        <th width="15%">Total Transaksi</th>
                        <th width="30%">Total Pemasukan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_pemasukan = 0;
                    $total_pesanan = 0;
                    $total_antar_jemput = 0;
                    
                    foreach ($laporan_data as $row): 
                        $pemasukan = $periode === 'harian' ? $row['total_harga'] : $row['total_harga'];
                        $pesanan = $periode === 'harian' ? $row['jumlah_pesanan'] : $row['jumlah_pesanan'];
                        $antar_jemput = $periode === 'harian' ? $row['jumlah_antar_jemput'] : $row['jumlah_antar_jemput'];
                        
                        $total_pemasukan += $pemasukan;
                        $total_pesanan += $pesanan;
                        $total_antar_jemput += $antar_jemput;
                    ?>
                        <tr>
                            <td><?php 
                                if ($periode === 'harian') {
                                    echo formatTanggalSingkat($row['tanggal']);
                                } else {
                                    echo formatBulanIndonesia($row['bulan']);
                                }
                            ?></td>
                            <td class="text-center"><?php echo $pesanan; ?></td>
                            <td class="text-center"><?php echo $antar_jemput; ?></td>
                            <td class="text-center"><?php echo ($pesanan + $antar_jemput); ?></td>
                            <td class="text-right">Rp <?php echo number_format($pemasukan, 0, ',', '.') . ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td class="text-center">TOTAL</td>
                        <td class="text-center"><?php echo $total_pesanan; ?></td>
                        <td class="text-center"><?php echo $total_antar_jemput; ?></td>
                        <td class="text-center"><?php echo ($total_pesanan + $total_antar_jemput); ?></td>
                        <td class="text-right">Rp <?php echo number_format($total_pemasukan, 0, ',', '.') . ''; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #42c3cf; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Cetak / Simpan PDF
                </button>
            </div>
        </body>
        </html>
        <script>
            // Auto print when page loads
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        </script>
        <?php
        $html = ob_get_clean();
        
        // Output HTML for PDF
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit();
    }
}

// AJAX handler untuk mendapatkan detail pesanan per tanggal
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_orders') {
    $tanggal = $_GET['tanggal'];
    $jenis = $_GET['jenis'] ?? 'semua';
    
    $orders = [];
    
    if ($jenis === 'pesanan' || $jenis === 'semua') {
        // Ambil data pesanan laundry
        $query = "SELECT 
            p.id,
            p.tracking_code,
            p.harga as total_harga,
            p.status,
            p.status_pembayaran,
            p.waktu,
            pl.nama as nama_pelanggan,
            pl.no_hp,
            GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
            'pesanan' as jenis_transaksi
        FROM pesanan p 
        JOIN pelanggan pl ON p.id_pelanggan = pl.id 
        JOIN paket pk ON p.id_paket = pk.id 
        WHERE DATE(p.waktu) = ? AND p.status_pembayaran = 'sudah_dibayar'
        AND p.deleted_at IS NULL
        GROUP BY p.id
        ORDER BY p.waktu DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    if ($jenis === 'antar_jemput' || $jenis === 'semua') {
        // Ambil data antar jemput
        $query = "SELECT 
            aj.id,
            CONCAT('AJ-', LPAD(aj.id, 6, '0')) as tracking_code,
            COALESCE(aj.harga, 5000) as total_harga,
            aj.status,
            'sudah_dibayar' as status_pembayaran,
            COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)) as waktu,
            COALESCE(pl.nama, aj.nama_pelanggan, 'Pelanggan Tidak Dikenal') as nama_pelanggan,
            COALESCE(pl.no_hp, '-') as no_hp,
            CONCAT('Layanan ', UPPER(aj.layanan)) as paket_list,
            'antar_jemput' as jenis_transaksi
        FROM antar_jemput aj 
        LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id
        WHERE DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = ? 
        AND aj.status = 'selesai'
        AND aj.deleted_at IS NULL
        ORDER BY COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)) DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    // Sort by time
    usort($orders, function($a, $b) {
        return strtotime($b['waktu']) - strtotime($a['waktu']);
    });
    
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit();
}

// AJAX handler untuk mendapatkan detail pesanan per bulan
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_monthly_orders') {
    $bulan = $_GET['bulan'];
    $jenis = $_GET['jenis'] ?? 'semua';
    
    $orders = [];
    
    if ($jenis === 'pesanan' || $jenis === 'semua') {
        // Ambil data pesanan laundry
        $query = "SELECT 
            p.id,
            p.tracking_code,
            p.harga as total_harga,
            p.status,
            p.status_pembayaran,
            p.waktu,
            pl.nama as nama_pelanggan,
            pl.no_hp,
            GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
            'pesanan' as jenis_transaksi
        FROM pesanan p 
        JOIN pelanggan pl ON p.id_pelanggan = pl.id 
        JOIN paket pk ON p.id_paket = pk.id 
        WHERE DATE_FORMAT(p.waktu, '%Y-%m') = ? AND p.status_pembayaran = 'sudah_dibayar'
        AND p.deleted_at IS NULL
        GROUP BY p.id
        ORDER BY p.waktu DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $bulan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    if ($jenis === 'antar_jemput' || $jenis === 'semua') {
        // Ambil data antar jemput
        $query = "SELECT 
            aj.id,
            CONCAT('AJ-', LPAD(aj.id, 6, '0')) as tracking_code,
            COALESCE(aj.harga, 5000) as total_harga,
            aj.status,
            'sudah_dibayar' as status_pembayaran,
            COALESCE(aj.waktu_antar, aj.waktu_jemput) as waktu,
            COALESCE(pl.nama, aj.nama_pelanggan, 'Pelanggan Tidak Dikenal') as nama_pelanggan,
            COALESCE(pl.no_hp, '-') as no_hp,
            CONCAT('Layanan ', UPPER(aj.layanan)) as paket_list,
            'antar_jemput' as jenis_transaksi
        FROM antar_jemput aj 
        LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id
        WHERE DATE_FORMAT(COALESCE(aj.waktu_antar, aj.waktu_jemput), '%Y-%m') = ? 
        AND aj.status = 'selesai'
        -- PERBAIKAN: Mengubah kondisi deleted_at untuk menangani NULL dengan benar
        AND aj.deleted_at IS NULL
        ORDER BY COALESCE(aj.waktu_antar, aj.waktu_jemput) DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $bulan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    // Sort by time
    usort($orders, function($a, $b) {
        return strtotime($b['waktu']) - strtotime($a['waktu']);
    });
    
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit();
}

// AJAX handler untuk data chart
if (isset($_GET['ajax']) && $_GET['ajax'] === 'chart_data') {
    $laporan_data = getLaporanData($conn, $periode, $bulan, $tahun, $jenis_laporan);
    
    $chart_data = [];
    foreach ($laporan_data as $data) {
        if ($periode === 'harian') {
            $chart_data[] = [
                'label' => date('d/m', strtotime($data['tanggal'])),
                'value' => (int)$data['total_harga'],
                'orders' => (int)$data['jumlah_pesanan'],
                'antar_jemput' => (int)$data['jumlah_antar_jemput']
            ];
        } else {
            $chart_data[] = [
                'label' => date('M Y', strtotime($data['bulan'] . '-01')),
                'value' => (int)$data['total_harga'],
                'orders' => (int)$data['jumlah_pesanan'],
                'antar_jemput' => (int)$data['jumlah_antar_jemput']
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($chart_data);
    exit();
}

$laporan_data = getLaporanData($conn, $periode, $bulan, $tahun, $jenis_laporan);

// Hitung total statistik
$total_pemasukan = array_sum(array_column($laporan_data, 'total_harga'));
$total_pesanan = array_sum(array_column($laporan_data, 'jumlah_pesanan'));
$total_antar_jemput = array_sum(array_column($laporan_data, 'jumlah_antar_jemput'));

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
    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_nama = $bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return "$hari $bulan_nama $tahun";
}

function formatBulanIndonesia($bulan_str) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $parts = explode('-', $bulan_str);
    $tahun = $parts[0];
    $bulan_num = intval($parts[1]);
    
    return $bulan[$bulan_num] . ' ' . $tahun;
}

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function getStatusBadge($status) {
    $status = trim(strtolower($status));
    
    switch ($status) {
        case 'diproses':
            return '<span class="badge bg-info">Diproses</span>';
        case 'selesai':
            return '<span class="badge bg-success">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge bg-danger">Dibatalkan</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function getJenisLaporanText($jenis) {
    switch ($jenis) {
        case 'pesanan':
            return 'Pesanan Laundry Saja';
        case 'antar_jemput':
            return 'Layanan Antar Jemput Saja';
        case 'semua':
        default:
            return 'Semua Transaksi (Pesanan + Antar Jemput)';
    }
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));

// Tentukan periode text
if ($periode === 'harian') {
    $periode_text = "Harian - " . formatBulanIndonesia($bulan);
} else {
    $periode_text = "Bulanan - Tahun " . $tahun;
}

$jenis_text = getJenisLaporanText($jenis_laporan);
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
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
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
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .jenis-subtitle {
            font-size: 1rem;
            color: #28a745;
            margin-bottom: 2rem;
            font-weight: 500;
            background: rgba(40, 167, 69, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
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
        .stat-card.purple::before { background: #6f42c1; }

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
        .stat-icon.purple { background: linear-gradient(135deg, #6f42c1, #5a32a3); }

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

        .periode-buttons, .jenis-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .periode-btn, .jenis-btn {
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

        .periode-btn.active, .jenis-btn.active {
            background: #42c3cf;
            color: white;
            border-color: #42c3cf;
            box-shadow: 0 2px 10px rgba(66, 195, 207, 0.3);
        }

        .periode-btn:hover:not(.active), .jenis-btn:hover:not(.active) {
            background: rgba(66, 195, 207, 0.1);
            color: #42c3cf;
            border-color: #42c3cf;
        }

        .jenis-btn.pesanan.active {
            background: #28a745;
            border-color: #28a745;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
        }

        .jenis-btn.antar-jemput.active {
            background: #17a2b8;
            border-color: #17a2b8;
            box-shadow: 0 2px 10px rgba(23, 162, 184, 0.3);
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .export-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn.excel {
            background: #28a745;
            color: white;
        }

        .export-btn.excel:hover {
            background: #218838;
            color: white;
        }

        .export-btn.pdf {
            background: #dc3545;
            color: white;
        }

        .export-btn.pdf:hover {
            background: #c82333;
            color: white;
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

        .chart-wrapper {
            position: relative;
            height: 400px;
            margin-bottom: 1rem;
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

        /* Date Cards */
        .date-card {
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 80px !important;
            display: flex;
            flex-direction: column;
        }

        .date-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .date-card.has-data {
            border-left: 3px solid rgb(50, 176, 204);
        }

        .date-card.no-data {
            border-left: 3px solid #dee2e6;
            opacity: 0.7;
        }

        .date-card-header {
            padding: 1rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            flex-shrink: 0;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .date-text {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            line-height: 1.2;
            margin: 0;
        }

        .date-count {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.1rem;
            line-height: 1.2;
            margin-bottom: 0;
        }

        .date-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 1rem;
            margin: 0;
        }

        .chevron-icon {
            color: #6c757d;
            transition: transform 0.3s ease;
            font-size: 0.9rem;
        }

        .chevron-icon.rotated {
            transform: rotate(180deg);
        }

        .orders-dropdown {
            background: #f8f9fa;
            padding: 0;
            border-top: 1px solid #dee2e6;
            display: none;
            position: relative;
            z-index: 10;
            margin-top: 0;
        }

        .orders-dropdown.show {
            display: block;
        }

        .order-item {
            background: white;
            margin: 0.5rem;
            border-radius: 6px;
            padding: 0.75rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 2px solid #42c3cf;
        }

        .order-item.antar-jemput {
            border-left-color: #17a2b8;
        }

        .order-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }

        .tracking-code {
            font-weight: 600;
            color: #42c3cf;
            background: rgba(66, 195, 207, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }

        .tracking-code.antar-jemput {
            color: #17a2b8;
            background: rgba(23, 162, 184, 0.1);
        }

        .order-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 0.9rem;
        }

        .order-details {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .jenis-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .jenis-badge.pesanan {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .jenis-badge.antar-jemput {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        .loading {
            text-align: center;
            padding: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .no-data {
            padding: 1rem 1rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 0.8rem;
            color:rgb(125, 125, 125);
        }

        /* Mobile Cards */
        .mobile-cards {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .stats-dashboard {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.4rem;
            }

            .filter-container,
            .table-container,
            .chart-container {
                padding: 1.25rem;
                margin-bottom: 1.5rem;
            }

            .desktop-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .periode-buttons, .jenis-buttons {
                justify-content: center;
                margin-bottom: 1rem;
            }

            .periode-btn, .jenis-btn {
                flex: 1;
                min-width: 100px;
                text-align: center;
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }

            .export-buttons {
                flex-direction: column;
            }

            .export-btn {
                justify-content: center;
            }

            .chart-wrapper {
                height: 300px;
            }

            .date-card {
                height: 70px !important;
            }
            
            .date-card-header {
                padding: 0.6rem 0.8rem;
            }
            
            .date-text {
                font-size: 0.9rem;
            }
            
            .date-amount {
                font-size: 0.95rem;
            }

            .table-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }

            .order-item {
                margin: 0.4rem;
                padding: 0.6rem;
            }

            .tracking-code {
                font-size: 0.75rem;
            }

            .order-amount {
                font-size: 0.85rem;
            }

            .order-details {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .periode-buttons, .jenis-buttons {
                flex-direction: column;
                gap: 0.3rem;
            }

            .date-card {
                height: 65px !important;
            }
            
            .date-card-header {
                padding: 0.5rem 0.7rem;
            }
            
            .date-text {
                font-size: 0.85rem;
            }
            
            .date-count {
                font-size: 0.75rem;
            }
            
            .date-amount {
                font-size: 0.9rem;
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
            <div class="jenis-subtitle">
                <i class="fas fa-filter me-2"></i><?php echo $jenis_text; ?>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-dashboard">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($total_pemasukan); ?></div>
                    <div class="stat-label">Total Pemasukan</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_pesanan; ?></div>
                    <div class="stat-label">Pesanan Laundry</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_antar_jemput; ?></div>
                    <div class="stat-label">Antar Jemput</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon purple">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-number"><?php echo ($total_pesanan + $total_antar_jemput); ?></div>
                    <div class="stat-label">Total Transaksi</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-number"><?php echo ($total_pesanan + $total_antar_jemput) > 0 ? formatRupiah($total_pemasukan / ($total_pesanan + $total_antar_jemput)) : 'Rp 0'; ?></div>
                    <div class="stat-label">Rata-rata</div>
                </div>
            </div>

            <!-- Filter Container -->
            <div class="filter-container">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filter Laporan
                </div>
                
                <!-- Jenis Laporan Buttons -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jenis Laporan:</label>
                    <div class="jenis-buttons">
                        <a href="?periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=semua" 
                           class="jenis-btn <?php echo $jenis_laporan === 'semua' ? 'active' : ''; ?>">
                            <i class="fas fa-list me-1"></i>Semua Transaksi
                        </a>
                        <a href="?periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=pesanan" 
                           class="jenis-btn pesanan <?php echo $jenis_laporan === 'pesanan' ? 'active' : ''; ?>">
                            <i class="fas fa-receipt me-1"></i>Pesanan Laundry
                        </a>
                        <a href="?periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=antar_jemput" 
                           class="jenis-btn antar-jemput <?php echo $jenis_laporan === 'antar_jemput' ? 'active' : ''; ?>">
                            <i class="fas fa-truck me-1"></i>Antar Jemput
                        </a>
                    </div>
                </div>
                
                <!-- Periode Buttons -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Periode Laporan:</label>
                    <div class="periode-buttons">
                        <a href="?periode=harian&bulan=<?php echo $bulan; ?>&jenis=<?php echo $jenis_laporan; ?>" class="periode-btn <?php echo $periode === 'harian' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day me-1"></i>Harian
                        </a>
                        <a href="?periode=bulanan&tahun=<?php echo $tahun; ?>&jenis=<?php echo $jenis_laporan; ?>" class="periode-btn <?php echo $periode === 'bulanan' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt me-1"></i>Bulanan
                        </a>
                    </div>
                </div>
                
                <form method="GET" action="">
                    <input type="hidden" name="periode" value="<?php echo $periode; ?>">
                    <input type="hidden" name="jenis" value="<?php echo $jenis_laporan; ?>">
                    
                    <div class="row">
                        <?php if ($periode === 'harian'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pilih Bulan</label>
                                <input type="month" class="form-control" name="bulan" value="<?php echo $bulan; ?>" max="<?php echo date('Y-m'); ?>" onchange="this.form.submit()">
                            </div>
                        <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pilih Tahun</label>
                                <select class="form-select" name="tahun" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $tahun == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="?export=excel&periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=<?php echo $jenis_laporan; ?>" class="export-btn excel" target="_blank">
                        <i class="fas fa-file-excel"></i>Export Excel
                    </a>
                    <a href="?export=pdf&periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=<?php echo $jenis_laporan; ?>" class="export-btn pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i>Export PDF
                    </a>
                </div>
            </div>

            <!-- Chart Container -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i>Grafik Pemasukan
                </div>
                <div class="chart-wrapper">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-title">
                    <span><i class="fas fa-list"></i><?php echo $periode === 'harian' ? 'Laporan Harian' : 'Laporan Bulanan'; ?></span>
                    <div class="text-muted">
                        Total: <?php echo count($laporan_data); ?> <?php echo $periode === 'harian' ? 'hari' : 'bulan'; ?>
                    </div>
                </div>

                <?php if (empty($laporan_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-chart-line"></i>
                        <h5>Tidak Ada Data</h5>
                        <p>Belum ada data transaksi untuk periode yang dipilih.</p>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="desktop-table">
                        <?php foreach ($laporan_data as $data): ?>
                            <div class="date-card <?php echo $data['total_harga'] > 0 ? 'has-data' : 'no-data'; ?>" 
                                 onclick="toggleOrders(this, '<?php echo $periode === 'harian' ? $data['tanggal'] : $data['bulan']; ?>')">
                                <div class="date-card-header">
                                    <div class="date-info">
                                        <div class="date-text">
                                            <?php 
                                            if ($periode === 'harian') {
                                                echo formatTanggalSingkat($data['tanggal']);
                                            } else {
                                                echo formatBulanIndonesia($data['bulan']);
                                            }
                                            ?>
                                        </div>
                                        <div class="date-count">
                                            <?php 
                                            $total_transaksi = $data['jumlah_pesanan'] + $data['jumlah_antar_jemput'];
                                            echo $total_transaksi . ' transaksi';
                                            if ($jenis_laporan === 'semua' && $total_transaksi > 0) {
                                                echo ' (' . $data['jumlah_pesanan'] . ' pesanan, ' . $data['jumlah_antar_jemput'] . ' antar jemput)';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="date-amount"><?php echo formatRupiah($data['total_harga']); ?></div>
                                        <i class="fas fa-chevron-down chevron-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="orders-dropdown" id="orders-<?php echo $periode === 'harian' ? $data['tanggal'] : $data['bulan']; ?>">
                                <div class="loading">
                                    <i class="fas fa-spinner fa-spin"></i> Memuat data...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-cards">
                        <?php foreach ($laporan_data as $data): ?>
                            <div class="date-card <?php echo $data['total_harga'] > 0 ? 'has-data' : 'no-data'; ?>" 
                                 onclick="toggleOrders(this, '<?php echo $periode === 'harian' ? $data['tanggal'] : $data['bulan']; ?>')">
                                <div class="date-card-header">
                                    <div class="date-info">
                                        <div class="date-text">
                                            <?php 
                                            if ($periode === 'harian') {
                                                echo formatTanggalSingkat($data['tanggal']);
                                            } else {
                                                echo formatBulanIndonesia($data['bulan']);
                                            }
                                            ?>
                                        </div>
                                        <div class="date-count">
                                            <?php 
                                            $total_transaksi = $data['jumlah_pesanan'] + $data['jumlah_antar_jemput'];
                                            echo $total_transaksi . ' transaksi';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="date-amount"><?php echo formatRupiah($data['total_harga']); ?></div>
                                        <i class="fas fa-chevron-down chevron-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="orders-dropdown" id="mobile-orders-<?php echo $periode === 'harian' ? $data['tanggal'] : $data['bulan']; ?>">
                                <div class="loading">
                                    <i class="fas fa-spinner fa-spin"></i> Memuat data...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let incomeChart;

        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadChart();
        });

        function loadChart() {
            fetch(`?ajax=chart_data&periode=<?php echo $periode; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&jenis=<?php echo $jenis_laporan; ?>`)
                .then(response => response.json())
                .then(data => {
                    createChart(data);
                })
                .catch(error => {
                    console.error('Error loading chart data:', error);
                });
        }

        function createChart(data) {
            const ctx = document.getElementById('incomeChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (incomeChart) {
                incomeChart.destroy();
            }

            const labels = data.map(item => item.label);
            const incomeData = data.map(item => item.value);
            const orderData = data.map(item => item.orders);
            const antarJemputData = data.map(item => item.antar_jemput);

            const datasets = [{
                label: 'Pemasukan (Rp)',
                data: incomeData,
                backgroundColor: 'rgba(66, 195, 207, 0.8)',
                borderColor: 'rgba(66, 195, 207, 1)',
                borderWidth: 2,
                yAxisID: 'y'
            }];

            // Add datasets based on jenis laporan
            if ('<?php echo $jenis_laporan; ?>' === 'semua') {
                datasets.push({
                    label: 'Pesanan Laundry',
                    data: orderData,
                    type: 'line',
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                });
                datasets.push({
                    label: 'Antar Jemput',
                    data: antarJemputData,
                    type: 'line',
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                });
            } else if ('<?php echo $jenis_laporan; ?>' === 'pesanan') {
                datasets.push({
                    label: 'Jumlah Pesanan',
                    data: orderData,
                    type: 'line',
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                });
            } else if ('<?php echo $jenis_laporan; ?>' === 'antar_jemput') {
                datasets.push({
                    label: 'Jumlah Antar Jemput',
                    data: antarJemputData,
                    type: 'line',
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                });
            }

            incomeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: '<?php echo $periode === "harian" ? "Grafik Pemasukan Harian" : "Grafik Pemasukan Bulanan"; ?> - <?php echo $jenis_text; ?>',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            color: '#333'
                        },
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Pemasukan: Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                    } else {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: '<?php echo $periode === "harian" ? "Tanggal" : "Bulan"; ?>',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Pemasukan (Rp)',
                                font: {
                                    weight: 'bold'
                                },
                                color: 'rgba(66, 195, 207, 1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                                },
                                color: 'rgba(66, 195, 207, 1)'
                            },
                            grid: {
                                color: 'rgba(66, 195, 207, 0.1)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Jumlah Transaksi',
                                font: {
                                    weight: 'bold'
                                },
                                color: 'rgba(40, 167, 69, 1)'
                            },
                            ticks: {
                                color: 'rgba(40, 167, 69, 1)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                    },
                }
            });
        }

        function toggleOrders(element, date) {
            const dropdown = element.nextElementSibling;
            const chevron = element.querySelector('.chevron-icon');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                chevron.classList.remove('rotated');
                return;
            }

            // Close all other dropdowns
            document.querySelectorAll('.orders-dropdown').forEach(d => {
                d.classList.remove('show');
            });
            document.querySelectorAll('.chevron-icon').forEach(c => {
                c.classList.remove('rotated');
            });

            dropdown.classList.add('show');
            chevron.classList.add('rotated');

            // Load orders if not already loaded
            if (dropdown.innerHTML.includes('Memuat data...')) {
                loadOrders(date, dropdown);
            }
        }

        function loadOrders(date, container) {
            const periode = '<?php echo $periode; ?>';
            const jenis = '<?php echo $jenis_laporan; ?>';
            let url = `?ajax=${periode === 'harian' ? 'get_orders' : 'get_monthly_orders'}&${periode === 'harian' ? 'tanggal' : 'bulan'}=${date}&jenis=${jenis}`;
            
            fetch(url)
                .then(response => response.json())
                .then(orders => {
                    if (orders.length === 0) {
                        container.innerHTML = '<div class="text-center text-muted py-3">Tidak ada transaksi</div>';
                        return;
                    }

                    let html = '';
                    orders.forEach(order => {
                        const isAntarJemput = order.jenis_transaksi === 'antar_jemput';
                        html += `
                            <div class="order-item ${isAntarJemput ? 'antar-jemput' : ''}" onclick="viewOrderDetail('${order.tracking_code}')">
                                <div class="order-header">
                                    <div>
                                        <span class="tracking-code ${isAntarJemput ? 'antar-jemput' : ''}">${order.tracking_code}</span>
                                        <span class="jenis-badge ${order.jenis_transaksi}">${isAntarJemput ? 'Antar Jemput' : 'Pesanan'}</span>
                                    </div>
                                    <span class="order-amount">${formatRupiah(order.total_harga)}</span>
                                </div>
                                <div class="order-details">
                                    <strong>${order.nama_pelanggan}</strong> • ${order.paket_list}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = '<div class="text-center text-danger py-3">Error memuat data</div>';
                    console.error('Error:', error);
                });
        }

        function viewOrderDetail(trackingCode) {
            if (trackingCode.startsWith('AJ-')) {
                window.location.href = `antar-jemput.php?search=${trackingCode}`;
            } else {
                window.location.href = `detail-laporan.php?tracking=${trackingCode}`;
            }
        }

        function formatRupiah(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }
    </script>
</body>
</html>
