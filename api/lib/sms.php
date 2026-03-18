<?php
/**
 * KZN Liquor Indaba 2026 — SMS Library
 *
 * Provides send_umsg_sms() for use by send_sms.php and notify.php.
 */

if (!defined('SMS_GATEWAY_URL')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Normalise a South African mobile number to international format (+27…).
 * Accepts: 0821234567 / +27821234567 / 27821234567
 */
function normalise_za_number(string $number): string {
    $number = preg_replace('/\s+/', '', $number);
    if (str_starts_with($number, '0') && strlen($number) === 10) {
        return '+27' . substr($number, 1);
    }
    if (str_starts_with($number, '27') && strlen($number) === 11) {
        return '+' . $number;
    }
    return $number;
}

/**
 * Send an SMS through the UMSG XML Gateway.
 *
 * @param  string $to      Recipient phone number
 * @param  string $message SMS body
 * @return array{ success: bool, message: string, gateway_ref: string|null, error: string|null }
 */
function send_umsg_sms(string $to, string $message): array {
    $destination = normalise_za_number($to);

    $xml = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<sms>' .
            '<username>%s</username>' .
            '<password>%s</password>' .
            '<sender>%s</sender>' .
            '<destination>%s</destination>' .
            '<message>%s</message>' .
        '</sms>',
        htmlspecialchars(SMS_USERNAME,  ENT_XML1, 'UTF-8'),
        htmlspecialchars(SMS_PASSWORD,  ENT_XML1, 'UTF-8'),
        htmlspecialchars(SMS_SENDER,    ENT_XML1, 'UTF-8'),
        htmlspecialchars($destination,  ENT_XML1, 'UTF-8'),
        htmlspecialchars($message,      ENT_XML1, 'UTF-8')
    );

    $ch = curl_init(SMS_GATEWAY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=UTF-8'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success'     => false,
            'message'     => 'cURL error: ' . $curl_err,
            'gateway_ref' => null,
            'error'       => $curl_err,
        ];
    }

    try {
        $xml_response = new SimpleXMLElement((string) $response);
    } catch (Exception $e) {
        return [
            'success'     => false,
            'message'     => 'Invalid gateway response',
            'gateway_ref' => null,
            'error'       => (string) $response,
        ];
    }

    $status = strtoupper((string) ($xml_response->status ?? $xml_response->Status ?? ''));
    $ref    = (string) ($xml_response->msgid ?? $xml_response->reference ?? '');

    if (in_array($status, ['OK', 'ACCEPTED', '0', 'SUCCESS'], true)) {
        return [
            'success'     => true,
            'message'     => 'SMS sent successfully',
            'gateway_ref' => $ref ?: null,
            'error'       => null,
        ];
    }

    $gateway_msg = (string) ($xml_response->error ?? $xml_response->description ?? $status);
    return [
        'success'     => false,
        'message'     => 'Gateway error: ' . $gateway_msg,
        'gateway_ref' => null,
        'error'       => $gateway_msg,
    ];
}
