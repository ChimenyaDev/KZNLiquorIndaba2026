<?php
/**
 * KZN Liquor Indaba 2026 — Communication Log Endpoint
 *
 * GET  /api/comm_log.php               → returns all log entries (newest first)
 * GET  /api/comm_log.php?type=email    → filter by type (email|sms)
 * GET  /api/comm_log.php?limit=100     → limit number of results
 * POST /api/comm_log.php               → append a manual log entry
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $log  = read_comm_log();
    $type = $_GET['type']  ?? '';
    $limit = (int) ($_GET['limit'] ?? 0);

    // Filter by type if requested
    if ($type) {
        $log = array_values(array_filter($log, fn($e) => ($e['type'] ?? '') === $type));
    }

    // Return newest first
    $log = array_reverse($log);

    // Apply limit
    if ($limit > 0) {
        $log = array_slice($log, 0, $limit);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'entries' => $log, 'count' => count($log)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry = json_decode(file_get_contents('php://input'), true);

    if (!$entry || !isset($entry['type'], $entry['to'])) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Missing required fields: type, to']));
    }

    log_communication($entry);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
