/**
 * KZN Liquor Indaba 2026 — Unified Notification Endpoint (Vercel Serverless)
 * 
 * Accepts a list of recipients and sends email, SMS, or both according to
 * each delegate's communication preference or an explicit channel override.
 */

import { sendUmsgSms } from './_lib/sms.js';
import { sendSmtpEmail, buildEmailHtml } from './_lib/email.js';
import { setCorsHeaders, validateEmail, validatePhone, applyTemplateVars, checkRateLimit, logCommunication } from './_lib/helpers.js';

export default async function handler(req, res) {
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
        attachments || []
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
      const smsText = personalisedMessage.replace(/<[^>]*>/g, '').substring(0, 918);
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
}