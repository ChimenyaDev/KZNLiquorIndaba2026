/**
 * KZN Liquor Indaba 2026 — SMS Library (Node.js / Vercel)
 * 
 * Sends SMS via UMSG XML gateway at https://sms01.umsg.co.za/xml/send
 */

import axios from 'axios';
import config, { validateSmsConfig } from './config.js';

/**
 * Normalize a South African mobile number to international format (+27…).
 * Accepts: 0821234567 / +27821234567 / 27821234567
 */
function normalizeZaNumber(number) {
  const cleaned = number.replace(/\s+/g, '');
  
  if (cleaned.startsWith('0') && cleaned.length === 10) {
    return '+27' + cleaned.substring(1);
  }
  
  if (cleaned.startsWith('27') && cleaned.length === 11) {
    return '+' + cleaned;
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

  // Log the request for debugging (without exposing password)
  console.log('Sending SMS via UMSG:', {
    gateway: config.sms.gatewayUrl,
    username: config.sms.username,
    sender: config.sms.sender,
    destination: destination,
    messageLength: message.length,
    passwordSet: config.sms.password ? 'Yes' : 'No'
  });

  // Build XML payload
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<sms>
  <username>${escapeXml(config.sms.username)}</username>
  <password>${escapeXml(config.sms.password)}</password>
  <sender>${escapeXml(config.sms.sender)}</sender>
  <destination>${escapeXml(destination)}</destination>
  <message>${escapeXml(message)}</message>
</sms>`;

  try {
    const response = await axios.post(config.sms.gatewayUrl, xml, {
      headers: { 'Content-Type': 'text/xml; charset=UTF-8' },
      timeout: 15000
    });

    // Parse XML response
    const responseText = response.data;
    console.log('UMSG Gateway Response:', responseText);

    const statusMatch = responseText.match(/<status>(.*?)<\/status>/i) || 
                        responseText.match(/<Status>(.*?)<\/Status>/i);
    const refMatch = responseText.match(/<msgid>(.*?)<\/msgid>/i) || 
                     responseText.match(/<reference>(.*?)<\/reference>/i);
    
    const status = (statusMatch ? statusMatch[1] : '').toUpperCase();
    const ref = refMatch ? refMatch[1] : null;

    if (['OK', 'ACCEPTED', '0', 'SUCCESS'].includes(status)) {
      return {
        success: true,
        message: 'SMS sent successfully',
        gateway_ref: ref,
        error: null
      };
    }

    const errorMatch = responseText.match(/<error>(.*?)<\/error>/i) || 
                       responseText.match(/<description>(.*?)<\/description>/i);
    const gatewayMsg = errorMatch ? errorMatch[1] : status;

    return {
      success: false,
      message: `Gateway error: ${gatewayMsg}`,
      gateway_ref: null,
      error: gatewayMsg
    };

  } catch (error) {
    // Enhanced error logging
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
 * Escape special characters for XML
 */
function escapeXml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}