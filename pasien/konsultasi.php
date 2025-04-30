<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienName = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienName'")->fetch_assoc();

// Pastikan nomor rekam medis tersedia di sesi
$query_no_rm = $conn->prepare("SELECT id, no_rm FROM pasien WHERE username = ?");
$query_no_rm->bind_param("s", $_SESSION['username']);
$query_no_rm->execute();
$result_no_rm = $query_no_rm->get_result();

if ($result_no_rm->num_rows > 0) {
    $row_no_rm = $result_no_rm->fetch_assoc();
    $nomor_rekam_medis = $row_no_rm['no_rm'];
    $id_pasien = $row_no_rm['id'];
} else {
    echo "Data pasien tidak ditemukan. Silakan login kembali.";
    exit();
}

// Ambil data poli dari database
$query_poli = "SELECT id, nama_poli FROM poli";
$result_poli = $conn->query($query_poli);

// Handle AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_dokter':
            $poliId = intval($_GET['poli_id']);
            $query_dokter = "SELECT id, nama FROM dokter WHERE id_poli = ?";
            $stmt = $conn->prepare($query_dokter);
            $stmt->bind_param("i", $poliId);
            $stmt->execute();
            $result = $stmt->get_result();
            $dokter = [];
            while ($row = $result->fetch_assoc()) {
                $dokter[] = $row;
            }
            echo json_encode($dokter);
            exit;

        case 'get_jadwal':
            $dokterId = intval($_GET['dokter_id']);
            $query_jadwal = "SELECT id, hari, jam_mulai, jam_selesai FROM jadwal_periksa WHERE id_dokter = ? AND status = 'Aktif'";
            $stmt = $conn->prepare($query_jadwal);
            $stmt->bind_param("i", $dokterId);
            $stmt->execute();
            $result = $stmt->get_result();
            $jadwal = [];
            while ($row = $result->fetch_assoc()) {
                $jadwal[] = $row;
            }
            echo json_encode($jadwal);
            exit;

        case 'get_konsultasi':
            $id = intval($_GET['id']);
            $query = "SELECT id, subject, pertanyaan FROM konsultasi WHERE id = ? AND id_pasien = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $id_pasien);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $konsultasi = $result->fetch_assoc();
                echo json_encode($konsultasi);
            } else {
                echo json_encode(['error' => 'Konsultasi tidak ditemukan']);
            }
            exit;
        case 'get_detail_konsultasi':
            $id = intval($_GET['id']);
            $query = "
                SELECT k.*, d.nama AS nama_dokter, p.nama_poli
                FROM konsultasi k
                JOIN dokter d ON k.id_dokter = d.id
                JOIN poli p ON d.id_poli = p.id
                WHERE k.id = ? AND k.id_pasien = ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $id_pasien);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $konsultasi = $result->fetch_assoc();
                $konsultasi['tgl_konsultasi'] = date('d-m-Y H:i', strtotime($konsultasi['tgl_konsultasi']));
                echo json_encode($konsultasi);
            } else {
                echo json_encode(['error' => 'Konsultasi tidak ditemukan']);
            }
            exit;

        case 'update_konsultasi':
            $id = intval($_POST['id']);
            $subject = htmlspecialchars($_POST['subject'], ENT_QUOTES, 'UTF-8');
            $pertanyaan = htmlspecialchars($_POST['pertanyaan'], ENT_QUOTES, 'UTF-8');
            $query = "UPDATE konsultasi SET subject = ?, pertanyaan = ? WHERE id = ? AND id_pasien = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $subject, $pertanyaan, $id, $id_pasien);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
            exit;

        case 'delete_konsultasi':
            $id = intval($_POST['id']);
            $query = "DELETE FROM konsultasi WHERE id = ? AND id_pasien = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $id_pasien);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
            exit;
    }
}

