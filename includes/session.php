<?php
/**
 * Centralized session bootstrap.
 * - Secure cookie flags (httponly / samesite; secure when HTTPS)
 * - Strict mode to reduce session fixation risk
 */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    // Configure cookie params BEFORE session_start()
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
