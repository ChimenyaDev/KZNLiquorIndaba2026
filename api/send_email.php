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
require_once __DIR__ . '/lib/email.php';

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

$allowed_mimes = [
    'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv',
];

foreach ($attachments as $att) {
    if (empty($att['filename']) || empty($att['content'])) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => 'Each attachment must have filename and content']));
    }
    $mime = $att['mime'] ?? 'application/octet-stream';
    if (!in_array($mime, $allowed_mimes, true)) {
        http_response_code(422);
        exit(json_encode(['success' => false, 'message' => 'Attachment type not allowed: ' . $mime]));
    }
}

if (!check_rate_limit()) {
    http_response_code(429);
    exit(json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again shortly.']));
}

if (!$text && $html) {
    $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
}

$result = send_smtp_email($to, $toName, $subject, $html, $text, $attachments);

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
