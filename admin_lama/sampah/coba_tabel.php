<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
        }
        h2 {
            text-align: center;
        }

        /* Membuat tabel responsive dengan scroll horizontal */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; /* Smooth scrolling di iOS */
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap; /* Hindari teks wrapping */
        }
        th, td {
            border: 1px solid #ddd;
            text-align: center;
            padding: 10px;
        }
        th {
            background-color: #42c3cf;
            color: white;
        }
        td {
            background-color: #fff;
        }

        /* Mengurangi padding dan font di layar kecil */
        @media (max-width: 768px) {
            th, td {
                padding: 5px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <h2>Data Pasien</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>No KTP</th>
                    <th>No HP</th>
                    <th>No RM</th>
                    <th>Username</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>123</td>
                    <td>Budi Santoso</td>
                    <td>Jalan Sehat No. 11</td>
                    <td>123456789012</td>
                    <td>081234567890</td>
                    <td>RM001</td>
                    <td>budi123</td>
                    <td>Edit | Hapus</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>124</td>
                    <td>Siti Aminah</td>
                    <td>Jalan Bahagia No. 12</td>
                    <td>987654321012</td>
                    <td>081987654321</td>
                    <td>RM002</td>
                    <td>siti_aminah</td>
                    <td>Edit | Hapus</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