// Jika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_dokter = intval($_POST['dokter']);
    $subject = htmlspecialchars($_POST['subject'], ENT_QUOTES, 'UTF-8');
    $pertanyaan = htmlspecialchars($_POST['pertanyaan'], ENT_QUOTES, 'UTF-8');

    // Simpan data ke database
    $stmt = $conn->prepare("INSERT INTO konsultasi (subject, pertanyaan, tgl_konsultasi, id_pasien, id_dokter) VALUES (?, ?, NOW(), ?, ?)");
    $stmt->bind_param("ssii", $subject, $pertanyaan, $id_pasien, $id_dokter);
    
    if ($stmt->execute()) {
        $success_message = "Konsultasi berhasil dikirim!";
    } else {
        $error_message = "Gagal mengirim konsultasi. Silakan coba lagi.";
    }
    $stmt->close();
}

// Ambil riwayat konsultasi
$query_riwayat = "
    SELECT k.id, k.subject, k.pertanyaan, k.jawaban, k.tgl_konsultasi, d.nama AS nama_dokter, p.nama_poli
    FROM konsultasi k
    JOIN dokter d ON k.id_dokter = d.id
    JOIN poli p ON d.id_poli = p.id
    WHERE k.id_pasien = ?
    ORDER BY k.tgl_konsultasi DESC
