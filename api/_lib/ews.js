/**
 * KZN Liquor Indaba 2026 — Exchange Web Services (EWS) Email Client
 * 
 * Sends emails via Exchange Web Services using HTTPS (port 443).
 * This bypasses SMTP port restrictions and works with Exchange/OWA servers.
 */

/**
 * Send an email via Exchange Web Services (EWS)
 * 
 * @param {string} to - Recipient email address
 * @param {string} toName - Recipient display name (optional)
 * @param {string} subject - Email subject
 * @param {string} htmlBody - HTML email body
 * @param {string} textBody - Plain text fallback
 * @returns {Promise<{success: boolean, message: string, error: string|null}>}
 */
export async function sendEwsEmail(to, toName, subject, htmlBody, textBody) {
  const endpoint = process.env.EWS_ENDPOINT || 'https://mail.kznera.org.za/EWS/Exchange.asmx';
  const username = process.env.EWS_USERNAME || process.env.SMTP_USERNAME || 'nto.vinkhumbo@kznera.org.za';
  const password = process.env.EWS_PASSWORD || process.env.SMTP_PASSWORD || '';
  const domain = process.env.EWS_DOMAIN || 'KZNERA';
  const fromEmail = process.env.EWS_FROM_EMAIL || process.env.SMTP_FROM_EMAIL || username;
  const fromName = process.env.EWS_FROM_NAME || process.env.SMTP_FROM_NAME || 'KZN Liquor Indaba 2026';

  if (!password) {
    return {
      success: false,
      message: 'EWS_PASSWORD or SMTP_PASSWORD not configured',
      error: 'Missing password'
    };
  }

  // Build EWS SOAP request
  const soapEnvelope = buildEwsSoapEnvelope(to, toName, subject, htmlBody, textBody, fromEmail, fromName);

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'text/xml; charset=utf-8',
        'Authorization': 'Basic ' + Buffer.from(`${domain}+\${username}:${password}`).toString('base64')
      },
      body: soapEnvelope
    });

    const responseText = await response.text();

    // Check for successful response
    if (response.ok && responseText.includes('NoError')) {
      return {
        success: true,
        message: 'Email sent successfully via EWS',
        error: null
      };
    }

    // Parse error from SOAP response
    const errorMatch = responseText.match(/<m:ResponseCode>(.*?)<\/m:ResponseCode>/);
    const errorCode = errorMatch ? errorMatch[1] : 'Unknown';
    
    const messageMatch = responseText.match(/<m:MessageText>(.*?)<\/m:MessageText>/);
    const errorMessage = messageMatch ? messageMatch[1] : 'No error message';

    return {
      success: false,
      message: `EWS Error: ${errorCode} - ${errorMessage}`,
      error: `${errorCode}: ${errorMessage}`
    };

  } catch (error) {
    return {
      success: false,
      message: `EWS connection failed: ${error.message}`,
      error: error.message
    };
  }
}

/**
 * Build EWS SOAP envelope for sending email
 */
function buildEwsSoapEnvelope(to, toName, subject, htmlBody, textBody, fromEmail, fromName) {
  // Escape XML special characters
  const escapeXml = (str) => {
    return (str || '').replace(/[<>&'"\g, (c) => {
      switch (c) {
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '&': return '&amp;';
        case "'": return '&apos;';
        case '"': return '&quot;';
        default: return c;
      }
    });
  };

  const safeSubject = escapeXml(subject);
  const safeHtmlBody = escapeXml(htmlBody);
  const safeTextBody = escapeXml(textBody);
  const safeToEmail = escapeXml(to);
  const safeToName = escapeXml(toName || to);
  const safeFromEmail = escapeXml(fromEmail);
  const safeFromName = escapeXml(fromName);

  return `<?xml version="1.0" encoding="utf-8"?>\n<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"\n               xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types"\n               xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">\n  <soap:Header>\n    <t:RequestServerVersion Version="Exchange2013_SP1" />\n  </soap:Header>\n  <soap:Body>\n    <m:CreateItem MessageDisposition="SendAndSaveCopy">\n      <m:Items>\n        <t:Message>\n          <t:Subject>${safeSubject}</t:Subject>\n          <t:Body BodyType="HTML">${safeHtmlBody}</t:Body>\n          <t:ToRecipients>\n            <t:Mailbox>\n              <t:Name>${safeToName}</t:Name>\n              <t:EmailAddress>${safeToEmail}</t:EmailAddress>\n            </t:Mailbox>\n          </t:ToRecipients>\n          <t:From>\n            <t:Mailbox>\n              <t:Name>${safeFromName}</t:Name>\n              <t:EmailAddress>${safeFromEmail}</t:EmailAddress>\n            </t:Mailbox>\n          </t:From>\n        </t:Message>\n      </m:Items>\n    </m:CreateItem>\n  </soap:Body>\n</soap:Envelope>`;
}

/**
 * Test EWS connectivity
 * 
 * @returns {Promise<{success: boolean, message: string, endpoint: string}>}
 */
export async function testEwsConnection() {
  const endpoint = process.env.EWS_ENDPOINT || 'https://mail.kznera.org.za/EWS/Exchange.asmx';
  
  try {
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: {
        'User-Agent': 'KZN-Liquor-Indaba/1.0'
      }
    });

    if (response.ok || response.status === 401) {
      // 401 is actually good - means EWS is there but needs auth
      return {
        success: true,
        message: 'EWS endpoint is reachable',
        endpoint: endpoint
      };
    }

    return {
      success: false,
      message: `EWS endpoint returned status ${response.status}`,
      endpoint: endpoint
    };
  
  } catch (error) {
    return {
      success: false,
      message: `Cannot reach EWS endpoint: ${error.message}`,
      endpoint: endpoint
    };
  }
}