<?php
/**
 * KZN Liquor Indaba 2026 — Email Endpoint
 *
 * POST  /api/send_email.php
 * Body:
 * {
 *   "to":          "recipient@example.com",
 *   "toName":      "First Last",
 *   "subject":     "Email subject line",
 *   "html":        "<p>HTML body</p>",
 *   "text":        "Plain-text fallback",
 *   "attachments": [
 *     { "filename": "invite.pdf", "content": "<base64>", "mime": "application/pdf" }
 *   ]
 * }
 *
 * Returns: { "success": bool, "message": string }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lib/email_unified.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

set_cors_headers();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid JSON body']));
}

$to          = trim($input['to']      ?? '');
$toName      = trim($input['toName']  ?? '');
$subject     = trim($input['subject'] ?? '');
$html        = trim($input['html']    ?? '');
$text        = trim($input['text']    ?? '');
$attachments = $input['attachments'] ?? [];

if (!$to || !$subject || (!$html && !$text)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing required fields: to, subject, html or text']));
}

if (!validate_email($to)) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => 'Invalid recipient email address']));
}

if (strlen($subject) > 998) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => 'Subject line too long']));
}

if (!is_array($attachments)) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => 'attachments must be an array']));
}

$max_attachment_files = 5;
$max_attachment_size  = 5 * 1024 * 1024;
$max_total_size       = 10 * 1024 * 1024;
if (count($attachments) > $max_attachment_files) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => "Maximum {$max_attachment_files} attachments allowed"]));
}

$allowed_mimes = [
    'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv',
];

$ext_to_mime = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
];

$total_attachment_size = 0;
foreach ($attachments as &$att) {
    if (empty($att['filename']) || empty($att['content'])) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => 'Each attachment must have filename and content']));
    }
    $filename = trim((string)$att['filename']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $incoming_mime = strtolower(trim((string)($att['mime'] ?? '')));
    $mime = in_array($incoming_mime, $allowed_mimes, true)
        ? $incoming_mime
        : ($ext_to_mime[$ext] ?? ($incoming_mime ?: 'application/octet-stream'));
    if (!in_array($mime, $allowed_mimes, true)) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => 'Attachment type not allowed: ' . $mime]));
    }

    $content = preg_replace('/\s+/', '', (string)$att['content']);
    $decoded = base64_decode($content, true);
    if ($decoded === false) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => "Invalid base64 attachment content for {$filename}"]));
    }

    $declared_size = isset($att['size']) ? (int)$att['size'] : 0;
    $actual_size = $declared_size > 0 ? $declared_size : strlen($decoded);
    if ($actual_size > $max_attachment_size) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => "{$filename} exceeds 5MB per-file limit"]));
    }

    $total_attachment_size += $actual_size;
    if ($total_attachment_size > $max_total_size) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => 'Total attachment size exceeds 10MB limit']));
    }

    $att['mime'] = $mime;
    $att['content'] = $content;
    $att['size'] = $actual_size;
}
unset($att);

if (!check_rate_limit()) {
    http_response_code(429);
    exit(json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again shortly.']));
}

if (!$text && $html) {
    $text = html_to_plain_text($html);
}

$result = send_email_unified($to, $toName, $subject, $html, $text, $attachments);

log_communication([
    'type'    => 'email',
    'to'      => $to,
    'subject' => $subject,
    'success' => $result['success'],
    'error'   => $result['error'] ?? null,
]);

http_response_code($result['success'] ? 200 : 502);
header('Content-Type: application/json');
echo json_encode($result);
