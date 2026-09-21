<?php
/**
 * Email Gateway Jamselinas
 * 
 * Strategi:
 * 1. Selalu simpan email ke antrian (logs/queue/) agar tidak hilang
 * 2. Coba kirim via PHP mail()
 * 3. Jika gagal, email tetap bisa dilihat di logs/ dan bisa di-kirim ulang nanti
 * 
 * Untuk production: ganti fungsi sendEmail() dengan PHPMailer + SMTP
 */

define('EMAIL_QUEUE_DIR', __DIR__ . '/../logs/queue');
define('EMAIL_SENT_DIR', __DIR__ . '/../logs/sent');
define('EMAIL_LOG_FILE', __DIR__ . '/../logs/email.log');

function ensureEmailDirs() {
    foreach ([EMAIL_QUEUE_DIR, EMAIL_SENT_DIR, dirname(EMAIL_LOG_FILE)] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Kirim email
 * Return: true jika berhasil dikirim ATAU berhasil disimpan ke queue
 */
function sendEmail($to, $subject, $bodyHtml, $toName = '') {
    ensureEmailDirs();

    $fromEmail = 'noreply@jamselinas.id';
    $fromName  = 'Jamselinas XV Bandung 2026';
    $id = date('Ymd_His') . '_' . substr(md5($to . $subject . microtime()), 0, 8);

    // 1. Simpan ke queue dulu (selalu berhasil)
    $queueData = [
        'id' => $id,
        'to' => $to,
        'to_name' => $toName,
        'subject' => $subject,
        'body' => $bodyHtml,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending',
    ];
    $queueFile = EMAIL_QUEUE_DIR . '/' . $id . '.json';
    file_put_contents($queueFile, json_encode($queueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Simpan juga versi HTML agar mudah dibuka di browser
    $htmlFile = EMAIL_QUEUE_DIR . '/' . $id . '.html';
    file_put_contents($htmlFile, "<!-- TO: {$to} | SUBJECT: {$subject} -->\n" . $bodyHtml);

    // 2. Coba kirim via mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: panitia@jamselinas.id\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $sent = false;
    try {
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyHtml, $headers);
    } catch (Throwable $e) {
        $sent = false;
    }

    // 3. Update status & log
    $status = $sent ? 'sent' : 'queued';
    $queueData['status'] = $status;
    $queueData['sent_at'] = $sent ? date('Y-m-d H:i:s') : null;
    file_put_contents($queueFile, json_encode($queueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($sent) {
        // Pindahkan ke folder sent
        rename($queueFile, EMAIL_SENT_DIR . '/' . $id . '.json');
        rename($htmlFile, EMAIL_SENT_DIR . '/' . $id . '.html');
    }

    $logLine = date('Y-m-d H:i:s') . " | {$status} | TO: {$to} | SUBJECT: {$subject} | ID: {$id}\n";
    file_put_contents(EMAIL_LOG_FILE, $logLine, FILE_APPEND);

    // Return true selama berhasil disimpan (meski mail() gagal)
    return true;
}

/**
 * Template email dasar (mobile-friendly)
 */
function emailTemplate($title, $content) {
    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
  <div style="max-width:480px;margin:20px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.08);">
    <div style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;padding:24px 20px;text-align:center;">
      <h1 style="margin:0;font-size:20px;">🚴 Jamselinas XV</h1>
      <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Bandung 2026</p>
    </div>
    <div style="padding:24px 20px;color:#111827;font-size:15px;line-height:1.6;">
      ' . $content . '
    </div>
    <div style="padding:16px 20px;background:#f9fafb;text-align:center;font-size:12px;color:#6b7280;">
      &copy; 2026 Indonesia Folding Bike (IDFB)<br>Jamselinas XV Bandung
    </div>
  </div>
</body>
</html>';
}

function emailSetelahDaftar($peserta, $uploadLink) {
    $content = '
      <p>Halo <strong>' . htmlspecialchars($peserta['nama_lengkap']) . '</strong>,</p>
      <p>Terima kasih telah mendaftar <strong>Jamselinas XV Bandung 2026</strong>.</p>
      <p>Status Anda: <strong style="color:#d97706;">Menunggu Pembayaran</strong></p>
      <p>Silakan transfer <strong>' . formatRupiah($peserta['harga']) . '</strong>, lalu unggah bukti melalui tombol di bawah:</p>
      <p style="text-align:center;margin:24px 0;">
        <a href="' . htmlspecialchars($uploadLink) . '" style="display:inline-block;background:#0f766e;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:600;">
          Upload Bukti Pembayaran
        </a>
      </p>
      <p style="font-size:13px;color:#6b7280;">Batas waktu: <strong>2×24 jam</strong>. Link hanya untuk Anda.</p>
      <p style="font-size:12px;word-break:break-all;color:#6b7280;">' . htmlspecialchars($uploadLink) . '</p>
    ';
    return sendEmail(
        $peserta['email'],
        'Pendaftaran Berhasil – Upload Bukti Pembayaran | Jamselinas XV',
        emailTemplate('Pendaftaran Berhasil', $content),
        $peserta['nama_lengkap']
    );
}

function emailSetelahVerifikasi($peserta, $dashboardLink) {
    $content = '
      <p>Halo <strong>' . htmlspecialchars($peserta['nama_lengkap']) . '</strong>,</p>
      <p>Pembayaran Anda <strong style="color:#16a34a;">berhasil diverifikasi</strong>!</p>
      <p style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;text-align:center;margin:20px 0;">
        <span style="font-size:13px;color:#166534;">Nomor Peserta</span><br>
        <strong style="font-size:22px;color:#15803d;">' . htmlspecialchars($peserta['nomor_peserta']) . '</strong>
      </p>
      <p>Akses halaman pribadi Anda (QR Code, jadwal, Ride Pack, dll):</p>
      <p style="text-align:center;margin:24px 0;">
        <a href="' . htmlspecialchars($dashboardLink) . '" style="display:inline-block;background:#0f766e;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:600;">
          Buka Dashboard Saya
        </a>
      </p>
      <p style="font-size:13px;color:#6b7280;">Jangan bagikan link ini ke orang lain.</p>
      <p style="font-size:12px;word-break:break-all;color:#6b7280;">' . htmlspecialchars($dashboardLink) . '</p>
      <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
      <p style="font-size:13px;">
        <strong>Ringkasan:</strong><br>
        Paket: ' . labelPaket($peserta['paket']) . '<br>
        Jersey: ' . htmlspecialchars($peserta['ukuran_jersey1']) . ' &amp; ' . htmlspecialchars($peserta['ukuran_jersey2']) . '<br>
        Komunitas: ' . htmlspecialchars($peserta['nama_komunitas']) . '
      </p>
    ';
    return sendEmail(
        $peserta['email'],
        'Pembayaran Terverifikasi – ' . $peserta['nomor_peserta'] . ' | Jamselinas XV',
        emailTemplate('Pembayaran Terverifikasi', $content),
        $peserta['nama_lengkap']
    );
}

function emailPembayaranDitolak($peserta) {
    $content = '
      <p>Halo <strong>' . htmlspecialchars($peserta['nama_lengkap']) . '</strong>,</p>
      <p>Bukti pembayaran Anda <strong style="color:#dc2626;">belum dapat diverifikasi</strong>.</p>
      <p>Kemungkinan penyebab:</p>
      <ul style="padding-left:18px;color:#4b5563;font-size:14px;">
        <li>Nominal tidak sesuai</li>
        <li>Bukti tidak jelas</li>
        <li>Rekening tujuan salah</li>
      </ul>
      <p>Hubungi panitia di WhatsApp <strong>0812-3456-7890</strong> untuk bantuan.</p>
    ';
    return sendEmail(
        $peserta['email'],
        'Pembayaran Belum Diverifikasi | Jamselinas XV',
        emailTemplate('Pembayaran Ditolak', $content),
        $peserta['nama_lengkap']
    );
}