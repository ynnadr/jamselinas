<?php
// includes/functions.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

/**
 * Generate random token
 */
function generateToken($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Buat slug dari nama
 */
function makeSlug($nama) {
    $slug = strtolower(trim($nama));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return $slug;
}

/**
 * Generate nomor peserta (contoh: BDG-2026-0001)
 */
function generateNomorPeserta($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM peserta WHERE status_pembayaran = 'terverifikasi'");
    $row = $stmt->fetch();
    $next = ($row['total'] ?? 0) + 1;
    return 'BDG-2026-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * Format rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Label status pembayaran
 */
function labelStatusBayar($status) {
    $map = [
        'menunggu' => ['Menunggu Verifikasi', 'bg-yellow-100 text-yellow-800'],
        'terverifikasi' => ['Terverifikasi', 'bg-green-100 text-green-800'],
        'ditolak' => ['Ditolak', 'bg-red-100 text-red-800'],
    ];
    return $map[$status] ?? ['Unknown', 'bg-gray-100 text-gray-800'];
}

/**
 * Label paket
 */
function labelPaket($paket) {
    $map = [
        'early_bird' => 'Early Bird',
        'regular' => 'Regular',
        'late' => 'Late',
    ];
    return $map[$paket] ?? $paket;
}

/**
 * Sanitize input
 */
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Flash message sederhana via session
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}