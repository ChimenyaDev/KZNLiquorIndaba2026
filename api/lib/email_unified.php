<?php
/**
 * KZN Liquor Indaba 2026 — Unified Email Library
 *
 * Wraps EWS and SMTP into a single send_email_unified() function.
 * Strategy:
 *   - If EMAIL_METHOD is 'ews'  → try EWS; on failure fall back to SMTP.
 *   - If EMAIL_METHOD is 'smtp' → use SMTP only.
 *
 * Both libraries are loaded lazily so the unused one is never executed.
 */

if (!defined('EMAIL_METHOD')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Send an email using the configured method (EWS or SMTP).
 *
 * @param  string $to          Recipient address
 * @param  string $toName      Recipient display name (may be empty)
 * @param  string $subject     Email subject
 * @param  string $html        HTML body (may be empty)
 * @param  string $text        Plain-text body
 * @param  array  $attachments Array of { filename, content (base64), mime }
 * @return array{ success: bool, message: string, error: string|null, method: string }
 */
function send_email_unified(
    string $to,
    string $toName,
    string $subject,
    string $html,
    string $text,
    array  $attachments
): array {
    $method = strtolower(EMAIL_METHOD);
    $debug  = defined('EMAIL_DEBUG') && EMAIL_DEBUG;

    if ($method === 'ews') {
        require_once __DIR__ . '/ews.php';

        if ($debug) {
            error_log("[Email] Attempting EWS send to $to");
        }

        $ewsResult = send_ews_email($to, $toName, $subject, $html, $text, $attachments);

        if ($ewsResult['success']) {
            if ($debug) {
                error_log("[Email] EWS send succeeded for $to");
            }
            return array_merge($ewsResult, ['method' => 'ews']);
        }

        // EWS failed — log and try SMTP fallback
        error_log("[Email] EWS failed for $to: " . ($ewsResult['error'] ?? $ewsResult['message'])
            . '. Falling back to SMTP.');

        require_once __DIR__ . '/email.php';

        if ($debug) {
            error_log("[Email] Attempting SMTP fallback send to $to");
        }

        $smtpResult = send_smtp_email($to, $toName, $subject, $html, $text, $attachments);

        if ($smtpResult['success']) {
            if ($debug) {
                error_log("[Email] SMTP fallback succeeded for $to");
            }
            return array_merge($smtpResult, [
                'method'       => 'smtp_fallback',
                'ews_error'    => $ewsResult['error'] ?? $ewsResult['message'],
            ]);
        }

        // Both methods failed
        error_log("[Email] Both EWS and SMTP failed for $to. "
            . "EWS: " . ($ewsResult['error'] ?? $ewsResult['message'])
            . " | SMTP: " . ($smtpResult['error'] ?? $smtpResult['message']));

        return [
            'success' => false,
            'message' => 'Email delivery failed via both EWS and SMTP. '
                . 'EWS: ' . $ewsResult['message']
                . ' | SMTP: ' . $smtpResult['message'],
            'error'   => $smtpResult['error'] ?? $ewsResult['error'],
            'method'  => 'none',
        ];
    }

    // SMTP-only path
    require_once __DIR__ . '/email.php';

    if ($debug) {
        error_log("[Email] Attempting SMTP send to $to");
    }

    $smtpResult = send_smtp_email($to, $toName, $subject, $html, $text, $attachments);

    if ($debug) {
        $status = $smtpResult['success'] ? 'succeeded' : 'failed';
        error_log("[Email] SMTP send $status for $to");
    }

    return array_merge($smtpResult, ['method' => 'smtp']);
}
