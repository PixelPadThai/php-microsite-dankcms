<?php
final class Auth
{
    private const SESSION_NAME = 'MSD_SESS';
    private const CSRF_KEY = '_csrf_tokens';
    private const RATE_LIMIT_FILE = __DIR__ . '/../data/.rate-limit.json';

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name(self::SESSION_NAME);
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }

    public static function login(string $password): bool {
        self::startSession();
        if (!self::checkRateLimit('login', 5, 900)) return false;
        if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
            self::recordRateLimit('login');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['login_at'] = time();
        return true;
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        @session_destroy();
    }

    public static function isLoggedIn(): bool {
        self::startSession();
        if (empty($_SESSION['admin'])) return false;
        if (!empty(ADMIN_IP_ALLOWLIST) && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ADMIN_IP_ALLOWLIST, true)) return false;
        return true;
    }

    public static function require(): void {
        if (!self::isLoggedIn()) {
            header('Location: /dashboard/login');
            exit;
        }
    }

    public static function verifyPassword(string $pw, string $hash): bool {
        return password_verify($pw, $hash);
    }

    public static function csrfToken(): string {
        self::startSession();
        $t = bin2hex(random_bytes(16));
        $_SESSION[self::CSRF_KEY][$t] = time();
        if (count($_SESSION[self::CSRF_KEY]) > 50) {
            $_SESSION[self::CSRF_KEY] = array_slice($_SESSION[self::CSRF_KEY], -50, null, true);
        }
        return $t;
    }

    public static function checkCsrf(?string $token): bool {
        self::startSession();
        if (!$token) return false;
        if (!isset($_SESSION[self::CSRF_KEY][$token])) return false;
        unset($_SESSION[self::CSRF_KEY][$token]); // single-use
        return true;
    }

    public static function checkRateLimit(string $bucket, int $max, int $windowSec): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $data = self::readRateLimit();
        $key = "$bucket:$ip";
        $now = time();
        $events = array_filter($data[$key] ?? [], fn($t) => $t > $now - $windowSec);
        return count($events) < $max;
    }

    public static function recordRateLimit(string $bucket): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $data = self::readRateLimit();
        $key = "$bucket:$ip";
        $data[$key][] = time();
        self::writeRateLimit($data);
    }

    private static function readRateLimit(): array {
        if (!is_file(self::RATE_LIMIT_FILE)) return [];
        return json_decode(file_get_contents(self::RATE_LIMIT_FILE), true) ?? [];
    }

    private static function writeRateLimit(array $data): void {
        @file_put_contents(self::RATE_LIMIT_FILE, json_encode($data));
    }
}
