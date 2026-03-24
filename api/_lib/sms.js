/**
 * KZN Liquor Indaba 2026 — SMS Library (Node.js / Vercel)
 * 
 * Sends SMS via UMSG XML gateway at https://sms01.umsg.co.za/xml/send
 */

import axios from 'axios';
import config, { validateSmsConfig } from './config.js';

/**
 * Normalize a South African mobile number to international format WITHOUT + prefix
 * (as required by UMSG gateway).
 *
 * Accepts: 0821234567 / +27821234567 / 27821234567 / 082 123 4567
 * Returns: 27821234567 (no + prefix)
 */
function normalizeZaNumber(number) {
  // Remove ALL non-numeric characters (spaces, dashes, parentheses, plus signs)
  const cleaned = String(number).replace(/[^\d]/g, '');

  // If starts with 0 and is 10 digits (local SA format)
  if (cleaned.startsWith('0') && cleaned.length === 10) {
    return '27' + cleaned.substring(1);
  }

  // If starts with 27 and is 11 digits (already in international format)
  if (cleaned.startsWith('27') && cleaned.length === 11) {
    return cleaned;
  }

  return cleaned;
}

/**
 * Send an SMS through the UMSG XML Gateway.
 * 
 * @param {string} to - Recipient phone number
 * @param {string} message - SMS body
 * @returns {Promise<{success: boolean, message: string, gateway_ref: string|null, error: string|null}>}
 */
export async function sendUmsgSms(to, message) {
  // Validate config first
  if (!validateSmsConfig()) {
    return {
      success: false,
      message: 'SMS service is not properly configured',
      gateway_ref: null,
      error: 'Missing SMS_PASSWORD environment variable'
    };
  }

  const destination = normalizeZaNumber(to);

  // Build XML payload
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<sms>
  <username>${escapeXml(config.sms.username)}</username>
  <password>${escapeXml(config.sms.password)}</password>
  <sender>${escapeXml(config.sms.sender)}</sender>
  <destination>${escapeXml(destination)}</destination>
  <message>${escapeXml(message)}</message>
</sms>`;

  // Log XML for debugging (with password masked)
  const xmlForLogging = xml.replace(
    /<password>.*?<\/password>/,
    `<password>${config.sms.password ? '***' + config.sms.password.slice(-2) : 'EMPTY'}</password>`
  );
  console.log('Sending SMS XML:', xmlForLogging);
  console.log('Password length being sent:', config.sms.password ? config.sms.password.length : 0);

  try {
    const credentials = Buffer.from(`${config.sms.username}:${config.sms.password}`).toString('base64');
    const response = await axios.post(config.sms.gatewayUrl, xml, {
      headers: {
        'Content-Type': 'text/xml; charset=UTF-8',
        'Authorization': `Basic ${credentials}`
      },
      timeout: 15000,
      validateStatus: function (status) {
        // Don't throw on any status, we'll handle it ourselves
        return true;
      }
    });

    console.log('UMSG Response Status:', response.status);
    console.log('UMSG Response Data:', response.data);

    // If we get 401, log more details
    if (response.status === 401) {
      console.error('❌ 401 Unauthorized - Credentials rejected by UMSG gateway');
      console.error('Gateway URL:', config.sms.gatewayUrl);
      console.error('Username being sent:', config.sms.username);
      console.error('Password set:', !!config.sms.password);
      console.error('Password length:', config.sms.password ? config.sms.password.length : 0);

      return {
        success: false,
        message: 'SMS gateway authentication failed (401). Please verify SMS_USERNAME and SMS_PASSWORD in Vercel environment variables.',
        gateway_ref: null,
        error: '401 Unauthorized - Invalid credentials'
      };
    }

    // Parse XML response for successful requests
    const responseText = typeof response.data === 'string' ? response.data : String(response.data);
    const statusMatch = responseText.match(/<status>(.*?)<\/status>/i) || 
                        responseText.match(/<Status>(.*?)<\/Status>/i);
    const refMatch = responseText.match(/<msgid>(.*?)<\/msgid>/i) || 
                     responseText.match(/<reference>(.*?)<\/reference>/i);
    
    const gatewayStatus = (statusMatch ? statusMatch[1] : '').toUpperCase();
    const ref = refMatch ? refMatch[1] : null;

    if (['OK', 'ACCEPTED', '0', 'SUCCESS'].includes(gatewayStatus)) {
      return {
        success: true,
        message: 'SMS sent successfully',
        gateway_ref: ref,
        error: null
      };
    }

    // Handle both attribute-style <error description="..."/> and text-style <error>...</error>
    const errorMatch = responseText.match(/<error[^>]*\bdescription="([^"]*)"/i) ||
                       responseText.match(/<error[^>]*>([^<]*)<\/error>/i) ||
                       responseText.match(/<description[^>]*>([^<]*)<\/description>/i);
    const gatewayMsg = errorMatch ? errorMatch[1] : gatewayStatus;

    return {
      success: false,
      message: `Gateway error: ${gatewayMsg}`,
      gateway_ref: null,
      error: gatewayMsg
    };

  } catch (error) {
    const errorDetails = {
      message: error.message,
      code: error.code,
      response: error.response?.data,
      status: error.response?.status,
      statusText: error.response?.statusText
    };

    console.error('UMSG SMS Error:', errorDetails);

    return {
      success: false,
      message: `Request error: ${error.message}`,
      gateway_ref: null,
      error: error.message
    };
  }
}

/**
 * Escape special characters for XML (including # and other special chars)
 */
function escapeXml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}