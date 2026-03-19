<?php
/**
 * KZN Liquor Indaba 2026 — Email Library
 *
 * Provides send_smtp_email() for use by send_email.php and notify.php.
 */

if (!defined('SMTP_HOST')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Send an email via SMTP using native socket functions (no external library).
 * Supports STARTTLS, AUTH LOGIN, and MIME multipart with optional attachments.
 *
 * @param  string $to          Recipient address
 * @param  string $toName      Recipient display name (may be empty)
 * @param  string $subject     Email subject
 * @param  string $html        HTML body (may be empty)
 * @param  string $text        Plain-text body
 * @param  array  $attachments Array of { filename, content (base64), mime }
 * @return array{ success: bool, message: string, error: string|null }
 */
function send_smtp_email(
    string $to,
    string $toName,
    string $subject,
    string $html,
    string $text,
    array  $attachments
): array {
    $boundary    = 'KZNIndaba_'  . bin2hex(random_bytes(8));
    $altBoundary = 'KZNAlt_'     . bin2hex(random_bytes(8));

    $body = build_mime_body($html, $text, $attachments, $boundary, $altBoundary);

    $host       = SMTP_HOST;
    $port       = SMTP_PORT;
    $encryption = SMTP_ENCRYPTION;

    $debug = defined('EMAIL_DEBUG') && EMAIL_DEBUG;

    if ($debug) {
        // DNS resolution check
        $resolved = gethostbyname($host);
        $dnsOk    = $resolved !== $host;
        error_log("[SMTP] DNS: $host → " . ($dnsOk ? $resolved : 'FAILED'));
        error_log("[SMTP] Connecting to $host:$port (encryption=$encryption)");
    }

    $errno  = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("[SMTP] Connection failed: host=$host port=$port errno=$errno error=$errstr");
        return [
            'success' => false,
            'message' => "Cannot connect to SMTP server ($host:$port): $errstr",
            'error'   => $errstr,
            'debug'   => "errno=$errno host=$host port=$port",
        ];
    }

    if ($debug) {
        error_log("[SMTP] Connected to $host:$port");
    }

    stream_set_timeout($socket, 15);

    try {
        $greeting = smtp_expect($socket, '220', 'SMTP greeting');
        if ($debug) { error_log("[SMTP] ← $greeting"); }

        smtp_send($socket, "EHLO kznera.org.za");
        if ($debug) { error_log("[SMTP] → EHLO kznera.org.za"); }
        $caps = smtp_read_multi($socket);
        if ($debug) { error_log("[SMTP] ← " . implode(' | ', $caps)); }

        if ($encryption === 'tls') {
            smtp_send($socket, 'STARTTLS');
            if ($debug) { error_log("[SMTP] → STARTTLS"); }
            smtp_expect($socket, '220', 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed');
            }
            if ($debug) { error_log("[SMTP] TLS negotiated successfully"); }
            smtp_send($socket, "EHLO kznera.org.za");
            smtp_read_multi($socket);
        }

        smtp_send($socket, 'AUTH LOGIN');
        if ($debug) { error_log("[SMTP] → AUTH LOGIN"); }
        smtp_expect($socket, '334', 'AUTH LOGIN');
        smtp_send($socket, base64_encode(SMTP_USERNAME));
        smtp_expect($socket, '334', 'AUTH username');
        smtp_send($socket, base64_encode(SMTP_PASSWORD));
        smtp_expect($socket, '235', 'AUTH password');
        if ($debug) { error_log("[SMTP] AUTH LOGIN succeeded"); }

        smtp_send($socket, 'MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
        smtp_expect($socket, '250', 'MAIL FROM');

        smtp_send($socket, 'RCPT TO:<' . $to . '>');
        smtp_expect($socket, '250', 'RCPT TO');
        if ($debug) { error_log("[SMTP] RCPT TO <$to> accepted"); }

        smtp_send($socket, 'DATA');
        smtp_expect($socket, '354', 'DATA');

        $toDisplay = $toName ? '"' . encode_header_phrase($toName) . '" <' . $to . '>' : $to;

        $headers  = "From: \"" . encode_header_phrase(SMTP_FROM_NAME) . "\" <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: $toDisplay\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($attachments) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        } else {
            $headers .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n";
        }

        $headers .= "X-Mailer: KZNLiquorIndaba2026/1.0\r\n\r\n";

        fwrite($socket, $headers . $body . "\r\n.\r\n");
        smtp_expect($socket, '250', 'message accepted');
        if ($debug) { error_log("[SMTP] Message accepted by server"); }

        smtp_send($socket, 'QUIT');
        fclose($socket);

        return ['success' => true, 'message' => 'Email sent successfully', 'error' => null];

    } catch (RuntimeException $e) {
        error_log("[SMTP] Error: " . $e->getMessage());
        @fclose($socket);
        return ['success' => false, 'message' => $e->getMessage(), 'error' => $e->getMessage()];
    }
}

