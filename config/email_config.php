<?php
require_once __DIR__ . '/env.php';

// SMTP settings (load from environment when available)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));

define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: (SMTP_USER ?: ''));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Sistema de Registro');
