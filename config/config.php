<?php
declare(strict_types=1);

// Bridge directly to db.php to prevent constant redeclaration warnings
require_once __DIR__ . '/db.php';

// Safe sanitization helper that accepts strings or null without crashing
if (!function_exists('sanitize')) {
    function sanitize(?string $data): string {
        return htmlspecialchars(trim((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
    }
}