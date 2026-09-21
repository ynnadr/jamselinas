<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    redirect('login.php');
}

$pdo = getPDO();

// Statistik (selalu dihitung)
$stats = [
    'total'         => (int)$pdo->query("SELECT COUNT(*) FROM peserta")->fetchColumn(),
    'menunggu'      => (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE status_pembayaran = 'menunggu' AND bukti_pembayaran IS NOT NULL")->fetchColumn(),
    'terverifikasi' => (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE status_pembayaran = 'terverifikasi'")->fetchColumn(),
    'belum_bayar'   => (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE bukti_pembayaran IS NULL")->fetchColumn(),
];

// Parameter list (hanya aktif saat drilldown)
$filter  = $_GET['filter'] ?? '';
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; // Optimal untuk HP
$offset  = ($page - 1) * $perPage;

$pesertaList = [];
$totalRows   = 0;
$titleList   = '';
$showList    = in_array($filter, ['total', 'menunggu', 'terverifikasi', 'belum_bayar']);

if ($showList) {
    // Build WHERE
    $where  = [];
    $params = [];

    switch ($filter) {
        case 'menunggu':
            $where[] = "status_pembayaran = 'menunggu' AND bukti_pembayaran IS NOT NULL";
            $titleList = 'Menunggu Verifikasi';
            break;
        case 'terverifikasi':
            $where[] = "status_pembayaran = 'terverifikasi'";
            $titleList = 'Terverifikasi';
            break;
        case 'belum_bayar':
            $where[] = "bukti_pembayaran IS NULL";
            $titleList = 'Belum Upload Bukti';
            break;
        default:
            $titleList = 'Semua Peserta';
    }

    if ($q !== '') {
        $where[] = "(nama_lengkap LIKE ? OR whatsapp LIKE ? OR nomor_peserta LIKE ? OR email LIKE ? OR nama_komunitas LIKE ?)";
        $like = "%{$q}%";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM peserta {$whereSql}");
    $stmtCount->execute($params);
    $totalRows = (int)$stmtCount->fetchColumn();

    // Data
    $orderBy = match($filter) {
        'terverifikasi' => 'verified_at DESC',
        'menunggu'      => 'updated_at DESC',
        default         => 'created_at DESC',
    };

    $sql = "SELECT id, nama_lengkap, whatsapp, email, nama_komunitas, paket, harga, 
                   status_pembayaran, nomor_peserta, bukti_pembayaran, token, created_at
            FROM peserta {$whereSql} 
            ORDER BY {$orderBy} 
            LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pesertaList = $stmt->fetchAll();

    $totalPages = max(1, (int)ceil($totalRows / $perPage));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Admin - Jamselinas</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card {
            cursor: pointer;
            transition: transform 0.12s, box-shadow 0.12s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .stat-card:active { transform: scale(0.97); }
        .stat-card.active-filter {
            outline: 2.5px solid var(--primary);
            outline-offset: 2px;
        }
        /* Compact list item untuk 2000 data */
        .list-item {
            background: white;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
        }
        .list-item .name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .list-item .meta {
            font-size: 0.78rem;
            color: var(--gray-500);
            display: flex;
            flex-wrap: wrap;
            gap: 6px 12px;
        }
        .list-item .actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
        }
        .list-item .actions a {
            flex: 1;
            text-align: center;
            padding: 8px;
            font-size: 0.8rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-sm-primary {
            background: var(--primary);
            color: white;
        }
        .btn-sm-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        .search-box {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .search-box input {
            flex: 1;
            padding: 10px 12px;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .search-box button {
            padding: 10px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
        }
        .pagination a {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        .pagination .current {
            background: var(--primary);
            color: white;
        }
        .pagination .disabled {
            color: var(--gray-300);
            border: 1px solid var(--gray-100);
        }
        .result-info {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>Dashboard Admin</h1>
        <div>
            <a href="verifikasi.php" style="color:white;margin-right:12px;font-size:0.9rem;">Verifikasi</a>
            <a href="logout.php" style="color:#fca5a5;font-size:0.9rem;">Logout</a>
        </div>
    </div>

    <div class="container">
        <p class="text-sm text-muted mb-3">Halo, <?= clean($_SESSION['admin_nama']) ?></p>

        <!-- Statistik (klik = drilldown) -->
        <div class="stat-grid">
            <a href="?filter=total" class="stat-card <?= $filter === 'total' ? 'active-filter' : '' ?>">
                <div class="number"><?= number_format($stats['total']) ?></div>
                <div class="label">Total Daftar</div>
            </a>
            <a href="?filter=menunggu" class="stat-card <?= $filter === 'menunggu' ? 'active-filter' : '' ?>">
                <div class="number" style="color:var(--accent)"><?= number_format($stats['menunggu']) ?></div>
                <div class="label">Menunggu Verifikasi</div>
            </a>
            <a href="?filter=terverifikasi" class="stat-card <?= $filter === 'terverifikasi' ? 'active-filter' : '' ?>">
                <div class="number" style="color:var(--success)"><?= number_format($stats['terverifikasi']) ?></div>
                <div class="label">Terverifikasi</div>
            </a>
            <a href="?filter=belum_bayar" class="stat-card <?= $filter === 'belum_bayar' ? 'active-filter' : '' ?>">
                <div class="number" style="color:var(--gray-500)"><?= number_format($stats['belum_bayar']) ?></div>
                <div class="label">Belum Upload</div>
            </a>
        </div>

        <?php if (!$showList): ?>
            <!-- Halaman utama: hanya statistik, tanpa list -->
            <div class="card text-center" style="padding:28px 16px;">
                <p class="text-sm text-muted">
                    Ketuk salah satu kotak di atas untuk melihat daftar peserta.<br>
                    Layout dioptimalkan untuk hingga 2.000 data di layar HP.
                </p>
            </div>

        <?php else: ?>
            <!-- Mode drilldown: search + list + pagination -->
            <div class="card" style="padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <strong style="font-size:1rem;"><?= $titleList ?></strong>
                    <a href="index.php" class="text-xs" style="color:var(--primary);">← Kembali</a>
                </div>

                <!-- Search -->
                <form method="GET" class="search-box">
                    <input type="hidden" name="filter" value="<?= clean($filter) ?>">
                    <input type="search" name="q" value="<?= clean($q) ?>" placeholder="Cari nama, WA, no. peserta..." enterkeyhint="search">
                    <button type="submit">Cari</button>
                </form>

                <div class="result-info">
                    <?= number_format($totalRows) ?> data
                    <?php if ($q): ?> • pencarian: “<?= clean($q) ?>”<?php endif; ?>
                    <?php if ($totalRows > 0): ?>
                        • halaman <?= $page ?>/<?= $totalPages ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($pesertaList)): ?>
                    <p class="text-sm text-muted text-center" style="padding:20px 0;">Tidak ada data.</p>
                <?php else: ?>
                    <?php foreach ($pesertaList as $p): ?>
                        <?php
                        $badgeStyle = match($p['status_pembayaran']) {
                            'terverifikasi' => 'background:#dcfce7;color:#166534;',
                            'ditolak'       => 'background:#fee2e2;color:#991b1b;',
                            default         => 'background:#fef3c7;color:#92400e;',
                        };
                        $badgeLabel = labelStatusBayar($p['status_pembayaran'])[0];
                        ?>
                        <div class="list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                                <div class="name"><?= clean($p['nama_lengkap']) ?></div>
                                <span class="badge" style="<?= $badgeStyle ?>font-size:0.7rem;white-space:nowrap;"><?= $badgeLabel ?></span>
                            </div>
                            <div class="meta">
                                <span><?= clean($p['whatsapp']) ?></span>
                                <span><?= labelPaket($p['paket']) ?> · <?= formatRupiah($p['harga']) ?></span>
                                <?php if ($p['nomor_peserta']): ?>
                                    <span style="font-weight:600;color:var(--primary);"><?= clean($p['nomor_peserta']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="actions">
                                <?php if ($p['status_pembayaran'] === 'menunggu' && $p['bukti_pembayaran']): ?>
                                    <a href="verifikasi.php?id=<?= $p['id'] ?>" class="btn-sm-primary">Periksa Bukti</a>
                                <?php elseif ($p['status_pembayaran'] === 'terverifikasi'): ?>
                                    <a href="../peserta.php?slug=<?= makeSlug($p['nama_lengkap']) ?>&token=<?= $p['token'] ?>" target="_blank" class="btn-sm-secondary">Dashboard</a>
                                <?php else: ?>
                                    <span class="text-xs text-muted" style="padding:8px 0;">Belum ada aksi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $baseUrl = '?filter=' . urlencode($filter) . ($q ? '&q=' . urlencode($q) : '');
                            // Prev
                            if ($page > 1): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">‹</a>
                            <?php else: ?>
                                <span class="disabled">‹</span>
                            <?php endif;

                            // Page numbers (tampilkan max 5)
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $start + 4);
                            $start = max(1, $end - 4);

                            for ($i = $start; $i <= $end; $i++):
                                if ($i === $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                                <?php endif;
                            endfor;

                            // Next
                            if ($page < $totalPages): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">›</a>
                            <?php else: ?>
                                <span class="disabled">›</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
