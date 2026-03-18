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

  // Email (SMTP)
  smtp: {
    host:      process.env.SMTP_HOST       || 'mail.kznera.org.za',
    port:      parseInt(process.env.SMTP_PORT || '587', 10),
    username:  process.env.SMTP_USERNAME   || 'nto.vinkhumbo@kznera.org.za',
    password:  process.env.SMTP_PASSWORD   || '',
    fromEmail: process.env.SMTP_FROM_EMAIL || 'nto.vinkhumbo@kznera.org.za',
    fromName:  process.env.SMTP_FROM_NAME  || 'KZN Liquor Indaba 2026',
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
