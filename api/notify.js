/**
 * KZN Liquor Indaba 2026 — Unified Notification Endpoint (Vercel Serverless)
 * 
 * Accepts a list of recipients and sends email, SMS, or both according to
 * each delegate's communication preference or an explicit channel override.
 */

import { sendUmsgSms } from './_lib/sms.js';
import { sendSmtpEmail, buildEmailHtml } from './_lib/email.js';
import { setCorsHeaders, validateEmail, validatePhone, applyTemplateVars, checkRateLimit, logCommunication } from './_lib/helpers.js';

const MAX_ATTACHMENT_FILES = 5;
const MAX_ATTACHMENT_SIZE_BYTES = 5 * 1024 * 1024;
const MAX_ATTACHMENT_TOTAL_BYTES = 10 * 1024 * 1024;
const ALLOWED_ATTACHMENT_MIMES = new Set([
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/jpeg',
  'image/png',
  'image/gif',
  'text/plain',
  'text/csv'
]);
const EXTENSION_TO_MIME = {
  pdf: 'application/pdf',
  doc: 'application/msword',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xls: 'application/vnd.ms-excel',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  png: 'image/png',
  gif: 'image/gif',
  txt: 'text/plain',
  csv: 'text/csv'
};

function getFileExtension(filename = '') {
  const parts = String(filename).toLowerCase().split('.');
  return parts.length > 1 ? parts.pop() : '';
}

function normalizeAndValidateAttachments(attachments) {
  if (!attachments) return { ok: true, attachments: [] };
  if (!Array.isArray(attachments)) {
    return { ok: false, message: 'attachments must be an array' };
  }
  if (attachments.length > MAX_ATTACHMENT_FILES) {
    return { ok: false, message: `Maximum ${MAX_ATTACHMENT_FILES} attachments allowed` };
  }

  let totalBytes = 0;
  const normalized = [];
  for (const att of attachments) {
    if (!att || typeof att !== 'object') {
      return { ok: false, message: 'Each attachment must be an object' };
    }
    const rawFilename = String(att.filename || '').replace(/\0/g, '').trim();
    const pathParts = rawFilename.split(/[/\\]/).filter(Boolean);
    const filename = pathParts[pathParts.length - 1] || '';
    const content = String(att.content || '').trim();
    const inputMime = String(att.mime || '').trim().toLowerCase();
    if (!filename || !content) {
      return { ok: false, message: 'Each attachment must include filename and content' };
    }

    const extension = getFileExtension(filename);
    const inferredMime = EXTENSION_TO_MIME[extension] || '';
    const mime = (ALLOWED_ATTACHMENT_MIMES.has(inputMime) ? inputMime : '')
      || inferredMime
      || inputMime
      || 'application/octet-stream';

    if (!ALLOWED_ATTACHMENT_MIMES.has(mime)) {
      return { ok: false, message: `Attachment type not allowed: ${filename}` };
    }

    const sanitizedContent = content.replace(/\s/g, '');
    // Client uploads are expected to use FileReader data URLs, which produce standard base64.
    // URL-safe base64 variants are intentionally rejected here.
    if (!/^[A-Za-z0-9+/=]+$/.test(sanitizedContent)) {
      return { ok: false, message: `Attachment content is not valid base64: ${filename}` };
    }
    const decoded = Buffer.from(sanitizedContent, 'base64');
    const decodedSize = decoded.byteLength;
    const declaredSize = Number(att.size);
    if (Number.isFinite(declaredSize) && declaredSize > 0 && declaredSize !== decodedSize) {
      return { ok: false, message: `Attachment size metadata mismatch: ${filename}` };
    }
    const size = decodedSize;
    if (size > MAX_ATTACHMENT_SIZE_BYTES) {
      return { ok: false, message: `${filename} exceeds 5MB per-file limit` };
    }
    totalBytes += size;
    if (totalBytes > MAX_ATTACHMENT_TOTAL_BYTES) {
      return { ok: false, message: 'Total attachment size exceeds 10MB limit' };
    }
    normalized.push({ filename, content: sanitizedContent, mime, size });
  }

  return { ok: true, attachments: normalized };
}

