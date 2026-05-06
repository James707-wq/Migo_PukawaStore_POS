<?php
// ============================================================
//  config.php  –  Database connection & global settings
// ============================================================

define('DB_HOST',     'sql207.infinityfree.com');
define('DB_USER',     'if0_41827958');          // InfinityFree
define('DB_PASS',     'pukawastore4');              // InfinityFree
define('DB_NAME',     'if0_41827958_pukawa_pos');
define('DB_PORT',     3306);

define('STORE_NAME',    'Pukawa Store');
define('STORE_ADDRESS', '123 Rizal St., Cagayan de Oro City');
define('STORE_PHONE',   '0912-345-6789');
define('STORE_TIN',     '123-456-789-000');

define('APP_NAME',    'Pukawa Store POS');
define('APP_VERSION', '1.0.0');

// ── Dynamic BASE_URL ────────────────────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && $basePath !== '\\') {
    $basePath = rtrim($basePath, '/') . '/';
} else {
    $basePath = '/';
}
define('BASE_URL', $protocol . $host . $basePath);

// Session lifetime (seconds)
define('SESSION_LIFETIME', 28800);  // 8 hours

// ── Google reCAPTCHA v3 ────────────────────────────────────
// Get your keys from: https://www.google.com/recaptcha/admin
// Replace with your actual reCAPTCHA keys
define('RECAPTCHA_SITE_KEY',   'YOUR_RECAPTCHA_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'YOUR_RECAPTCHA_SECRET_KEY');

// ── PDO connection ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                           DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['success' => false,
                             'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Session bootstrap ───────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// ── Helpers ─────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isUserActive(): bool {
    if (!isLoggedIn()) return false;
    
    $db = getDB();
    $stmt = $db->prepare('SELECT is_active FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    return $user && (bool)$user['is_active'];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    
    // Check if user's account is still active
    if (!isUserActive()) {
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?msg=account_deactivated');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . 'dashboard.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role']      ?? 'cashier',
    ];
}

function jsonResponse(bool $success, string $message = '', array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function generateTransactionNo(): string {
    return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function formatCurrency(float $amount): string {
    return '₱ ' . number_format($amount, 2);
}
