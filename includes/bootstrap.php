<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- Base URL (auto-detected, works in sub-folders such as /vetsmart) ---------- */
(function () {
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';
    $root = str_replace('\\', '/', $root);
    $app  = str_replace('\\', '/', $app);
    $base = ($root !== '' && strpos($app, $root) === 0) ? substr($app, strlen($root)) : '';
    define('BASE_URL', rtrim($base, '/'));
})();

/* ---------- Database ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check config/config.php and import database/vetsmart.sql.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []) { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? 0 : $r; }

/* ---------- Helpers ---------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path))); exit; }
function post(string $k, $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function nz($v) { $v = trim((string)$v); return $v === '' ? null : $v; }
function fmt_dt($v, $f = 'd M Y, h:i A'): string { return $v ? date($f, strtotime($v)) : '—'; }
function dt_local($v): ?string { // datetime-local input -> MySQL
    $v = trim((string)$v); if ($v === '') return null;
    $t = strtotime($v); return $t ? date('Y-m-d H:i:s', $t) : null;
}
function slug(string $s): string {
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'x';
}
function time_ago(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    return date('d M, h:i A', strtotime($dt));
}

/* ---------- Flash messages ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Auth ---------- */
function is_logged_in(): bool { return !empty($_SESSION['uid']); }
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }
function me(): array { return ['id' => uid(), 'name' => $_SESSION['name'] ?? '', 'role' => $_SESSION['role'] ?? '', 'designation' => $_SESSION['designation'] ?? '']; }

function require_login(array $roles = ['admin', 'user'], bool $json = false): void {
    if (!is_logged_in()) {
        if ($json) { json_out(['error' => 'auth'], 401); }
        redirect('login.php');
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        if ($json) { json_out(['error' => 'forbidden'], 403); }
        redirect(is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php');
    }
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data); exit;
}

/* Admin sees every row, a vet (user) sees only the rows they created. */
function scope_sql(string $col, array &$params): string {
    if (is_admin()) return '';
    $params[] = uid();
    return " AND $col = ?";
}

/* ---------- Dropdown data (always ascending) ---------- */
/* Farms, locations and batches are shared: every signed-in user sees every registered farm. */
function farm_list(): array {
    return q_all("SELECT id, farm_name, farm_code FROM farms ORDER BY farm_name ASC");
}
function farm_options(?int $selected = null, string $placeholder = 'Select farm'): string {
    $o = '<option value="">' . h($placeholder) . '</option>';
    foreach (farm_list() as $f) {
        $o .= '<option value="' . $f['id'] . '"' . ($selected === (int)$f['id'] ? ' selected' : '') . '>' . h($f['farm_name']) . ' (' . h($f['farm_code']) . ')</option>';
    }
    return $o;
}
function find_farm(int $id): ?array {
    return q_one("SELECT * FROM farms WHERE id = ?", [$id]);
}
function find_location(int $id, int $farmId): ?array {
    return q_one("SELECT * FROM farm_locations WHERE id = ? AND farm_id = ?", [$id, $farmId]);
}
function find_batch(int $id, int $farmId): ?array {
    return q_one("SELECT * FROM batches WHERE id = ? AND farm_id = ?", [$id, $farmId]);
}

/* ---------- Activity / notifications ---------- */
function log_activity(string $type, string $message, ?string $link = null): void {
    q("INSERT INTO activity_log (actor_id, actor_name, type, message, link) VALUES (?,?,?,?,?)",
      [uid(), $_SESSION['name'] ?? 'User', $type, mb_substr($message, 0, 250), $link]);
}
/* Admin sees everything; a user sees their own activity. */
function activity_scope(array &$params, string $alias = 'a'): string {
    if (is_admin()) return '';
    $params[] = uid();
    return " AND $alias.actor_id = ?";
}
function unread_count(): int {
    $p = [uid()];
    $sql = "SELECT COUNT(*) FROM activity_log a WHERE a.id > (SELECT notif_seen_id FROM users WHERE id = ?)";
    $p2 = [];
    $sql .= activity_scope($p2);
    return (int)q_val($sql, array_merge($p, $p2));
}

/* ---------- Form helpers ---------- */
function valid_phone(?string $v): bool { return $v === null || $v === '' || preg_match('/^\d{10}$/', $v) === 1; }
function coord($v): ?string {
    $v = trim((string)$v); return ($v !== '' && is_numeric($v)) ? $v : null;
}

/* Admin filter: veterinarians (role = user), A to Z. */
function vet_options(?int $selected = null, string $placeholder = 'All veterinarians'): string {
    $o = '<option value="">' . h($placeholder) . '</option>';
    foreach (q_all("SELECT id, full_name FROM users WHERE role = 'user' ORDER BY full_name ASC") as $u) {
        $o .= '<option value="' . $u['id'] . '"' . ($selected === (int)$u['id'] ? ' selected' : '') . '>' . h($u['full_name']) . '</option>';
    }
    return $o;
}
