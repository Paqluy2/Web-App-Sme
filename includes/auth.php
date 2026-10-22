<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

class Auth
{
    public static function login(string $username, string $password): bool
    {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM employees WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $passwordValid = false;

        // Check if already hashed
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        }
        // Fallback: Check plain text match from original SQL dump (e.g. admin123)
        else if ($password === $user['password']) {
            $passwordValid = true;
            // Upgrade to secure Bcrypt hash automatically
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upgradeStmt = $db->prepare('UPDATE employees SET password = ? WHERE employee_id = ?');
            $upgradeStmt->execute([$newHash, $user['employee_id']]);
        }

        if ($passwordValid) {
            session_regenerate_id(true);
            $_SESSION['employee_id'] = (int) $user['employee_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['First_name'] . ' ' . $user['Last_name'];
            $_SESSION['role'] = $user['role'];

            // Record into audit_log table from gigscafe schema
            $audit = $db->prepare('INSERT INTO audit_log (Employee_id, action_details, log_date) VALUES (?, ?, NOW())');
            $audit->execute([$user['employee_id'], 'Logged into the system via web interface']);

            return true;
        }

        return false;
    }

    public static function check(): bool
    {
        return isset($_SESSION['employee_id'], $_SESSION['role']);
    }

    public static function requireStaff(): void
    {
        if (!self::check()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function requireManager(): void
    {
        self::requireStaff();
        if ($_SESSION['role'] !== 'Manager') {
            http_response_code(403);
            die('Access Denied: Requires Manager privileges.');
        }
    }

    public static function logout(): void
    {
        if (isset($_SESSION['employee_id'])) {
            $db = getDB();
            $audit = $db->prepare('INSERT INTO audit_log (Employee_id, action_details, log_date) VALUES (?, ?, NOW())');
            $audit->execute([$_SESSION['employee_id'], 'Logged out of the system']);
        }
        $_SESSION = [];
        session_destroy();
    }
}
// =============================================================
// CSRF & Input Sanitization Helpers
// =============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('get_csrf_token')) {
    function get_csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $data): string {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}