";
$stmt_riwayat = $conn->prepare($query_riwayat);
$stmt_riwayat->bind_param("i", $id_pasien);
$stmt_riwayat->execute();
$result_riwayat = $stmt_riwayat->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi - Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_pasien.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Konsultasi Dokter</h1>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mb-5">
                <div class="mb-3">
                    <label for="poli" class="form-label">Pilih Poli:</label>
                    <select name="poli" id="poli" class="form-select" required>
                        <option value="">Pilih Poli</option>
                        <?php while ($row = $result_poli->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>"><?php echo $row['nama_poli']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="dokter" class="form-label">Pilih Dokter:</label>
                    <select name="dokter" id="dokter" class="form-select" required>
                        <option value="">Pilih Poli Terlebih Dahulu</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject:</label>
                    <input type="text" name="subject" id="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="pertanyaan" class="form-label">Pertanyaan:</label>
                    <textarea name="pertanyaan" id="pertanyaan" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Kirim Konsultasi</button> 
            </form>

            <h1 class="mb-4">Daftar Riwayat Konsultasi</h1>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Poli</th>
                        <th>Dokter</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    while ($row = $result_riwayat->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo date('d-m-Y H:i', strtotime($row['tgl_konsultasi'])); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_poli']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_dokter']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo $row['jawaban'] ? 
                            '<span class="badge" style="background-color:rgb(45, 165, 43); color: #fff; border-radius: 20px; padding: 10px;">Terjawab <i class="bi bi-check2-all"></i></span>' : 
                            '<span class="badge" style="background-color:rgb(242, 228, 29); color: rgb(51, 51, 51); border-radius: 20px; padding: 10px;">Menunggu 🕗</span>'; ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" onclick="editKonsultasi(<?php echo $row['id']; ?>)">Edit</button>
                                <button class="btn btn-danger btn-sm me-1" onclick="deleteKonsultasi(<?php echo $row['id']; ?>)">Hapus</button>
                                <button class="btn btn-primary btn-sm" onclick="showDetailKonsultasi(<?php echo $row['id']; ?>)">Detail</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Konsultasi Modal -->
    <div class="modal fade" id="editKonsultasiModal" tabindex="-1" aria-labelledby="editKonsultasiModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editKonsultasiModalLabel">Edit Konsultasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editKonsultasiForm">
                        <input type="hidden" id="editKonsultasiId" name="id">
                        <div class="mb-3">
                            <label for="editSubject" class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="editSubject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPertanyaan" class="form-label">Pertanyaan:</label>
                            <textarea class="form-control" id="editPertanyaan" name="pertanyaan" rows="4" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="updateKonsultasi()">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Konsultasi Modal -->
    <div class="modal fade" id="detailKonsultasiModal" tabindex="-1" aria-labelledby="detailKonsultasiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailKonsultasiModalLabel">Detail Konsultasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailKonsultasiContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        $(document).ready(function() {
            $('#poli').change(function() {
                var poliId = $(this).val();
                if (poliId) {
                    $.ajax({
                        url: '?action=get_dokter&poli_id=' + poliId,
                        type: 'get',
                        dataType: 'json',
                        success: function(data) {
                            var html = '<option value="">Pilih Dokter</option>';
                            for (var i = 0; i < data.length; i++) {
                                html += '<option value="' + data[i].id + '">' + data[i].nama + '</option>';
                            }
                            $('#dokter').html(html);
                            $('#jadwal').html('<option value="">Pilih Dokter Terlebih Dahulu</option>');
                        }
                    });
                } else {
                    $('#dokter').html('<option value="">Pilih Poli Terlebih Dahulu</option>');
                    $('#jadwal').html('<option value="">Pilih Dokter Terlebih Dahulu</option>');
                }
            });

            $('#dokter').change(function() {
                var dokterId = $(this).val();
                if (dokterId) {
                    $.ajax({
                        url: '?action=get_jadwal&dokter_id=' + dokterId,
                        type: 'get',
                        dataType: 'json',
                        success: function(data) {
                            var html = '<option value="">Pilih Jadwal</option>';
                            for (var i = 0; i < data.length; i++) {
                                html += '<option value="' + data[i].id + '">' + data[i].hari + ' (' + data[i].jam_mulai + ' - ' + data[i].jam_selesai + ')</option>';
                            }
                            $('#jadwal').html(html);
                        }
                    });
                } else {
                    $('#jadwal').html('<option value="">Pilih Dokter Terlebih Dahulu</option>');
                }
            });
        });

        function editKonsultasi(id) {
            $.ajax({
                url: '?action=get_konsultasi&id=' + id,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#editKonsultasiId').val(data.id);
                    $('#editSubject').val(data.subject);
                    $('#editPertanyaan').val(data.pertanyaan);
                    $('#editKonsultasiModal').modal('show');
                },
                error: function() {
                    alert('Terjadi kesalahan saat mengambil data konsultasi.');
                }
            });
        }

        function updateKonsultasi() {
            $.ajax({
                url: '?action=update_konsultasi',
                type: 'POST',
                data: $('#editKonsultasiForm').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Konsultasi berhasil diperbarui.');
                        $('#editKonsultasiModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Gagal memperbarui konsultasi: ' + response.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat memperbarui konsultasi.');
                }
            });
        }

        function deleteKonsultasi(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda tidak akan dapat mengembalikan ini!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '?action=delete_konsultasi',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    'Terhapus!',
                                    'Konsultasi berhasil dihapus.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Gagal!',
                                    'Gagal menghapus konsultasi: ' + response.message,
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                'Gagal!',
                                'Terjadi kesalahan saat menghapus konsultasi.',
                                'error'
                            );
                        }
                    });
                }
            });
        }

        function showDetailKonsultasi(id) {
            $.ajax({
                url: '?action=get_detail_konsultasi&id=' + id,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var content = `
                        <p><strong>Subject:</strong> ${data.subject}</p>
                        <p><strong>Pertanyaan:</strong> ${data.pertanyaan}</p>
                        <hr>
                        <p><strong>Poli:</strong> ${data.nama_poli}</p>
                        <p><strong>Dokter:</strong> ${data.nama_dokter}</p>
                        <p><strong>Tanggal Konsultasi:</strong> ${data.tgl_konsultasi}</p>
                        <hr>
                        <h5>Jawaban Dokter:</h5>
                        <p>${data.jawaban ? data.jawaban : 'Belum ada jawaban dari dokter.'}</p>
                    `;
                    $('#detailKonsultasiContent').html(content);
                    $('#detailKonsultasiModal').modal('show');
                },
                error: function() {
                    alert('Terjadi kesalahan saat mengambil detail konsultasi.');
                }
            });
        }
    </script>
</body>
</html>