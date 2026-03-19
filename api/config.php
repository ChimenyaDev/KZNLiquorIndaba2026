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
// Set SMS_PASSWORD via the SMS_PASSWORD environment variable (required for sending).
define('SMS_PASSWORD',     getenv('SMS_PASSWORD')     ?: '');
define('SMS_SENDER',       getenv('SMS_SENDER')       ?: 'KZNIndaba');

// ── Email Configuration ───────────────────────────────────────────────────────
// Email sending method: 'ews' (Exchange Web Services) or 'smtp'
define('EMAIL_METHOD',     getenv('EMAIL_METHOD')     ?: 'ews');
// Set to true (or EMAIL_DEBUG=1 env var) for verbose per-request email logging
define('EMAIL_DEBUG',      filter_var(getenv('EMAIL_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));

// ── EWS (Exchange Web Services) ──────────────────────────────────────────────
define('EWS_ENDPOINT',     getenv('EWS_ENDPOINT')     ?: 'https://mail.kznera.org.za/EWS/Exchange.asmx');
define('EWS_USERNAME',     getenv('EWS_USERNAME')     ?: 'nto.vinkhumbo@kznera.org.za');
// Set EWS_PASSWORD via the EWS_PASSWORD environment variable (required for sending).
define('EWS_PASSWORD',     getenv('EWS_PASSWORD')     ?: '');
define('EWS_FROM_EMAIL',   getenv('EWS_FROM_EMAIL')   ?: 'nto.vinkhumbo@kznera.org.za');
define('EWS_FROM_NAME',    getenv('EWS_FROM_NAME')    ?: 'KZN Liquor Indaba 2026');
define('EWS_VERSION',      getenv('EWS_VERSION')      ?: 'Exchange2013_SP1');

// ── Email (SMTP — fallback) ───────────────────────────────────────────────────
define('SMTP_HOST',        getenv('SMTP_HOST')        ?: 'mail.kznera.org.za');
define('SMTP_PORT',        (int)(getenv('SMTP_PORT')  ?: 587));
define('SMTP_USERNAME',    getenv('SMTP_USERNAME')    ?: 'nto.vinkhumbo@kznera.org.za');
// Set SMTP_PASSWORD via the SMTP_PASSWORD environment variable (required for sending).
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
