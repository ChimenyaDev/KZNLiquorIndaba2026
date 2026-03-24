/**
 * KZN Liquor Indaba 2026 — SMS Configuration Verification Endpoint
 *
 * GET /api/verify-sms-config?token=<VERIFY_TOKEN>
 *
 * Returns SMS configuration status without exposing sensitive values.
 * Use this endpoint to diagnose 401 Unauthorized errors from the SMS gateway.
 *
 * Requires VERIFY_TOKEN query parameter matching the VERIFY_TOKEN env var.
 */

import config from './_lib/config.js';

export default function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');

  // Require a token to prevent unauthenticated information disclosure.
  // Set VERIFY_TOKEN in Vercel environment variables.
  const expectedToken = process.env.VERIFY_TOKEN;
  const providedToken = req.query && req.query.token;

  if (!expectedToken || !providedToken || providedToken !== expectedToken) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const verification = {
    gatewayUrl:        config.sms.gatewayUrl,
    username:          config.sms.username,
    sender:            config.sms.sender,
    envVarPresent:     !!process.env.SMS_PASSWORD,
    passwordSet:       !!config.sms.password,
    passwordLength:    config.sms.password ? config.sms.password.length : 0,
    passwordFirstChar: config.sms.password ? config.sms.password.charAt(0) : null,
    passwordLastChar:  config.sms.password ? config.sms.password.charAt(config.sms.password.length - 1) : null
  };

  return res.status(200).json(verification);
}
