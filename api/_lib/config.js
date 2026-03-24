/**
 * KZN Liquor Indaba 2026 — Configuration (Node.js / Vercel)
 *
 * Reads credentials from environment variables.
 * Set these in Vercel dashboard → Settings → Environment Variables
 */

export default {
  // SMS Gateway (UMSG)
  sms: {
    gatewayUrl: process.env.SMS_GATEWAY_URL || 'https://sms01.umsg.co.za/xml/send',
    username:   process.env.SMS_USERNAME    || 'kzn_liquor_sa',
    password:   process.env.SMS_PASSWORD    || '',
    sender:     process.env.SMS_SENDER      || 'KZNIndaba'
  },

  // Email (Resend - Primary)
  resend: {
    apiKey:    process.env.RESEND_API_KEY    || '',
    fromEmail: process.env.RESEND_FROM_EMAIL || 'nto.vinkhumbo@kznera.org.za',
    fromName:  process.env.RESEND_FROM_NAME  || 'KZN Liquor Indaba 2026'
  },

  // Email (SMTP - Fallback)
  smtp: {
    host:       process.env.SMTP_HOST       || 'mail.kznera.org.za',
    port:       parseInt(process.env.SMTP_PORT || '587', 10),
    username:   process.env.SMTP_USERNAME   || 'nto.vinkhumbo@kznera.org.za',
    password:   process.env.SMTP_PASSWORD   || '',
    fromEmail:  process.env.SMTP_FROM_EMAIL || 'nto.vinkhumbo@kznera.org.za',
    fromName:   process.env.SMTP_FROM_NAME  || 'KZN Liquor Indaba 2026',
    encryption: process.env.SMTP_ENCRYPTION || 'tls' // 'tls' or 'ssl'
  },

  // CORS
  allowedOrigins: [
    'http://localhost',
    'http://localhost:8080',
    'https://kznliquorindaba2026.azurewebsites.net',
    'https://kzn-liquor-indaba2026.vercel.app'
  ],

  // Rate limiting
  rateLimitPerMinute: parseInt(process.env.RATE_LIMIT_PER_MINUTE || '30', 10)
};

/**
 * Validate that required SMS credentials are present.
 * @returns {boolean} true if SMS is properly configured
 */
export function validateSmsConfig() {
  if (!process.env.SMS_PASSWORD) {
    console.warn('⚠️  SMS_PASSWORD environment variable is not set');
    return false;
  }
  return true;
}
