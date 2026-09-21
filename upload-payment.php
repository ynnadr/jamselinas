<?php
session_start();
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT * FROM peserta WHERE token = ?");
$stmt->execute([$token]);
$peserta = $stmt->fetch();

if (!$peserta) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif;">Token tidak valid atau pendaftaran tidak ditemukan.</div>');
}

$errors = [];
$success = false;

if ($peserta['status_pembayaran'] === 'terverifikasi') {
    $dashboardUrl = BASE_URL . '/peserta.php?slug=' . makeSlug($peserta['nama_lengkap']) . '&token=' . $token;
    $alreadyVerified = true;
} else {
    $alreadyVerified = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyVerified) {
    if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Silakan pilih file bukti pembayaran.';
    } else {
        $file = $_FILES['bukti'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Format file harus JPG, PNG, atau PDF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Ukuran file maksimal 5MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $token . '_' . time() . '.' . $ext;
            $dest = UPLOAD_DIR . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $pdo->prepare("UPDATE peserta SET bukti_pembayaran = ?, status_pembayaran = 'menunggu' WHERE token = ?");
                $stmt->execute([$filename, $token]);
                $success = true;
            } else {
                $errors[] = 'Gagal mengupload file. Coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upload Bukti Pembayaran - Jamselinas</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Upload Bukti Bayar</h1>
        <p><?= clean($peserta['nama_lengkap']) ?></p>
    </div>

    <div class="container">
        <?php if ($alreadyVerified): ?>
            <div class="alert alert-success">
                Pembayaran Anda sudah <strong>terverifikasi</strong>!
            </div>
            <div class="card text-center">
                <p class="mb-3">Nomor Peserta: <strong><?= clean($peserta['nomor_peserta']) ?></strong></p>
                <a href="<?= $dashboardUrl ?>" class="btn btn-primary">Buka Dashboard Saya</a>
            </div>

        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <strong>Bukti pembayaran berhasil diupload!</strong><br>
                Tim panitia akan memverifikasi dalam 1x24 jam. Anda akan mendapat notifikasi setelah verifikasi.
            </div>
            <div class="card text-center">
                <p class="text-sm text-muted">Silakan cek WhatsApp / Email Anda secara berkala.</p>
            </div>

        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $e) echo "<div>$e</div>"; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">Detail Pembayaran</div>
                <div class="table-card" style="box-shadow:none; border:1px solid var(--gray-200);">
                    <div class="row">
                        <span class="label">Nama</span>
                        <span><?= clean($peserta['nama_lengkap']) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Paket</span>
                        <span><?= labelPaket($peserta['paket']) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Total Bayar</span>
                        <span class="fw-bold"><?= formatRupiah($peserta['harga']) ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Transfer ke Rekening</div>
                <div class="text-sm">
                    <p><strong>Bank BCA</strong></p>
                    <p>No. Rekening: <strong>1234567890</strong></p>
                    <p>a.n. <strong>Panitia Jamselinas Bandung</strong></p>
                    <p class="mt-2 text-muted">Cantumkan nama Anda di berita transfer.</p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="card">
                    <div class="form-group">
                        <label>Upload Bukti Transfer <span class="required">*</span></label>
                        <input type="file" name="bukti" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                        <p class="text-xs text-muted mt-2">Format: JPG, PNG, atau PDF. Maks 5MB.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">Kirim Bukti Pembayaran</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="footer">Jamselinas XV Bandung 2026</div>
</body>
</html>