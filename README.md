# Jamselinas XV Bandung 2026 - MVP Registration System

Sistem pendaftaran online sederhana untuk Jambore Sepeda Lipat Nasional (Jamselinas).

## Tech Stack
- PHP Vanilla
- MySQL (PDO)
- JavaScript (minimal)
- CSS Mobile-First Modern

## Fitur

### Peserta
1. Form pendaftaran lengkap (mobile responsive)
2. Setelah daftar → dapat link unik untuk upload bukti pembayaran
3. Setelah verifikasi admin → dapat nomor peserta + link dashboard pribadi (dengan token)
4. Dashboard pribadi berisi:
   - QR Code absensi
   - Status timeline
   - Jadwal acara
   - Info Ride Pack
   - Data peserta

### Admin
1. Login
2. Dashboard statistik
3. Verifikasi pembayaran (setujui / tolak)
4. Generate nomor peserta otomatis

## Instalasi

1. **Buat database**
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

2. **Sesuaikan konfigurasi**
   Edit `config/database.php`:
   - DB_HOST, DB_NAME, DB_USER, DB_PASS
   - BASE_URL (contoh: `http://localhost/jamselinas`)

3. **Permission folder upload**
   ```bash
   chmod -R 755 uploads/
   ```

4. **Akses**
   - Pendaftaran: `http://localhost/jamselinas/`
   - Admin: `http://localhost/jamselinas/admin/`
     - Username: `admin`
     - Password: `admin123`

## Struktur Folder

```
jamselinas/
├── admin/
│   ├── index.php          # Dashboard admin
│   ├── login.php
│   ├── logout.php
│   └── verifikasi.php     # Verifikasi pembayaran
├── assets/
│   ├── css/style.css
│   └── js/
├── config/
│   └── database.php
├── includes/
│   └── functions.php
├── sql/
│   └── schema.sql
├── uploads/
│   └── bukti/             # Bukti transfer
├── index.php              # Form pendaftaran
├── upload-payment.php     # Upload bukti (token)
├── peserta.php            # Dashboard peserta (token)
└── README.md
```

## Alur Sistem

1. Peserta isi form → dapat token
2. Sistem tampilkan / kirim link: `upload-payment.php?token=xxx`
3. Peserta upload bukti
4. Admin verifikasi di panel admin
5. Jika disetujui → generate nomor peserta (BDG-2026-0001)
6. Peserta akses dashboard: `peserta.php?slug=nama-peserta&token=xxx`

## Catatan MVP

- Belum ada pengiriman WhatsApp/Email otomatis (bisa ditambahkan dengan API)
- QR Code menggunakan API publik (qrserver.com)
- Password admin default harus diganti di production
- Tidak ada CSRF protection & rate limiting (tambahkan untuk production)
- Desain fokus mobile (max-width 480px)

## Pengembangan Selanjutnya
- Integrasi WhatsApp Gateway / Email
- Export data peserta (Excel)
- Scan QR Code di admin (absensi)
- Multi admin & role
- Dark mode
```