export default async function handler(req, res) {
  try {
  // Only allow POST requests
  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Method not allowed' });
  }

  // Set CORS headers
  setCorsHeaders(req, res);

  // Handle OPTIONS preflight
  if (req.method === 'OPTIONS') {
    return res.status(204).end();
  }

  const { notifType, label, subject, message, recipients, attachments, sentBy } = req.body || {};

  // Validate required fields
  if (!message || !message.trim()) {
    return res.status(400).json({ success: false, message: 'message is required' });
  }

  if (!recipients || !Array.isArray(recipients) || recipients.length === 0) {
    return res.status(400).json({ success: false, message: 'recipients must be a non-empty array' });
  }

  const attachmentValidation = normalizeAndValidateAttachments(attachments);
  if (!attachmentValidation.ok) {
    return res.status(422).json({ success: false, message: attachmentValidation.message });
  }
  const normalizedAttachments = attachmentValidation.attachments;

  // Check rate limit
  if (!checkRateLimit()) {
    return res.status(429).json({ success: false, message: 'Rate limit exceeded. Try again shortly.' });
  }

  let sent = 0;
  let failed = 0;
  const errors = [];

  // Process each recipient
  for (const delegate of recipients) {
    const firstName = delegate.firstName || '';
    const lastName = delegate.lastName || '';
    const fullName = delegate.fullName || `${firstName} ${lastName}`.trim();
    const email = delegate.email || '';
    const mobile = delegate.mobile || '';
    const commPref = delegate.commPref || 'both';
    const ref = delegate.ref || '';

    // Template variables
    const vars = {
      firstName,
      lastName,
      fullName,
      referenceNumber: ref,
      eventDate: 'Friday 8 May – Saturday 9 May 2026',
      eventTime: '08:00',
      venue: 'Durban, KwaZulu-Natal'
    };

    const personalisedMessage = applyTemplateVars(message, vars);
    const personalisedSubject = applyTemplateVars(subject || 'KZN Liquor Indaba 2026', vars);

    // Determine which channels to use
    let useEmail = false;
    let useSms = false;

    if (notifType === 'email') {
      useEmail = true;
    } else if (notifType === 'sms') {
      useSms = true;
    } else if (notifType === 'both') {
      useEmail = true;
      useSms = true;
    } else {
      // Honor delegate's own preference
      if (commPref === 'both') {
        useEmail = true;
        useSms = true;
      } else if (commPref === 'sms') {
        useSms = true;
      } else {
        useEmail = true; // default to email
      }
    }

    // Send Email
    if (useEmail && email && validateEmail(email)) {
      const htmlBody = buildEmailHtml(personalisedSubject, firstName, personalisedMessage);
      const textBody = personalisedMessage.replace(/<[^>]*>/g, '').replace(/\n\n+/g, '\n\n');

      const emailResult = await sendSmtpEmail(
        email,
        fullName,
        personalisedSubject,
        htmlBody,
        textBody,
        normalizedAttachments
      );

      logCommunication({
        type: 'email',
        to: email,
        ref,
        label: label || 'Notification',
        subject: personalisedSubject,
        success: emailResult.success,
        sentBy: sentBy || 'System',
        error: emailResult.error || null
      });

      if (emailResult.success) {
        sent++;
      } else {
        failed++;
        errors.push({ recipient: email, error: emailResult.message });
      }
    }

    // Send SMS
    if (useSms && mobile && validatePhone(mobile)) {
      // Shorten message for SMS - keep only essential info
      let smsBody = personalisedMessage
        .replace(/<[^>]*>/g, '')  // Remove HTML tags
        .replace(/\s+/g, ' ')      // Collapse whitespace
        .trim();

      // If too long, keep first sentence + ref number
      if (smsBody.length > 160) {
        const refText = ref ? ` Ref: ${ref}` : '';
        const maxLen = 160 - refText.length - 3; // Reserve space for "..."
        smsBody = smsBody.substring(0, maxLen).trim() + '...' + refText;
      }

      const smsText = smsBody;
      const smsResult = await sendUmsgSms(mobile, smsText);

      logCommunication({
        type: 'sms',
        to: mobile,
        ref,
        label: label || 'Notification',
        message: smsText,
        success: smsResult.success,
        gateway_ref: smsResult.gateway_ref || null,
        sentBy: sentBy || 'System',
        error: smsResult.error || null
      });

      if (smsResult.success) {
        sent++;
      } else {
        failed++;
        errors.push({ recipient: mobile, error: smsResult.message });
      }
    }
  }

  return res.status(200).json({
    success: failed === 0,
    sent,
    failed,
    errors
  });

  } catch (error) {
    console.error('Notification handler error:', error);
    return res.status(500).json({
      success: false,
      message: 'A server error has occurred',
      error: error.message || 'Unknown error'
    });
  }
}
