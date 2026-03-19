<?php
/**
 * KZN Liquor Indaba 2026 — EWS (Exchange Web Services) Library
 *
 * Provides send_ews_email() for sending email through an Exchange server
 * via EWS SOAP over HTTPS with Basic or NTLM authentication.
 *
 * No external PHP libraries required — uses only cURL.
 */

if (!defined('EWS_ENDPOINT')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Send an email via Exchange Web Services (EWS) using the CreateItem operation.
 *
 * @param  string $to          Recipient address
 * @param  string $toName      Recipient display name (may be empty)
 * @param  string $subject     Email subject
 * @param  string $html        HTML body (may be empty)
 * @param  string $text        Plain-text body
 * @param  array  $attachments Array of { filename, content (base64), mime }
 * @return array{ success: bool, message: string, error: string|null }
 */
function send_ews_email(
    string $to,
    string $toName,
    string $subject,
    string $html,
    string $text,
    array  $attachments
): array {
    $endpoint = EWS_ENDPOINT;
    $username = EWS_USERNAME;
    $password = EWS_PASSWORD;
    $version  = EWS_VERSION;

    if (empty($password)) {
        return [
            'success' => false,
            'message' => 'EWS password is not configured (set EWS_PASSWORD environment variable)',
            'error'   => 'EWS_PASSWORD not set',
        ];
    }

    // Build the body — prefer HTML, fall back to plain text
    $bodyType    = $html ? 'HTML' : 'Text';
    $bodyContent = $html ?: $text;

    // XML-encode all user-supplied strings to prevent SOAP injection
    $safeSubject = ews_xml($subject);
    $safeBody    = ews_xml($bodyContent);
    $safeTo      = ews_xml($to);
    $safeToName  = ews_xml($toName ?: $to);

    // Build attachment XML block (inline/base64)
    $attachmentsXml = '';
    if (!empty($attachments)) {
        $attachmentsXml .= '<m:Attachments>';
        foreach ($attachments as $att) {
            $filename = ews_xml($att['filename'] ?? 'attachment');
            $content  = $att['content'] ?? '';   // already base64
            $mime     = ews_xml($att['mime'] ?? 'application/octet-stream');
            $attachmentsXml .= <<<XML

            <t:FileAttachment>
              <t:Name>{$filename}</t:Name>
              <t:ContentType>{$mime}</t:ContentType>
              <t:IsInline>false</t:IsInline>
              <t:Content>{$content}</t:Content>
            </t:FileAttachment>
XML;
        }
        $attachmentsXml .= "\n          </m:Attachments>";
    }

    $soap = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types"
    xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">
  <soap:Header>
    <t:RequestServerVersion Version="{$version}" />
  </soap:Header>
  <soap:Body>
    <m:CreateItem MessageDisposition="SendAndSaveCopy">
      <m:SavedItemFolderId>
        <t:DistinguishedFolderId Id="sentitems" />
      </m:SavedItemFolderId>
      <m:Items>
        <t:Message>
          <t:Subject>{$safeSubject}</t:Subject>
          <t:Body BodyType="{$bodyType}">{$safeBody}</t:Body>
          <t:ToRecipients>
            <t:Mailbox>
              <t:EmailAddress>{$safeTo}</t:EmailAddress>
              <t:Name>{$safeToName}</t:Name>
            </t:Mailbox>
          </t:ToRecipients>{$attachmentsXml}
        </t:Message>
      </m:Items>
    </m:CreateItem>
  </soap:Body>
</soap:Envelope>
XML;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $soap,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://schemas.microsoft.com/exchange/services/2006/messages/CreateItem"',
        ],
        // Try NTLM first, then Basic
        CURLOPT_HTTPAUTH       => CURLAUTH_NTLM | CURLAUTH_BASIC,
        CURLOPT_USERPWD        => "$username:$password",
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr) {
        return [
            'success' => false,
            'message' => "EWS request failed: $curlErr",
            'error'   => $curlErr,
        ];
    }

    if ($httpCode === 401) {
        return [
            'success' => false,
            'message' => 'EWS authentication failed (401 Unauthorized). Check EWS_USERNAME and EWS_PASSWORD.',
            'error'   => 'HTTP 401',
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'message' => "EWS server returned HTTP $httpCode",
            'error'   => "HTTP $httpCode",
        ];
    }

    // Parse the SOAP response
    return ews_parse_response($response);
}

