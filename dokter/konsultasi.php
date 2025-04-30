<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data dokter dari sesi
$dokterName = $_SESSION['username'];
$dokterData = $conn->query("SELECT * FROM dokter WHERE username = '$dokterName'")->fetch_assoc();

// Ambil ID dokter dan username dari session
$dokterId = $_SESSION['id'];
$dokterUsername = $_SESSION['username'];

// Handle form submission for replying to or editing consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['reply']) || isset($_POST['edit']))) {
    $konsultasi_id = intval($_POST['konsultasi_id']);
    $jawaban = htmlspecialchars($_POST['jawaban'], ENT_QUOTES, 'UTF-8');
    
    $stmt = $conn->prepare("UPDATE konsultasi SET jawaban = ? WHERE id = ? AND id_dokter = ?");
    $stmt->bind_param("sii", $jawaban, $konsultasi_id, $dokterData['id']);
    
    if ($stmt->execute()) {
        $success_message = isset($_POST['edit']) ? "Jawaban berhasil diubah!" : "Jawaban berhasil dikirim!";
    } else {
        $error_message = isset($_POST['edit']) ? "Gagal mengubah jawaban. Silakan coba lagi." : "Gagal mengirim jawaban. Silakan coba lagi.";
    }
    $stmt->close();
}

// Ambil daftar konsultasi untuk dokter ini
$query_konsultasi = "
    SELECT k.id, k.subject, k.pertanyaan, k.jawaban, k.tgl_konsultasi, p.nama AS nama_pasien, po.nama_poli
    FROM konsultasi k
    JOIN pasien p ON k.id_pasien = p.id
    JOIN dokter d ON k.id_dokter = d.id
    JOIN poli po ON d.id_poli = po.id
    WHERE k.id_dokter = ?
    ORDER BY k.tgl_konsultasi DESC
