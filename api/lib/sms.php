<?php
/**
 * KZN Liquor Indaba 2026 — SMS Library
 *
 * Provides send_umsg_sms() for use by send_sms.php and notify.php.
 * Sends SMS via UMSG gateway using GET with query parameters:
 * https://sms01.umsg.co.za/xml/send/?number1=...&message1=...
 */

if (!defined('SMS_GATEWAY_URL')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Normalise a South African mobile number to international format WITHOUT + prefix
 * (as required by UMSG gateway).
 *
 * Accepts: 0821234567 / +27821234567 / 27821234567 / 082 123 4567
 * Returns: 27821234567 (no + prefix)
 */
function normalise_za_number(string $number): string {
    // Remove ALL non-numeric characters (spaces, dashes, parentheses, plus signs)
    $cleaned = preg_replace('/[^\d]/', '', $number);
    if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
        return '27' . substr($cleaned, 1);
    }
    if (str_starts_with($cleaned, '27') && strlen($cleaned) === 11) {
        return $cleaned;
    }
    return $cleaned;
}

/**
 * Send an SMS through the UMSG Gateway using GET with query parameters.
 *
 * @param  string $to      Recipient phone number
 * @param  string $message SMS body
 * @return array{ success: bool, message: string, gateway_ref: string|null, error: string|null }
 */
function send_umsg_sms(string $to, string $message): array {
    $destination = normalise_za_number($to);

    // Build GET request URL with query parameters
    // Format: https://sms01.umsg.co.za/xml/send/?number1=27821234567&message1=Your+message
    $url = rtrim(SMS_GATEWAY_URL, '/') . '/?' . http_build_query([
        'number1'  => $destination,
        'message1' => $message,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_USERPWD        => SMS_USERNAME . ':' . SMS_PASSWORD,
    ]);

    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success'     => false,
            'message'     => 'cURL error: ' . $curl_err,
            'gateway_ref' => null,
            'error'       => $curl_err,
        ];
    }

    $response_text = (string) $response;

    // Check for submitresult with result="1" (success)
    preg_match('/<submitresult[^>]*result="(\d+)"[^>]*\/>/i',  $response_text, $result_match);
    preg_match('/<submitresult[^>]*key="([^"]+)"[^>]*\/>/i',   $response_text, $key_match);
    preg_match('/<submitresult[^>]*action="([^"]+)"[^>]*\/>/i', $response_text, $action_match);
    preg_match('/<submitresult[^>]*error="(\d+)"[^>]*\/>/i',   $response_text, $error_match);

    $result     = $result_match[1]  ?? '0';
    $key        = $key_match[1]     ?? null;
    $action     = $action_match[1]  ?? '';
    $error_code = $error_match[1]   ?? '0';

    if ($result === '1' && $error_code === '0') {
        return [
            'success'     => true,
            'message'     => 'SMS ' . $action . ' successfully',
            'gateway_ref' => $key,
            'error'       => null,
        ];
    }

    // Check for error description
    preg_match('/<error[^>]*description="([^"]+)"[^>]*\/>/i', $response_text, $err_desc_match);
    $gateway_msg = $err_desc_match[1] ?? "Gateway error (result={$result}, error={$error_code})";

    return [
        'success'     => false,
        'message'     => 'Gateway error: ' . $gateway_msg,
        'gateway_ref' => null,
        'error'       => $gateway_msg,
    ];
}