/**
 * Test connectivity to the EWS endpoint without sending any email.
 *
 * @return array{ reachable: bool, http_code: int, error: string|null }
 */
function ews_test_connectivity(): array
{
    $ch = curl_init(EWS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // 200, 401 and 405 all mean the server is reachable
    $reachable = !$curlErr && in_array($httpCode, [200, 401, 405, 500], true);

    return [
        'reachable' => $reachable,
        'http_code' => $httpCode,
        'error'     => $curlErr ?: null,
    ];
}

// ── Internal helpers ──────────────────────────────────────────────────────────

/**
 * Escape a string for safe inclusion in an XML element value.
 * Prevents SOAP/XML injection from user-supplied data.
 */
function ews_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Parse the EWS SOAP response and map error codes to friendly messages.
 *
 * @return array{ success: bool, message: string, error: string|null }
 */
function ews_parse_response(string $xml): array
{
    // Suppress XML warnings; handle them manually
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml)) {
        return [
            'success' => false,
            'message' => 'EWS returned invalid XML',
            'error'   => 'Invalid XML response',
        ];
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('m', 'http://schemas.microsoft.com/exchange/services/2006/messages');
    $xpath->registerNamespace('t', 'http://schemas.microsoft.com/exchange/services/2006/types');

    // Check for a ResponseCode element
    $codeNodes = $xpath->query('//m:ResponseCode | //t:ResponseCode');
    if ($codeNodes && $codeNodes->length > 0) {
        $responseCode = trim($codeNodes->item(0)->textContent);
        if ($responseCode === 'NoError') {
            return ['success' => true, 'message' => 'Email sent successfully via EWS', 'error' => null];
        }
        $friendly = ews_friendly_error($responseCode);
        return [
            'success' => false,
            'message' => $friendly,
            'error'   => $responseCode,
        ];
    }

    // No ResponseCode — check for a SOAP Fault
    $faultNodes = $xpath->query('//soap:Fault/faultstring', $dom->documentElement);
    if ($faultNodes && $faultNodes->length > 0) {
        $fault = trim($faultNodes->item(0)->textContent);
        return [
            'success' => false,
            'message' => "EWS SOAP Fault: $fault",
            'error'   => $fault,
        ];
    }

    return [
        'success' => false,
        'message' => 'Unexpected EWS response — no ResponseCode found',
        'error'   => 'Unknown EWS response',
    ];
}

/**
 * Map EWS error codes to user-friendly messages.
 */
function ews_friendly_error(string $code): string
{
    $map = [
        'ErrorAccessDenied'           => 'EWS access denied. Check the account has permission to send mail.',
        'ErrorMailboxMoveInProgress'  => 'The mailbox is currently being moved. Please try again in a few minutes.',
        'ErrorInvalidLicense'         => 'Exchange licence issue. Contact your IT administrator.',
        'ErrorItemNotFound'           => 'One or more recipient addresses are invalid.',
        'ErrorServerBusy'             => 'Exchange server is busy. Please try again shortly.',
        'ErrorInvalidRecipients'      => 'One or more recipient addresses are invalid.',
        'ErrorSendAsDenied'           => 'The account does not have Send As permission for this address.',
        'ErrorQuotaExceeded'          => 'Mailbox quota exceeded. Contact your IT administrator.',
        'ErrorMessageSizeExceeded'    => 'The email message is too large to send.',
        'ErrorInvalidEmailAddress'    => 'An email address in the request is not valid.',
    ];

    return $map[$code] ?? "EWS error: $code";
}
