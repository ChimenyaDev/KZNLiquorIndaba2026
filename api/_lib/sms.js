/**
 * KZN Liquor Indaba 2026 — SMS Library (Node.js / Vercel)
 * 
 * Sends SMS via UMSG gateway using GET with query parameters
 * https://sms01.umsg.co.za/xml/send/?number1=...&message1=...
 */

import axios from 'axios';
import config, { validateSmsConfig } from './config.js';

/**
 * Normalize a South African mobile number to format required by UMSG.
 * UMSG accepts: 27XXXXXXXXX (11 digits, no + prefix)
 * 
 * Accepts: 0821234567 / +27821234567 / 27821234567 / 082 123 4567
 * Returns: 27821234567 (11 digits, no + prefix)
 */
function normalizeZaNumber(number) {
  // Remove ALL non-numeric characters
  const cleaned = String(number).replace(/[^\d]/g, '');
  
  // If starts with 0 and is 10 digits (local SA format)
  if (cleaned.startsWith('0') && cleaned.length === 10) {
    return '27' + cleaned.substring(1);
  }
  
  // If starts with 27 and is 11 digits (already in international format)
  if (cleaned.startsWith('27') && cleaned.length === 11) {
    return cleaned;
  }
  
  // Return cleaned number as-is
  return cleaned;
}

/**
 * Send an SMS through the UMSG Gateway using GET with query parameters.
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

  // Build GET request URL with query parameters
  // Format: https://sms01.umsg.co.za/xml/send/?number1=27821234567&message1=Your+message
  const url = new URL(config.sms.gatewayUrl);
  url.searchParams.append('number1', destination);
  url.searchParams.append('message1', message);

  console.log('Sending SMS via UMSG GET:', {
    destination,
    messageLength: message.length,
    url: url.toString().replace(/message1=[^&]*/, 'message1=***')
  });

  try {
    // Use HTTP Basic Auth
    const credentials = Buffer.from(`${config.sms.username}:${config.sms.password}`).toString('base64');
    
    const response = await axios.get(url.toString(), {
      headers: {
        'Authorization': `Basic ${credentials}`
      },
      timeout: 15000,
      validateStatus: function (status) {
        return true; // Don't throw on any status
      }
    });

    console.log('UMSG Response Status:', response.status);
    console.log('UMSG Response Data:', response.data);

    // Handle 401
    if (response.status === 401) {
      console.error('❌ 401 Unauthorized - Credentials rejected');
      return {
        success: false,
        message: 'SMS gateway authentication failed (401)',
        gateway_ref: null,
        error: '401 Unauthorized'
      };
    }

    // Parse XML response
    const responseText = typeof response.data === 'string' ? response.data : String(response.data);
    
    // Check for submitresult with result="1" (success)
    const submitMatch = responseText.match(/<submitresult[^>]*result="(\d+)"[^>]*\/>/i);
    const keyMatch = responseText.match(/<submitresult[^>]*key="([^"]+)"[^>]*\/>/i);
    const actionMatch = responseText.match(/<submitresult[^>]*action="([^"]+)"[^>]*\/>/i);
    const errorMatch = responseText.match(/<submitresult[^>]*error="(\d+)"[^>]*\/>/i);

    const result = submitMatch ? submitMatch[1] : '0';
    const key = keyMatch ? keyMatch[1] : null;
    const action = actionMatch ? actionMatch[1] : '';
    const errorCode = errorMatch ? errorMatch[1] : '0';

    if (result === '1' && errorCode === '0') {
      return {
        success: true,
        message: `SMS ${action} successfully`,
        gateway_ref: key,
        error: null
      };
    }

    // Check for error description
    const errorDescMatch = responseText.match(/<error[^>]*description="([^"]+)"[^>]*\/>/i);
    const errorMsg = errorDescMatch ? errorDescMatch[1] : `Gateway error (result=${result}, error=${errorCode})`;

    return {
      success: false,
      message: `Gateway error: ${errorMsg}`,
      gateway_ref: null,
      error: errorMsg
    };

  } catch (error) {
    console.error('UMSG SMS Error:', {
      message: error.message,
      code: error.code,
      status: error.response?.status
    });

    return {
      success: false,
      message: `Request error: ${error.message}`,
      gateway_ref: null,
      error: error.message
    };
  }
}