<?php
session_start();
require_once 'includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi
    $nama_lengkap = clean($_POST['nama_lengkap'] ?? '');
    $nama_panggilan = clean($_POST['nama_panggilan'] ?? '');
    $whatsapp = clean($_POST['whatsapp'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $kota_asal = clean($_POST['kota_asal'] ?? '');
    $alamat = clean($_POST['alamat'] ?? '');
    $nama_komunitas = clean($_POST['nama_komunitas'] ?? 'Individu');
    $kota_komunitas = clean($_POST['kota_komunitas'] ?? '');
    $ukuran_jersey1 = $_POST['ukuran_jersey1'] ?? '';
    $ukuran_jersey2 = $_POST['ukuran_jersey2'] ?? '';
    $merk_sepeda = clean($_POST['merk_sepeda'] ?? '');
    $kontak_darurat_nama = clean($_POST['kontak_darurat_nama'] ?? '');
    $kontak_darurat_hubungan = clean($_POST['kontak_darurat_hubungan'] ?? '');
    $kontak_darurat_wa = clean($_POST['kontak_darurat_wa'] ?? '');
    $paket = $_POST['paket'] ?? 'regular';

    // Validasi wajib
    if (empty($nama_lengkap)) $errors[] = 'Nama lengkap wajib diisi';
    if (empty($whatsapp)) $errors[] = 'Nomor WhatsApp wajib diisi';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid';
    if (empty($kota_asal)) $errors[] = 'Kota asal wajib diisi';
    if (empty($ukuran_jersey1) || empty($ukuran_jersey2)) $errors[] = 'Ukuran jersey wajib dipilih';
    if (empty($kontak_darurat_nama) || empty($kontak_darurat_wa)) $errors[] = 'Kontak darurat wajib diisi';
    if (!in_array($paket, ['early_bird', 'regular', 'late'])) $errors[] = 'Paket tidak valid';

    // Normalisasi nomor WA (hapus spasi, strip, dll)
    $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);

    // Cek apakah nomor WA sudah terdaftar
    if (empty($errors) && !empty($whatsapp)) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, nama_lengkap FROM peserta WHERE whatsapp = ?");
        $stmt->execute([$whatsapp]);
        $existing = $stmt->fetch();
        if ($existing) {
            $errors[] = 'Nomor WhatsApp ini sudah terdaftar atas nama <strong>' . clean($existing['nama_lengkap']) . '</strong>. Silakan gunakan nomor lain.';
        }
    }

    // Harga
    $hargaMap = [
        'early_bird' => 300000,
        'regular' => 350000,
        'late' => 400000,
    ];
    $harga = $hargaMap[$paket];

    if (empty($errors)) {
        $pdo = getPDO();
        $token = generateToken(16);

        try {
            $stmt = $pdo->prepare("INSERT INTO peserta (
                token, nama_lengkap, nama_panggilan, whatsapp, email, tanggal_lahir, jenis_kelamin,
                kota_asal, alamat, nama_komunitas, kota_komunitas, ukuran_jersey1, ukuran_jersey2,
                merk_sepeda, kontak_darurat_nama, kontak_darurat_hubungan, kontak_darurat_wa,
                paket, harga
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?
            )");

            $stmt->execute([
                $token, $nama_lengkap, $nama_panggilan, $whatsapp, $email, $tanggal_lahir ?: null, $jenis_kelamin ?: null,
                $kota_asal, $alamat, $nama_komunitas, $kota_komunitas, $ukuran_jersey1, $ukuran_jersey2,
                $merk_sepeda, $kontak_darurat_nama, $kontak_darurat_hubungan, $kontak_darurat_wa,
                $paket, $harga
            ]);

            $uploadLink = BASE_URL . '/upload-payment.php?token=' . $token;

            // Kirim email konfirmasi + link upload
            $pesertaData = [
                'nama_lengkap' => $nama_lengkap,
                'email' => $email,
                'harga' => $harga,
            ];
            emailSetelahDaftar($pesertaData, $uploadLink);

            // Simpan untuk ditampilkan
            $_SESSION['reg_success'] = [
                'nama' => $nama_lengkap,
                'token' => $token,
                'upload_link' => $uploadLink,
                'harga' => $harga,
                'whatsapp' => $whatsapp,
                'email' => $email
            ];
            redirect('index.php?success=1');
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

$flash = getFlash();
$regSuccess = isset($_GET['success']) && isset($_SESSION['reg_success']) ? $_SESSION['reg_success'] : null;
if ($regSuccess) {
    // Jangan hapus dulu agar bisa di-refresh
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pendaftaran Jamselinas XV Bandung 2026</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>🚴 Jamselinas XV</h1>
        <p>Bandung 2026 • 5–7 Desember</p>
    </div>

    <div class="container">
        <?php if ($regSuccess): ?>
            <div class="alert alert-success">
                <strong>Pendaftaran Berhasil!</strong><br>
                Halo <strong><?= clean($regSuccess['nama']) ?></strong>, data Anda sudah tersimpan.
            </div>

            <div class="card">
                <div class="card-title">📋 Langkah Selanjutnya</div>
                <p class="text-sm mb-3">Silakan lakukan pembayaran sebesar <strong><?= formatRupiah($regSuccess['harga']) ?></strong>, lalu unggah bukti pembayaran melalui link di bawah ini:</p>
                
                <div class="alert alert-info text-sm">
                    <strong>Link Upload Bukti Pembayaran (khusus Anda):</strong><br>
                    <a href="<?= $regSuccess['upload_link'] ?>" style="word-break: break-all; font-weight: 600;">
                        <?= $regSuccess['upload_link'] ?>
                    </a>
                </div>

                <p class="text-sm text-muted mt-3">
                    Email konfirmasi beserta link ini telah dikirim ke <strong><?= clean($regSuccess['email'] ?? '') ?></strong>.<br>
                    Batas waktu upload: <strong>2×24 jam</strong>.
                </p>

                <a href="<?= $regSuccess['upload_link'] ?>" class="btn btn-primary mt-3">
                    Upload Bukti Pembayaran Sekarang
                </a>
            </div>

            <?php unset($_SESSION['reg_success']); ?>

        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin-left: 18px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="regForm">
                <!-- Data Pribadi -->
                <div class="section-title">Data Pribadi</div>
                <div class="card">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" required value="<?= clean($_POST['nama_lengkap'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nama Panggilan</label>
                        <input type="text" name="nama_panggilan" class="form-control" value="<?= clean($_POST['nama_panggilan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nomor WhatsApp <span class="required">*</span></label>
                        <input type="tel" name="whatsapp" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= clean($_POST['whatsapp'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= clean($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= $_POST['tanggal_lahir'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="jenis_kelamin" value="L" <?= ($_POST['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' ?>> Laki-laki
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="jenis_kelamin" value="P" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' ?>> Perempuan
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Kota Asal <span class="required">*</span></label>
                        <input type="text" name="kota_asal" class="form-control" required value="<?= clean($_POST['kota_asal'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control"><?= clean($_POST['alamat'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Komunitas -->
                <div class="section-title">Komunitas / Club</div>
                <div class="card">
                    <div class="form-group">
                        <label>Nama Komunitas</label>
                        <input type="text" name="nama_komunitas" class="form-control" placeholder="Isi 'Individu' jika tidak bergabung" value="<?= clean($_POST['nama_komunitas'] ?? 'Individu') ?>">
                    </div>
                    <div class="form-group">
                        <label>Kota Komunitas</label>
                        <input type="text" name="kota_komunitas" class="form-control" value="<?= clean($_POST['kota_komunitas'] ?? '') ?>">
                    </div>
                </div>

                <!-- Jersey -->
                <div class="section-title">Ukuran Jersey (2 pcs)</div>
                <div class="card">
                    <div class="form-group">
                        <label>Ukuran Jersey Official <span class="required">*</span></label>
                        <select name="ukuran_jersey1" class="form-control" required>
                            <option value="">Pilih ukuran</option>
                            <?php foreach (['S','M','L','XL','XXL','XXXL'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($_POST['ukuran_jersey1'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ukuran Jersey Additional <span class="required">*</span></label>
                        <select name="ukuran_jersey2" class="form-control" required>
                            <option value="">Pilih ukuran</option>
                            <?php foreach (['S','M','L','XL','XXL','XXXL'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($_POST['ukuran_jersey2'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sepeda -->
                <div class="section-title">Data Sepeda (Opsional)</div>
                <div class="card">
                    <div class="form-group">
                        <label>Merk & Tipe Sepeda Lipat</label>
                        <input type="text" name="merk_sepeda" class="form-control" placeholder="Contoh: Brompton M6L" value="<?= clean($_POST['merk_sepeda'] ?? '') ?>">
                    </div>
                </div>

                <!-- Kontak Darurat -->
                <div class="section-title">Kontak Darurat</div>
                <div class="card">
                    <div class="form-group">
                        <label>Nama Kontak Darurat <span class="required">*</span></label>
                        <input type="text" name="kontak_darurat_nama" class="form-control" required value="<?= clean($_POST['kontak_darurat_nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Hubungan</label>
                        <input type="text" name="kontak_darurat_hubungan" class="form-control" placeholder="Istri / Saudara / Teman" value="<?= clean($_POST['kontak_darurat_hubungan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nomor WhatsApp Kontak Darurat <span class="required">*</span></label>
                        <input type="tel" name="kontak_darurat_wa" class="form-control" required value="<?= clean($_POST['kontak_darurat_wa'] ?? '') ?>">
                    </div>
                </div>

                <!-- Paket -->
                <div class="section-title">Pilih Paket</div>
                <div class="card">
                    <div class="paket-grid">
                        <label class="paket-card <?= ($_POST['paket'] ?? 'regular') === 'early_bird' ? 'active' : '' ?>">
                            <input type="radio" name="paket" value="early_bird" <?= ($_POST['paket'] ?? '') === 'early_bird' ? 'checked' : '' ?>>
                            <div class="label">Early Bird</div>
                            <div class="harga">Rp 300.000</div>
                            <div class="text-xs text-muted">s/d 30 September 2026</div>
                        </label>
                        <label class="paket-card <?= ($_POST['paket'] ?? 'regular') === 'regular' ? 'active' : '' ?>">
                            <input type="radio" name="paket" value="regular" <?= ($_POST['paket'] ?? 'regular') === 'regular' ? 'checked' : '' ?>>
                            <div class="label">Regular</div>
                            <div class="harga">Rp 350.000</div>
                            <div class="text-xs text-muted">1 Okt – 15 Nov 2026</div>
                        </label>
                        <label class="paket-card <?= ($_POST['paket'] ?? '') === 'late' ? 'active' : '' ?>">
                            <input type="radio" name="paket" value="late" <?= ($_POST['paket'] ?? '') === 'late' ? 'checked' : '' ?>>
                            <div class="label">Late</div>
                            <div class="harga">Rp 400.000</div>
                            <div class="text-xs text-muted">16 Nov – 30 Nov 2026</div>
                        </label>
                    </div>
                </div>

                <!-- Syarat -->
                <div class="card">
                    <label class="checkbox-item">
                        <input type="checkbox" name="setuju" required>
                        Saya menyetujui Syarat & Ketentuan Jamselinas XV Bandung 2026 dan memahami biaya tidak dapat dikembalikan.
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="btnSubmit">
                    Daftar Sekarang
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="footer">
        &copy; 2026 Indonesia Folding Bike (IDFB)<br>
        Jamselinas XV Bandung
    </div>

    <script>
        // Paket card active state
        document.querySelectorAll('.paket-card input').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.paket-card').forEach(c => c.classList.remove('active'));
                this.closest('.paket-card').classList.add('active');
            });
        });
    </script>
</body>
</html>