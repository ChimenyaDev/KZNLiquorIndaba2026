<?php
/**
 * KZN Liquor Indaba 2026 — Shared Helper Functions
 *
 * Used by send_sms.php, send_email.php, notify.php and comm_log.php.
 */

require_once __DIR__ . '/config.php';

// ── CORS ───────────────────────────────────────────────────────────────────────

/**
 * Set CORS headers, restricting to ALLOWED_ORIGINS defined in config.php.
 * Handles pre-flight OPTIONS requests.
 */
function set_cors_headers(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Validation ─────────────────────────────────────────────────────────────────

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Accept South African mobile numbers in common formats:
 *  0821234567  /  +27821234567  /  27821234567
 */
function validate_phone(string $phone): bool {
    $clean = preg_replace('/[\s\-()]/', '', $phone);
    return (bool) preg_match('/^(\+27|0027|0)[6-9]\d{8}$/', $clean);
}

// ── Template variable substitution ────────────────────────────────────────────

/**
 * Replace {placeholders} in a template string with actual values.
 *
 * @param  string               $template Template containing {key} placeholders
 * @param  array<string,string> $vars     Associative map of key → value
 * @return string
 */
function apply_template_vars(string $template, array $vars): string {
    foreach ($vars as $key => $value) {
        $template = str_replace('{' . $key . '}', (string) $value, $template);
    }
    return $template;
}

// ── Rate limiting ──────────────────────────────────────────────────────────────

/**
 * Simple file-based rate limiter (per-server, not per-user).
 * Returns true if the request is within the allowed rate, false otherwise.
 */
function check_rate_limit(): bool {
    $file  = RATE_LIMIT_FILE;
    $limit = RATE_LIMIT_PER_MINUTE;

    ensure_data_dir();

    $data = file_exists($file)
        ? (json_decode(file_get_contents($file), true) ?: [])
        : [];

    $now    = time();
    $window = 60;

    // Remove entries outside the current 1-minute window
    $data = array_filter($data, fn($ts) => ($now - $ts) < $window);
    $data = array_values($data);

    if (count($data) >= $limit) {
        return false;
    }

    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

// ── Communication logging ─────────────────────────────────────────────────────

/**
 * Append one communication record to the JSON log file.
 *
 * @param array $entry Fields: type, to, subject?, message?, success, error?, gateway_ref?
 */
function log_communication(array $entry): void {
    ensure_data_dir();

    $file = COMM_LOG_FILE;
    $log  = file_exists($file)
        ? (json_decode(file_get_contents($file), true) ?: [])
        : [];

    $entry['timestamp'] = date('c'); // ISO 8601
    $log[]              = $entry;

    // Keep the log to the most recent 10 000 entries to avoid unbounded growth
    if (count($log) > 10000) {
        $log = array_slice($log, -10000);
    }

    file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Read all entries from the communication log.
 *
 * @return array
 */
function read_comm_log(): array {
    $file = COMM_LOG_FILE;
    if (!file_exists($file)) {
        return [];
    }
    return json_decode(file_get_contents($file), true) ?: [];
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function ensure_data_dir(): void {
    $dir = dirname(COMM_LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}
