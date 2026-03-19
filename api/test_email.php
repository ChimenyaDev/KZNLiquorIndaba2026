<?php
/**
 * KZN Liquor Indaba 2026 — Email Diagnostic Test Script
 *
 * Usage:
 *   CLI:     php api/test_email.php [--to=recipient@example.com]
 *   Browser: https://<host>/api/test_email.php  (admin IP only)
 *
 * Tests DNS, port connectivity, EWS endpoint reachability, and
 * optionally sends a real test email via EWS and SMTP.
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────

// Restrict browser access to localhost / private ranges only
if (PHP_SAPI !== 'cli') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $private = (
        str_starts_with($remote, '127.')   ||
        str_starts_with($remote, '10.')    ||
        str_starts_with($remote, '192.168.') ||
        str_starts_with($remote, '172.')   ||
        $remote === '::1'
    );
    if (!$private) {
        http_response_code(403);
        exit("Access denied. Run this script from the command line or a trusted network.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ews.php';
require_once __DIR__ . '/lib/email.php';

// Optional recipient override
$testTo = 'test@example.com';
if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--to=')) {
            $testTo = substr($arg, 5);
        }
    }
} elseif (!empty($_GET['to'])) {
    $testTo = filter_var($_GET['to'], FILTER_SANITIZE_EMAIL);
}

$mailHost = parse_url(EWS_ENDPOINT, PHP_URL_HOST) ?: SMTP_HOST;

// ── Output helpers ─────────────────────────────────────────────────────────────

function diag_line(string $line = ''): void { echo $line . "\n"; }
function diag_ok(string $msg): void  { echo "✓ $msg\n"; }
function diag_fail(string $msg): void { echo "✗ $msg\n"; }
function diag_info(string $msg): void { echo "  $msg\n"; }
function diag_section(string $title): void
{
    echo "\n$title\n";
    echo str_repeat('-', strlen($title)) . "\n";
}

// ── Header ─────────────────────────────────────────────────────────────────────

diag_line('=== KZN Liquor Indaba 2026 — Email Diagnostic Test ===');
diag_line();

diag_section('Configuration');
diag_info('Email Method : ' . EMAIL_METHOD);
diag_info('EWS Endpoint : ' . EWS_ENDPOINT);
diag_info('EWS Username : ' . EWS_USERNAME);
diag_info('EWS Password : ' . (EWS_PASSWORD !== '' ? '(set)' : '(NOT SET — tests will fail)'));
diag_info('EWS Version  : ' . EWS_VERSION);
diag_info('SMTP Host    : ' . SMTP_HOST . ':' . SMTP_PORT . ' (' . SMTP_ENCRYPTION . ')');
diag_info('SMTP Username: ' . SMTP_USERNAME);
diag_info('SMTP Password: ' . (SMTP_PASSWORD !== '' ? '(set)' : '(NOT SET)'));
diag_info('Test To      : ' . $testTo);

// ── DNS Resolution ─────────────────────────────────────────────────────────────

diag_section('DNS Resolution Test');

$resolved = gethostbyname($mailHost);
if ($resolved !== $mailHost) {
    diag_ok("$mailHost resolves to $resolved");
} else {
    diag_fail("$mailHost could not be resolved");
}

// ── Port Connectivity ─────────────────────────────────────────────────────────

diag_section('Port Connectivity Test');

$ports = [25, 587, 465, 443];
$portResults = [];
foreach ($ports as $port) {
    $errno  = 0;
    $errstr = '';
    $sock   = @fsockopen($mailHost, $port, $errno, $errstr, 5);
    if ($sock) {
        diag_ok("Port $port: Open");
        fclose($sock);
        $portResults[$port] = true;
    } else {
        diag_fail("Port $port: $errstr");
        $portResults[$port] = false;
    }
}

// ── EWS Endpoint Test ─────────────────────────────────────────────────────────

diag_section('EWS Endpoint Test');

$ewsConn = ews_test_connectivity();
if ($ewsConn['reachable']) {
    diag_ok('EWS endpoint is reachable (HTTP ' . $ewsConn['http_code'] . ')');
    if ($ewsConn['http_code'] === 401) {
        diag_info('Note: Server requires authentication (expected for EWS)');
    }
} else {
    diag_fail('EWS endpoint is NOT reachable'
        . ($ewsConn['error'] ? ': ' . $ewsConn['error'] : ' (HTTP ' . $ewsConn['http_code'] . ')'));
}

// ── EWS Send Test ─────────────────────────────────────────────────────────────

diag_section('EWS Email Send Test');

if (EWS_PASSWORD === '') {
    diag_fail('Skipped — EWS_PASSWORD is not set');
} else {
    $ewsResult = send_ews_email(
        $testTo,
        'Test Recipient',
        '[Test] KZN Liquor Indaba 2026 — EWS Diagnostic',
        '<p>This is an automated <strong>EWS diagnostic test</strong> email from the KZN Liquor Indaba 2026 system.</p>',
        'This is an automated EWS diagnostic test email from the KZN Liquor Indaba 2026 system.',
        []
    );

    if ($ewsResult['success']) {
        diag_ok('Email sent successfully via EWS to ' . $testTo);
    } else {
        diag_fail('EWS send failed: ' . $ewsResult['message']);
    }
}

// ── SMTP Send Test ────────────────────────────────────────────────────────────

diag_section('SMTP Email Send Test');

if (SMTP_PASSWORD === '') {
    diag_fail('Skipped — SMTP_PASSWORD is not set');
} else {
    $smtpResult = send_smtp_email(
        $testTo,
        'Test Recipient',
        '[Test] KZN Liquor Indaba 2026 — SMTP Diagnostic',
        '<p>This is an automated <strong>SMTP diagnostic test</strong> email from the KZN Liquor Indaba 2026 system.</p>',
        'This is an automated SMTP diagnostic test email from the KZN Liquor Indaba 2026 system.',
        []
    );

    if ($smtpResult['success']) {
        diag_ok('Email sent successfully via SMTP to ' . $testTo);
    } else {
        diag_fail('SMTP send failed: ' . $smtpResult['message']);
    }
}

// ── Recommendation ────────────────────────────────────────────────────────────

diag_section('Recommendation');

$ewsOk   = isset($ewsResult)   && $ewsResult['success'];
$smtpOk  = isset($smtpResult)  && $smtpResult['success'];
$ewsPass = EWS_PASSWORD !== '';
$smtpPass = SMTP_PASSWORD !== '';

if ($ewsOk) {
    diag_ok('EWS is working correctly — keep EMAIL_METHOD=ews');
} elseif ($smtpOk) {
    diag_info('EWS send failed but SMTP succeeded.');
    diag_info('Consider setting EMAIL_METHOD=smtp until EWS is resolved.');
} elseif ($ewsConn['reachable'] && $ewsPass) {
    diag_info('EWS endpoint is reachable but send failed.');
    diag_info('Check EWS_USERNAME, EWS_PASSWORD and mailbox permissions.');
} elseif (!$ewsConn['reachable']) {
    diag_info('EWS endpoint is not reachable from this server.');
    if ($portResults[587] ?? false) {
        diag_info('Port 587 is open — SMTP may work. Try EMAIL_METHOD=smtp.');
    } elseif ($portResults[465] ?? false) {
        diag_info('Port 465 is open — try SMTP_PORT=465 and SMTP_ENCRYPTION=ssl.');
    } else {
        diag_info('No mail ports are open. Check server firewall rules.');
    }
} elseif (!$ewsPass && !$smtpPass) {
    diag_info('Neither EWS_PASSWORD nor SMTP_PASSWORD is set.');
    diag_info('Set the appropriate environment variable and re-run this script.');
} else {
    diag_info('Unable to determine the best configuration automatically.');
    diag_info('Please review the test results above and contact KZNERA IT.');
}

diag_line();
