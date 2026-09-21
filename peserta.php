<?php
session_start();
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';
$slug = $_GET['slug'] ?? '';

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM peserta WHERE token = ?");
$stmt->execute([$token]);
$peserta = $stmt->fetch();

if (!$peserta || $peserta['status_pembayaran'] !== 'terverifikasi') {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif;">Akses ditolak. Pembayaran belum terverifikasi atau token tidak valid.</div>');
}

// Generate QR data (isi nomor peserta)
$qrData = $peserta['nomor_peserta'];
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - <?= clean($peserta['nama_lengkap']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Dashboard Peserta</h1>
        <p><?= clean($peserta['nomor_peserta']) ?></p>
    </div>

    <div class="container">
        <!-- QR Code -->
        <div class="card">
            <div class="card-title">🎫 QR Code Absensi</div>
            <div class="qr-box">
                <img src="<?= $qrUrl ?>" alt="QR Code" width="220" height="220">
                <p class="fw-bold mt-2"><?= clean($peserta['nomor_peserta']) ?></p>
                <p class="text-xs text-muted">Tunjukkan QR ini saat pengambilan Ride Pack & Official Ride</p>
            </div>
        </div>

        <!-- Status -->
        <div class="card">
            <div class="card-title">📊 Status Anda</div>
            <div class="timeline">
                <div class="timeline-item done">
                    <div class="fw-bold">Pendaftaran</div>
                    <div class="text-xs text-muted">Selesai</div>
                </div>
                <div class="timeline-item done">
                    <div class="fw-bold">Pembayaran</div>
                    <div class="text-xs text-muted">Terverifikasi</div>
                </div>
                <div class="timeline-item <?= $peserta['status_ridepack'] !== 'belum' ? 'done' : 'active' ?>">
                    <div class="fw-bold">Ride Pack</div>
                    <div class="text-xs text-muted">
                        <?php
                        if ($peserta['status_ridepack'] === 'sudah_diambil') echo 'Sudah diambil';
                        elseif ($peserta['status_ridepack'] === 'siap') echo 'Siap diambil';
                        else echo 'Belum tersedia';
                        ?>
                    </div>
                </div>
                <div class="timeline-item <?= $peserta['status_absen'] === 'hadir' ? 'done' : '' ?>">
                    <div class="fw-bold">Official Ride</div>
                    <div class="text-xs text-muted"><?= $peserta['status_absen'] === 'hadir' ? 'Hadir' : 'Belum absen' ?></div>
                </div>
            </div>
        </div>

        <!-- Info Peserta -->
        <div class="card">
            <div class="card-title">👤 Data Peserta</div>
            <div class="table-card" style="box-shadow:none;border:1px solid var(--gray-200);">
                <div class="row"><span class="label">Nama</span><span><?= clean($peserta['nama_lengkap']) ?></span></div>
                <div class="row"><span class="label">Komunitas</span><span><?= clean($peserta['nama_komunitas']) ?></span></div>
                <div class="row"><span class="label">Kota</span><span><?= clean($peserta['kota_asal']) ?></span></div>
                <div class="row"><span class="label">Jersey 1</span><span><?= clean($peserta['ukuran_jersey1']) ?></span></div>
                <div class="row"><span class="label">Jersey 2</span><span><?= clean($peserta['ukuran_jersey2']) ?></span></div>
                <div class="row"><span class="label">Paket</span><span><?= labelPaket($peserta['paket']) ?></span></div>
            </div>
        </div>

        <!-- Jadwal -->
        <div class="card">
            <div class="card-title">📅 Jadwal Acara</div>
            <div class="text-sm">
                <p class="fw-bold mb-1">Jumat, 5 Desember 2026</p>
                <p class="text-muted mb-3">• Registrasi & Pengambilan Ride Pack<br>• Pameran Sepeda Lipat<br>• Talkshow</p>

                <p class="fw-bold mb-1">Sabtu, 6 Desember 2026</p>
                <p class="text-muted mb-3">• City Tour (opsional)<br>• Kontes Sepeda Lipat<br>• Gala Dinner & Doorprize</p>

                <p class="fw-bold mb-1">Minggu, 7 Desember 2026</p>
                <p class="text-muted">• Official Ride (mulai 06.00)<br>• Finish & Penutupan</p>
            </div>
        </div>

        <!-- Rute -->
        <div class="card">
            <div class="card-title">🗺️ Rute Official Ride</div>
            <p class="text-sm text-muted mb-2">Rute akan diumumkan H-7 sebelum acara. Estimasi jarak ±25–35 km mengelilingi Kota Bandung.</p>
            <div class="alert alert-info text-sm">
                Update rute & GPX file akan muncul di sini.
            </div>
        </div>

        <!-- Ride Pack Info -->
        <div class="card">
            <div class="card-title">🎒 Pengambilan Ride Pack</div>
            <?php if ($peserta['status_ridepack'] === 'siap'): ?>
                <div class="alert alert-success text-sm">
                    Ride Pack Anda sudah siap diambil!
                </div>
            <?php elseif ($peserta['status_ridepack'] === 'sudah_diambil'): ?>
                <div class="alert alert-info text-sm">
                    Anda sudah mengambil Ride Pack.
                </div>
            <?php else: ?>
                <p class="text-sm text-muted">Informasi lokasi & jadwal pengambilan akan diupdate di sini H-2 acara.</p>
            <?php endif; ?>
        </div>

        <!-- Dokumentasi -->
        <div class="card">
            <div class="card-title">📸 Dokumentasi</div>
            <p class="text-sm text-muted">Album foto resmi akan tersedia setelah acara berlangsung.</p>
        </div>

        <!-- Kontak -->
        <div class="card">
            <div class="card-title">📞 Kontak Panitia</div>
            <p class="text-sm">WhatsApp: <strong>0812-3456-7890</strong></p>
            <p class="text-sm text-muted mt-1">Jam operasional: 09.00 – 17.00 WIB</p>
        </div>
    </div>

    <div class="footer">
        Simpan link ini untuk akses dashboard Anda<br>
        Jamselinas XV Bandung 2026
    </div>
</body>
</html>