";
$stmt_konsultasi = $conn->prepare($query_konsultasi);
$stmt_konsultasi->bind_param("i", $dokterData['id']);
$stmt_konsultasi->execute();
$result_konsultasi = $stmt_konsultasi->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi - Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/dokter.png">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .table-responsive {
            margin-top: 20px;
        }
        .btn-detail {
            padding: 2px 5px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>



    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Daftar Konsultasi Pasien</h1>

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

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pasien</th>
                            <th>Subject</th>
                            <th>Tanggal Konsultasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($konsultasi = $result_konsultasi->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($konsultasi['nama_pasien']) ?></td>
                                <td><?= htmlspecialchars($konsultasi['subject']) ?></td>
                                <td><?= date('d-m-Y H:i', strtotime($konsultasi['tgl_konsultasi'])) ?></td>
                                <td><?= $konsultasi['jawaban'] ? 
                                '<span class="badge" style="background-color:rgb(45, 165, 43); color: #fff; border-radius: 20px; padding: 10px;">Terjawab <i class="bi bi-check2-all"></i></span>' : 
                                '<span class="badge" style="background-color:rgb(242, 235, 29); color: rgb(51, 51, 51); border-radius: 20px; padding: 10px;">Belum Dijawab 🕗</span>' ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" style="border-radius: 30px; padding: 7px 20px;" data-bs-toggle="modal" data-bs-target="#konsultasiModal" 
                                            data-id="<?= $konsultasi['id'] ?>"
                                            data-nama="<?= htmlspecialchars($konsultasi['nama_pasien']) ?>"
                                            data-poli="<?= htmlspecialchars($konsultasi['nama_poli']) ?>"
                                            data-subject="<?= htmlspecialchars($konsultasi['subject']) ?>"
                                            data-pertanyaan="<?= htmlspecialchars($konsultasi['pertanyaan']) ?>"
                                            data-jawaban="<?= htmlspecialchars($konsultasi['jawaban']) ?>"
                                            data-tanggal="<?= date('d-m-Y H:i', strtotime($konsultasi['tgl_konsultasi'])) ?>">
                                            <i class="bi bi-box-arrow-up-right"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="konsultasiModal" tabindex="-1" aria-labelledby="konsultasiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="konsultasiModalLabel">Detail Konsultasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Nama Pasien: <span id="modalNamaPasien"></span></h6>
                    <h6>Poli: <span id="modalPoli"></span></h6>
                    <h6>Tanggal Konsultasi: <span id="modalTanggal"></span></h6>
                    <hr>
                    <h6><strong>Subject: </strong></h6>
                    <p id="modalSubject"></p>
                    <h6><strong>Pertanyaan: </strong></h6>
                    <p id="modalPertanyaan"></p>
                    <hr>
                    <h6>Jawaban:</h6>
                    <div id="jawabanContainer">
                        <p id="modalJawaban"></p>
                        <button id="editJawabanBtn" class="btn btn-secondary btn-sm">Edit Jawaban</button>
                    </div>
                    <form id="replyForm" method="post" action="">
                        <input type="hidden" id="modalKonsultasiId" name="konsultasi_id">
                        <div class="mb-3">
                            <label for="jawaban" class="form-label">Jawaban Anda:</label>
                            <textarea class="form-control" id="jawaban" name="jawaban" rows="4" required></textarea>
                        </div>
                        <button type="submit" name="reply" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-send-fill" viewBox="0 0 16 16">
                            <path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855H.766l-.452.18a.5.5 0 0 0-.082.887l.41.26.001.002 4.995 3.178 3.178 4.995.002.002.26.41a.5.5 0 0 0 .886-.083zm-1.833 1.89L6.637 10.07l-.215-.338a.5.5 0 0 0-.154-.154l-.338-.215 7.494-7.494 1.178-.471z"/>
                            </svg>
                            Kirim Jawaban</button>
                        <button type="submit" name="edit" class="btn btn-secondary" style="display: none;">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            var konsultasiModal = document.getElementById('konsultasiModal');
            konsultasiModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var nama = button.getAttribute('data-nama');
                var poli = button.getAttribute('data-poli');
                var subject = button.getAttribute('data-subject');
                var pertanyaan = button.getAttribute('data-pertanyaan');
                var jawaban = button.getAttribute('data-jawaban');
                var tanggal = button.getAttribute('data-tanggal');

                var modalNamaPasien = konsultasiModal.querySelector('#modalNamaPasien');
                var modalPoli = konsultasiModal.querySelector('#modalPoli');
                var modalSubject = konsultasiModal.querySelector('#modalSubject');
                var modalPertanyaan = konsultasiModal.querySelector('#modalPertanyaan');
                var modalJawaban = konsultasiModal.querySelector('#modalJawaban');
                var modalTanggal = konsultasiModal.querySelector('#modalTanggal');
                var modalKonsultasiId = konsultasiModal.querySelector('#modalKonsultasiId');
                var jawabanTextarea = konsultasiModal.querySelector('#jawaban');
                var jawabanContainer = konsultasiModal.querySelector('#jawabanContainer');
                var replyForm = konsultasiModal.querySelector('#replyForm');
                var editJawabanBtn = konsultasiModal.querySelector('#editJawabanBtn');
                var kirimJawabanBtn = replyForm.querySelector('button[name="reply"]');
                var simpanPerubahanBtn = replyForm.querySelector('button[name="edit"]');

                modalNamaPasien.textContent = nama;
                modalPoli.textContent = poli;
                modalSubject.textContent = subject;
                modalPertanyaan.textContent = pertanyaan;
                modalTanggal.textContent = tanggal;
                modalKonsultasiId.value = id;

                if (jawaban) {
                    modalJawaban.textContent = jawaban;
                    jawabanContainer.style.display = 'block';
                    replyForm.style.display = 'none';
                    editJawabanBtn.style.display = 'inline-block';
                } else {
                    jawabanContainer.style.display = 'none';
                    replyForm.style.display = 'block';
                    editJawabanBtn.style.display = 'none';
                    jawabanTextarea.value = '';
                }

                editJawabanBtn.addEventListener('click', function() {
                    jawabanContainer.style.display = 'none';
                    replyForm.style.display = 'block';
                    jawabanTextarea.value = jawaban;
                    kirimJawabanBtn.style.display = 'none';
                    simpanPerubahanBtn.style.display = 'inline-block';
                });
            });
        });
    </script>
</body>
</html>



