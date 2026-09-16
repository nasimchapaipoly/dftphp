<?php
/**
 * Class Routine Management System (NasimSoft)
 * Production Security & Rate Limiting Engine
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

// 1. Strict Session Security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_set_cookie_params([
        'lifetime' => 28800, // 8 hours
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// 2. Production HTTP Security Headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:;");

// 3. In-Memory / File-based Rate Limiter (Brute-force protection)
function checkRateLimit(string $actionKey, int $maxAttempts = 5, int $decaySeconds = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $cacheDir = sys_get_temp_dir() . '/nasimsoft_ratelimit';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0750, true);
    }

    $hash = md5($actionKey . '_' . $ip);
    $file = $cacheDir . '/' . $hash . '.json';

    $data = ['attempts' => 0, 'first_attempt' => time()];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            $data = json_decode($content, true) ?: $data;
        }
    }

    // Reset window if decay elapsed
    if ((time() - $data['first_attempt']) > $decaySeconds) {
        $data = ['attempts' => 1, 'first_attempt' => time()];
        @file_put_contents($file, json_encode($data));
        return true;
    }

    if ($data['attempts'] >= $maxAttempts) {
        return false; // Rate limit exceeded
    }

    $data['attempts']++;
    @file_put_contents($file, json_encode($data));
    return true;
}

// 4. Input Sanitization Helpers
function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitizeArray(array $data): array {
    $sanitized = [];
    foreach ($data as $key => $value) {
        $cleanKey = htmlspecialchars(trim((string)$key), ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            $sanitized[$cleanKey] = sanitizeArray($value);
        } else {
            $sanitized[$cleanKey] = sanitizeInput((string)$value);
        }
    }
    return $sanitized;
}