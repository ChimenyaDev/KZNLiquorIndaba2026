<?php
/**
 * KZN Liquor Indaba 2026 — Notification Configuration
 *
 * Credentials are read from environment variables first, then fall back
 * to the constants below. Override via environment variables in production
 * to avoid storing secrets in source code.
 */

// ── SMS Gateway (UMSG) ────────────────────────────────────────────────────────
define('SMS_GATEWAY_URL',  getenv('SMS_GATEWAY_URL')  ?: 'https://sms01.umsg.co.za/xml/send');
define('SMS_USERNAME',     getenv('SMS_USERNAME')     ?: 'kzn_liquor_sa');
define('SMS_PASSWORD',     getenv('SMS_PASSWORD')     ?: '7dy6tY#D');
define('SMS_SENDER',       getenv('SMS_SENDER')       ?: 'KZNIndaba');

// ── Email (SMTP / Exchange OWA) ───────────────────────────────────────────────
define('SMTP_HOST',        getenv('SMTP_HOST')        ?: 'mail.kznera.org.za');
define('SMTP_PORT',        (int)(getenv('SMTP_PORT')  ?: 587));
define('SMTP_USERNAME',    getenv('SMTP_USERNAME')    ?: 'nto.vinkhumbo@kznera.org.za');
define('SMTP_PASSWORD',    getenv('SMTP_PASSWORD')    ?: '');
define('SMTP_FROM_EMAIL',  getenv('SMTP_FROM_EMAIL')  ?: 'nto.vinkhumbo@kznera.org.za');
define('SMTP_FROM_NAME',   getenv('SMTP_FROM_NAME')   ?: 'KZN Liquor Indaba 2026');
define('SMTP_ENCRYPTION',  getenv('SMTP_ENCRYPTION')  ?: 'tls');   // 'tls' or 'ssl'

// ── Allowed CORS origins ──────────────────────────────────────────────────────
// Restrict to the domains that host the HTML files.
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8080',
    'https://kznliquorindaba2026.azurewebsites.net',
]);

// ── Communication log ─────────────────────────────────────────────────────────
// Path to the JSON log file relative to this script.
define('COMM_LOG_FILE', __DIR__ . '/data/comm_log.json');

// ── Rate limiting ─────────────────────────────────────────────────────────────
define('RATE_LIMIT_PER_MINUTE', (int)(getenv('RATE_LIMIT_PER_MINUTE') ?: 30));
define('RATE_LIMIT_FILE',       __DIR__ . '/data/rate_limit.json');
