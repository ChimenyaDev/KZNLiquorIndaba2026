<?php
/**
 * KZN Liquor Indaba 2026 — Unified Notification Endpoint
 *
 * Accepts a list of recipients and sends email, SMS, or both according to
 * each delegate's communication preference or an explicit channel override.
 *
 * POST body (JSON):
 * {
 *   "notifType":   "email" | "sms" | "both",
 *   "label":       "Event Reminder",
 *   "subject":     "Email subject",
 *   "message":     "Body with {firstName}, {referenceNumber}, etc.",
 *   "recipients":  [
 *     {
 *       "firstName": "Sipho", "lastName": "Nkosi",
 *       "email": "sipho@example.com", "mobile": "0821234567",
 *       "commPref": "email" | "sms" | "both",
 *       "ref": "IND2026-ABC123"
 *     }
 *   ],
 *   "attachments": [ { "filename": "doc.pdf", "content": "<base64>", "mime": "application/pdf" } ],
 *   "sentBy":      "Admin username"
 * }
 *
 * Response: { "success": bool, "sent": int, "failed": int, "errors": [] }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lib/sms.php';
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

$notifType   = $input['notifType']   ?? 'both';
$label       = trim($input['label']       ?? 'Notification');
$subject     = trim($input['subject']     ?? 'KZN Liquor Indaba 2026');
$message     = trim($input['message']     ?? '');
$recipients  = $input['recipients']  ?? [];
$attachments = $input['attachments'] ?? [];
$sentBy      = trim($input['sentBy']      ?? 'System');

if (!$message) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'message is required']));
}

if (empty($recipients) || !is_array($recipients)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'recipients must be a non-empty array']));
}

if (!check_rate_limit()) {
    http_response_code(429);
    exit(json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again shortly.']));
}

$sent   = 0;
$failed = 0;
$errors = [];

foreach ($recipients as $delegate) {
    $firstName = $delegate['firstName'] ?? '';
    $lastName  = $delegate['lastName']  ?? '';
    $fullName  = $delegate['fullName']  ?? trim("$firstName $lastName");
    $email     = $delegate['email']     ?? '';
    $mobile    = $delegate['mobile']    ?? '';
    $commPref  = $delegate['commPref']  ?? 'both';
    $ref       = $delegate['ref']       ?? '';

    $vars = [
        'firstName'       => $firstName,
        'lastName'        => $lastName,
        'fullName'        => $fullName,
        'referenceNumber' => $ref,
        'eventDate'       => 'Friday 8 May – Saturday 9 May 2026',
        'eventTime'       => '08:00',
        'venue'           => 'Durban, KwaZulu-Natal',
    ];

    $personalised_message = apply_template_vars($message, $vars);
    $personalised_subject = apply_template_vars($subject, $vars);

    // Determine which channels to use
    $use_email = false;
    $use_sms   = false;

    if ($notifType === 'email') {
        $use_email = true;
    } elseif ($notifType === 'sms') {
        $use_sms = true;
    } elseif ($notifType === 'both') {
        $use_email = true;
        $use_sms   = true;
    } else {
        // Honour delegate's own preference
        if ($commPref === 'both') {
            $use_email = true;
            $use_sms   = true;
        } elseif ($commPref === 'sms') {
            $use_sms = true;
        } else {
            $use_email = true; // default to email
        }
    }

    // ── Send Email ─────────────────────────────────────────────────────────────
    if ($use_email && $email && validate_email($email)) {
        $html_body = build_email_html($personalised_subject, $firstName, $personalised_message);
        $text_body = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $personalised_message));

        $email_result = send_smtp_email($email, $fullName, $personalised_subject, $html_body, $text_body, $attachments);

        log_communication([
            'type'    => 'email',
            'to'      => $email,
            'ref'     => $ref,
            'label'   => $label,
            'subject' => $personalised_subject,
            'success' => $email_result['success'],
            'sentBy'  => $sentBy,
            'error'   => $email_result['error'] ?? null,
        ]);

        if ($email_result['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = ['recipient' => $email, 'error' => $email_result['message']];
        }
    }

    // ── Send SMS ───────────────────────────────────────────────────────────────
    if ($use_sms && $mobile && validate_phone($mobile)) {
        $sms_text   = substr(strip_tags($personalised_message), 0, 918);
        $sms_result = send_umsg_sms($mobile, $sms_text);

        log_communication([
            'type'        => 'sms',
            'to'          => $mobile,
            'ref'         => $ref,
            'label'       => $label,
            'message'     => $sms_text,
            'success'     => $sms_result['success'],
            'gateway_ref' => $sms_result['gateway_ref'] ?? null,
            'sentBy'      => $sentBy,
            'error'       => $sms_result['error'] ?? null,
        ]);

        if ($sms_result['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = ['recipient' => $mobile, 'error' => $sms_result['message']];
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => $failed === 0,
    'sent'    => $sent,
    'failed'  => $failed,
    'errors'  => $errors,
]);
