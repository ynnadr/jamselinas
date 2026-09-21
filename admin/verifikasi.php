<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    redirect('login.php');
}

$pdo = getPDO();
$message = '';
$error = '';

// Proses verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM peserta WHERE id = ?");
    $stmt->execute([$id]);
    $peserta = $stmt->fetch();

    if ($peserta) {
        if ($aksi === 'setujui') {
            $nomor = generateNomorPeserta($pdo);
            $stmt = $pdo->prepare("UPDATE peserta SET 
                status_pembayaran = 'terverifikasi',
                nomor_peserta = ?,
                verified_at = NOW(),
                verified_by = ?
                WHERE id = ?");
            $stmt->execute([$nomor, $_SESSION['admin_id'], $id]);

            // Siapkan data untuk email
            $peserta['nomor_peserta'] = $nomor;
            $dashboardLink = BASE_URL . '/peserta.php?slug=' . makeSlug($peserta['nama_lengkap']) . '&token=' . $peserta['token'];

            // Kirim email berisi nomor peserta + link dashboard
            emailSetelahVerifikasi($peserta, $dashboardLink);

            $message = "Pembayaran <strong>{$peserta['nama_lengkap']}</strong> berhasil diverifikasi. Nomor peserta: <strong>$nomor</strong>. Email telah dikirim.";
        } elseif ($aksi === 'tolak') {
            $stmt = $pdo->prepare("UPDATE peserta SET status_pembayaran = 'ditolak' WHERE id = ?");
            $stmt->execute([$id]);

            // Kirim email penolakan
            emailPembayaranDitolak($peserta);

            $message = "Pembayaran <strong>{$peserta['nama_lengkap']}</strong> ditolak. Email notifikasi telah dikirim.";
        }
    }
}

// Detail satu peserta
$detail = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM peserta WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $detail = $stmt->fetch();
}

// List semua yang menunggu
$stmt = $pdo->query("SELECT * FROM peserta WHERE status_pembayaran = 'menunggu' AND bukti_pembayaran IS NOT NULL ORDER BY updated_at DESC");
$pendingList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verifikasi Pembayaran - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-header">
        <h1>Verifikasi Pembayaran</h1>
        <div>
            <a href="index.php" style="color:white;margin-right:12px;font-size:0.9rem;">Dashboard</a>
            <a href="logout.php" style="color:#fca5a5;font-size:0.9rem;">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($detail): ?>
            <!-- Detail Peserta -->
            <div class="card">
                <div class="card-title">Detail Peserta</div>
                <div class="table-card" style="box-shadow:none;border:1px solid var(--gray-200);">
                    <div class="row"><span class="label">Nama</span><span class="fw-bold"><?= clean($detail['nama_lengkap']) ?></span></div>
                    <div class="row"><span class="label">WhatsApp</span><span><?= clean($detail['whatsapp']) ?></span></div>
                    <div class="row"><span class="label">Email</span><span><?= clean($detail['email']) ?></span></div>
                    <div class="row"><span class="label">Kota</span><span><?= clean($detail['kota_asal']) ?></span></div>
                    <div class="row"><span class="label">Komunitas</span><span><?= clean($detail['nama_komunitas']) ?></span></div>
                    <div class="row"><span class="label">Paket</span><span><?= labelPaket($detail['paket']) ?></span></div>
                    <div class="row"><span class="label">Total</span><span class="fw-bold"><?= formatRupiah($detail['harga']) ?></span></div>
                    <div class="row"><span class="label">Jersey</span><span><?= $detail['ukuran_jersey1'] ?> & <?= $detail['ukuran_jersey2'] ?></span></div>
                    <div class="row"><span class="label">Status</span>
                        <span>
                            <?php $st = labelStatusBayar($detail['status_pembayaran']); ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e;"><?= $st[0] ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($detail['bukti_pembayaran']): ?>
                <div class="card">
                    <div class="card-title">Bukti Pembayaran</div>
                    <?php
                    $file = $detail['bukti_pembayaran'];
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $url = '../uploads/bukti/' . $file;
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                    ?>
                        <img src="<?= $url ?>" alt="Bukti" style="width:100%;border-radius:10px;border:1px solid var(--gray-200);">
                    <?php else: ?>
                        <a href="<?= $url ?>" target="_blank" class="btn btn-secondary">Lihat File PDF</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($detail['status_pembayaran'] === 'menunggu'): ?>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="id" value="<?= $detail['id'] ?>">
                    <button type="submit" name="aksi" value="setujui" class="btn btn-success mb-2">✅ Setujui & Generate Nomor Peserta</button>
                    <button type="submit" name="aksi" value="tolak" class="btn btn-danger" onclick="return confirm('Yakin tolak pembayaran ini?')">❌ Tolak</button>
                </form>
            <?php endif; ?>

            <a href="verifikasi.php" class="btn btn-secondary">← Kembali ke Daftar</a>

        <?php else: ?>
            <!-- List Pending -->
            <div class="card">
                <div class="card-title">Daftar Menunggu Verifikasi (<?= count($pendingList) ?>)</div>
                <?php if (empty($pendingList)): ?>
                    <p class="text-sm text-muted">Tidak ada data.</p>
                <?php else: ?>
                    <?php foreach ($pendingList as $p): ?>
                        <a href="?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;">
                            <div class="table-card mb-2">
                                <div class="row">
                                    <span class="fw-bold"><?= clean($p['nama_lengkap']) ?></span>
                                    <span class="text-sm"><?= formatRupiah($p['harga']) ?></span>
                                </div>
                                <div class="row">
                                    <span class="label"><?= clean($p['whatsapp']) ?></span>
                                    <span class="text-xs text-muted"><?= date('d M H:i', strtotime($p['updated_at'])) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>