/**
 * Wrap a notification message in a branded HTML email template.
 */
function build_email_html(string $subject, string $firstName, string $message): string {
    $safeSubject  = htmlspecialchars($subject,  ENT_QUOTES, 'UTF-8');
    $safeFirst    = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeMessage  = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$safeSubject}</title>
  <style>
    body { margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif; }
    .wrapper { max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;overflow:hidden; }
    .header  { background:#1a3560;color:#ffffff;padding:28px 32px; }
    .header h1 { margin:0;font-size:1.3rem;font-weight:700; }
    .header p  { margin:4px 0 0;font-size:0.85rem;opacity:0.85; }
    .body    { padding:30px 32px;color:#2d3748;font-size:0.95rem;line-height:1.7; }
    .footer  { padding:18px 32px;background:#f4f5f7;color:#718096;font-size:0.78rem;border-top:1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>KZN Liquor Indaba 2026</h1>
    <p>KwaZulu-Natal Economic Regulatory Authority</p>
  </div>
  <div class="body">
    <p>Dear {$safeFirst},</p>
    <p>{$safeMessage}</p>
    <p style="margin-top:28px;font-size:0.85rem;color:#718096;">
      For enquiries contact <a href="mailto:indaba@kznera.co.za" style="color:#1a3560;">indaba@kznera.co.za</a>
    </p>
  </div>
  <div class="footer">
    <p>KZN Liquor Indaba 2026 &nbsp;|&nbsp; Friday 8 May – Saturday 9 May 2026 &nbsp;|&nbsp; Durban, KwaZulu-Natal</p>
    <p>Personal information handled in accordance with POPIA (Act 4 of 2013).</p>
  </div>
</div>
</body>
</html>
HTML;
}

// ── MIME builder ───────────────────────────────────────────────────────────────

function build_mime_body(
    string $html,
    string $text,
    array  $attachments,
    string $boundary,
    string $altBoundary
): string {
    $body = '';

    if ($attachments) {
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
    }

    $body .= "--$altBoundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode($text) . "\r\n";

    if ($html) {
        $body .= "--$altBoundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n";
    }

    $body .= "--$altBoundary--\r\n";

    foreach ($attachments as $att) {
        $mime     = $att['mime']     ?? 'application/octet-stream';
        $filename = $att['filename'] ?? 'attachment';
        $content  = $att['content'];

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: $mime; name=\"" . encode_mime_filename($filename) . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . encode_mime_filename($filename) . "\"\r\n\r\n";
        $body .= chunk_split($content, 76, "\r\n");
    }

    if ($attachments) {
        $body .= "--$boundary--\r\n";
    }

    return $body;
}

// ── SMTP helpers ───────────────────────────────────────────────────────────────

function smtp_send($socket, string $cmd): void {
    fwrite($socket, $cmd . "\r\n");
}

function smtp_expect($socket, string $code, string $context): string {
    $line = fgets($socket, 512);
    if ($line === false) {
        throw new RuntimeException("No response from server during $context");
    }
    if (!str_starts_with(trim($line), $code)) {
        throw new RuntimeException("Expected $code during $context, got: " . trim($line));
    }
    return trim($line);
}

function smtp_read_multi($socket): array {
    $lines = [];
    while (($line = fgets($socket, 512)) !== false) {
        $lines[] = trim($line);
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $lines;
}

// ── Encoding helpers ────────────────────────────────────────────────────────────

/**
 * Encode a display name for use in an RFC 2822 "phrase" (e.g. From/To header).
 * Strips newlines to prevent header injection, then applies base64 encoded-word
 * encoding to handle non-ASCII characters safely.
 */
function encode_header_phrase(string $phrase): string {
    // Strip any CR/LF characters to prevent header injection
    $phrase = preg_replace('/[\r\n]/', '', $phrase);
    // Use RFC 2047 encoded-word so non-ASCII characters are safe
    return '=?UTF-8?B?' . base64_encode($phrase) . '?=';
}

/**
 * Encode an attachment filename for use in a MIME Content-Disposition header.
 * Strips path separators and newlines, applies RFC 2047 encoded-word encoding.
 */
function encode_mime_filename(string $filename): string {
    // Strip directory separators and newlines to prevent injection
    $filename = preg_replace('/[\r\n\/\\\\]/', '', $filename);
    return '=?UTF-8?B?' . base64_encode($filename) . '?=';
}

/**
 * Convert HTML to a plain-text fallback by stripping tags and normalising
 * line breaks.  Shared by notify.php and send_email.php.
 */
function html_to_plain_text(string $html): string {
    $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
    $text = str_replace(['</p>', '</div>', '</h1>', '</h2>', '</h3>'], "\n\n", $text);
    return trim(strip_tags($text));
}
