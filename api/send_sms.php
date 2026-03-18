<?php
/**
 * KZN Liquor Indaba 2026 — SMS Endpoint
 *
 * POST  /api/send_sms.php
 * Body: { "to": "0821234567", "message": "Your SMS text" }
 *
 * Returns: { "success": bool, "message": string, "gateway_ref": string|null }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lib/sms.php';

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

$to      = trim($input['to']      ?? '');
$message = trim($input['message'] ?? '');

if (!$to || !$message) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing required fields: to, message']));
}

if (!validate_phone($to)) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => 'Invalid phone number format']));
}

if (strlen($message) > 918) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => 'Message too long (max 918 characters)']));
}

if (!check_rate_limit()) {
    http_response_code(429);
    exit(json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again shortly.']));
}

$result = send_umsg_sms($to, $message);

log_communication([
    'type'        => 'sms',
    'to'          => $to,
    'message'     => $message,
    'success'     => $result['success'],
    'gateway_ref' => $result['gateway_ref'] ?? null,
    'error'       => $result['error'] ?? null,
]);

http_response_code($result['success'] ? 200 : 502);
header('Content-Type: application/json');
echo json_encode($result);
