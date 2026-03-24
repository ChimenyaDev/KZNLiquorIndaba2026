/**
 * KZN Liquor Indaba 2026 — Email Library (Node.js / Vercel)
 *
 * Provides sendSmtpEmail() and buildEmailHtml() for use by notify.js.
 */

import nodemailer from 'nodemailer';
import config from './config.js';

/**
 * Send an email via SMTP using nodemailer.
 *
 * @param {string} to          - Recipient address
 * @param {string} toName      - Recipient display name (may be empty)
 * @param {string} subject     - Email subject
 * @param {string} html        - HTML body (may be empty)
 * @param {string} text        - Plain-text fallback
 * @param {Array}  attachments - Array of { filename, content (base64), mime }
 * @returns {Promise<{success: boolean, message: string, error: string|null}>}
 */
export async function sendSmtpEmail(to, toName, subject, html, text, attachments = []) {
  const { host, port, username, password, fromEmail, fromName, encryption } = config.smtp;

  const transporter = nodemailer.createTransport({
    host,
    port,
    secure: encryption === 'ssl',
    requireTLS: encryption === 'tls',
    auth: {
      user: username,
      pass: password
    },
    connectionTimeout: 10000,  // 10 seconds — fail fast on unreachable hosts
    greetingTimeout:   10000,  // 10 seconds — time allowed for SMTP greeting
    socketTimeout:     15000,  // 15 seconds — idle socket timeout
    pool:              true,   // reuse connections across calls
    maxConnections:    5,
    maxMessages:       100,
    tls: {
      rejectUnauthorized: false // permit self-signed certs on the mail server;
                                // remove this once a valid CA-signed cert is installed
    }
  });

  const mailOptions = {
    from:    fromName ? `"${fromName}" <${fromEmail}>` : fromEmail,
    to:      toName   ? `"${toName}" <${to}>`          : to,
    subject,
    html:    html  || undefined,
    text:    text  || undefined,
    attachments: attachments.map(att => ({
      filename:    att.filename,
      content:     att.content,
      encoding:    'base64',
      contentType: att.mime || 'application/octet-stream'
    }))
  };

  try {
    await transporter.sendMail(mailOptions);
    return { success: true, message: 'Email sent successfully', error: null };
  } catch (error) {
    return { success: false, message: `SMTP error: ${error.message}`, error: error.message };
  }
}

/**
 * Wrap a notification message in a branded HTML email template.
 *
 * @param {string} subject   - Email subject
 * @param {string} firstName - Recipient first name
 * @param {string} message   - Message body (may contain HTML)
 * @returns {string} Complete HTML email
 */
export function buildEmailHtml(subject, firstName, message) {
  const safeSubject  = escapeHtml(subject);
  const safeFirst    = escapeHtml(firstName);
  const safeMessage  = message.includes('<') ? message : escapeHtml(message).replace(/\n/g, '<br>');

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>${safeSubject}</title>
  <style>
    body { margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif; }
    .wrapper { max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;overflow:hidden; }
    .header  { background:#1a3560;color:#ffffff;padding:28px 32px; }
    .header h1 { margin:0;font-size:1.3rem;font-weight:700; }
    .header p  { margin:4px 0 0;font-size:0.85rem;opacity:0.85; }
    .body    { padding:30px 32px;color:#2d3748;font-size:0.95rem;line-height:1.7; }
    .footer  { padding:18px 32px;background:#f4f5f7;color:#718096;font-size:0.78rem;border-top:1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>KZN Liquor Indaba 2026</h1>
    <p>KwaZulu-Natal Economic Regulatory Authority</p>
  </div>
  <div class="body">
    <p>Dear ${safeFirst},</p>
    <p>${safeMessage}</p>
    <p style="margin-top:28px;font-size:0.85rem;color:#718096;">
      For enquiries contact <a href="mailto:indaba@kznera.co.za" style="color:#1a3560;">indaba@kznera.co.za</a>
    </p>
  </div>
  <div class="footer">
    <p>KZN Liquor Indaba 2026 &nbsp;|&nbsp; Friday 8 May \u2013 Saturday 9 May 2026 &nbsp;|&nbsp; Durban, KwaZulu-Natal</p>
    <p>Personal information handled in accordance with POPIA (Act 4 of 2013).</p>
  </div>
</div>
</body>
</html>`;
}

/**
 * Escape special HTML characters to prevent XSS in email body.
 */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
