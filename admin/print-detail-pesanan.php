<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Jakarta');
include '../includes/db.php';

if (!isset($_GET['tracking']) || empty($_GET['tracking'])) {
    echo "Tracking code tidak ditemukan.";
    exit();
}
$tracking_code = $_GET['tracking'];

// Query pesanan utama
$query_pesanan_utama = "SELECT p.*, pl.nama as nama_pelanggan, pl.no_hp 
                 FROM pesanan p 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                 WHERE p.tracking_code = ? 
                 LIMIT 1";
$stmt = $conn->prepare($query_pesanan_utama);
$stmt->bind_param("s", $tracking_code);
$stmt->execute();
$result_pesanan_utama = $stmt->get_result();
if ($result_pesanan_utama->num_rows === 0) {
    echo "Pesanan tidak ditemukan.";
    exit();
}
$pesanan_utama = $result_pesanan_utama->fetch_assoc();

// Query semua item pesanan
$query_semua_item = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_paket, pk.icon 
                    FROM pesanan p 
                    JOIN paket pk ON p.id_paket = pk.id 
                    WHERE p.tracking_code = ?
                    ORDER BY p.id ASC";
$stmt_items = $conn->prepare($query_semua_item);
$stmt_items->bind_param("s", $tracking_code);
$stmt_items->execute();
$result_semua_item = $stmt_items->get_result();
$items_pesanan = [];
$total_harga_semua = 0;
while ($item = $result_semua_item->fetch_assoc()) {
    $items_pesanan[] = $item;
    $total_harga_semua += $item['harga'];
}

function formatTanggalIndonesia($tanggal) {
    $hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $bulan = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    $timestamp = strtotime($tanggal);
    $hari_ini = $hari[date('w', $timestamp)];
    $tanggal_ini = date('j', $timestamp);
    $bulan_ini = $bulan[date('n', $timestamp)];
    $tahun_ini = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    return "$hari_ini, $tanggal_ini $bulan_ini $tahun_ini $jam";
}
$tanggal_pesanan = formatTanggalIndonesia($pesanan_utama['waktu']);

function getStatusBadge($status) {
    switch ($status) {
        case 'diproses': return 'Diproses';
        case 'selesai': return 'Selesai';
        case 'dibatalkan': return 'Dibatalkan';
        default: return 'Unknown';
    }
}
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'belum_dibayar': return 'Belum Dibayar';
        case 'sudah_dibayar': return 'Sudah Dibayar';
        default: return 'Unknown';
    }
}
$alamat_laundry = 'RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219';
$wa_laundry = '0895395442010';
$nama_laundry = 'ZEEA LAUNDRY';
$logo = '../assets/images/zeea_laundry.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Struk Pesanan | <?php echo $nama_laundry; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; margin: 0; padding: 0; color: #222; }
        .struk-container { max-width: 700px; margin: 0 auto; background: #fff; padding: 24px 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { text-align: center; margin-bottom: 18px; }
        .header img { width: 60px; margin-bottom: 8px; }
        .header h2 { margin: 0 0 4px 0; font-size: 22px; letter-spacing: 1px; }
        .header .alamat { font-size: 12px; color: #555; margin-bottom: 2px; }
        .header .wa { font-size: 12px; color: #555; }
        .section { margin-bottom: 16px; }
        .section-title { font-weight: bold; font-size: 15px; margin-bottom: 6px; color: #222; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .info-table .label { color: #555; width: 120px; }
        .info-table .value { color: #222; font-weight: 500; }
        .paket-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .paket-table th, .paket-table td { border: 1px solid #ddd; padding: 6px 8px; font-size: 13px; }
        .paket-table th { background: #f2f2f2; font-weight: bold; }
        .paket-table td { text-align: center; }
        .paket-table td.left { text-align: left; }
        .paket-icon { width: 20px; height: 20px; object-fit: contain; vertical-align: middle; }
        .total-row { font-weight: bold; font-size: 15px; background: #e8f4f8; }
        .status-row { font-size: 13px; }
        .footer { text-align: center; margin-top: 18px; font-size: 12px; color: #555; }
        .catatan { margin-top: 10px; font-size: 12px; color: #444; }
        @media print {
            body { background: #fff; }
            .struk-container { box-shadow: none; border-radius: 0; padding: 0 8px; }
            .header img { width: 48px; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="struk-container">
        <div class="header">
            <img src="<?php echo $logo; ?>" alt="Logo Laundry">
            <h2><?php echo $nama_laundry; ?></h2>
            <div class="alamat"><?php echo $alamat_laundry; ?></div>
            <div class="wa">WhatsApp: <?php echo $wa_laundry; ?></div>
        </div>
        <div class="section">
            <table class="info-table">
                <tr><td class="label">Kode Tracking</td><td class="value">: <?php echo $pesanan_utama['tracking_code']; ?></td></tr>
                <tr><td class="label">Nama Pelanggan</td><td class="value">: <?php echo $pesanan_utama['nama_pelanggan']; ?></td></tr>
                <tr><td class="label">No. HP</td><td class="value">: <?php echo $pesanan_utama['no_hp']; ?></td></tr>
                <tr><td class="label">Tanggal Pesanan</td><td class="value">: <?php echo $tanggal_pesanan; ?></td></tr>
            </table>
        </div>
        <div class="section">
            <div class="section-title">Detail Paket</div>
            <table class="paket-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Icon</th>
                        <th class="left">Nama Paket</th>
                        <th>Berat (kg)</th>
                        <th>Harga/Kg</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach ($items_pesanan as $item): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td>
                            <?php if (!empty($item['icon'])): ?>
                                <img src="../assets/uploads/paket_icons/<?php echo htmlspecialchars($item['icon']); ?>" alt="<?php echo htmlspecialchars($item['nama_paket']); ?>" class="paket-icon">
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="left"><?php echo $item['nama_paket']; ?></td>
                        <td><?php echo number_format($item['berat'], 2, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format(($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) ? $item['harga_custom'] : $item['harga_paket'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" style="text-align:right;">Total</td>
                        <td>Rp <?php echo number_format($total_harga_semua, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="section">
            <table class="info-table">
                <tr class="status-row"><td class="label">Status Pesanan</td><td class="value">: <?php echo getStatusBadge($pesanan_utama['status']); ?></td></tr>
                <tr class="status-row"><td class="label">Status Pembayaran</td><td class="value">: <?php echo getPaymentStatusBadge($pesanan_utama['status_pembayaran']); ?></td></tr>
            </table>
        </div>
        <div class="catatan">
            <strong>Catatan:</strong> Simpan struk ini sebagai bukti pengambilan laundry. Jika ada pertanyaan, silakan hubungi WhatsApp di atas.
        </div>
        <div class="footer">
            Terima kasih telah menggunakan layanan <?php echo $nama_laundry; ?>.<br>
            Kepuasan Anda adalah prioritas kami.
        </div>
    </div>
</body>
</html> 