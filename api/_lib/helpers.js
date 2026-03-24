/**
 * KZN Liquor Indaba 2026 — Shared Helper Functions (Node.js / Vercel)
 *
 * Used by notify.js.
 */

import config from './config.js';

// ── CORS ──────────────────────────────────────────────────────────────────────

/**
 * Set CORS headers, restricting to allowedOrigins defined in config.js.
 *
 * @param {import('http').IncomingMessage} req
 * @param {import('http').ServerResponse}  res
 */
export function setCorsHeaders(req, res) {
  const origin = req.headers.origin || '';

  if (config.allowedOrigins.includes(origin)) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
  }

  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
}

// ── Validation ────────────────────────────────────────────────────────────────

/**
 * Basic email address validation using RFC 5321-compatible pattern.
 *
 * @param {string} email
 * @returns {boolean}
 */
export function validateEmail(email) {
  // RFC 5321: local part up to 64 chars, domain up to 255 chars
  return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/.test(email);
}

/**
 * Accept South African mobile numbers in common formats:
 *  0821234567  /  +27821234567  /  27821234567
 *
 * @param {string} phone
 * @returns {boolean}
 */
export function validatePhone(phone) {
  if (typeof phone !== 'string' || phone.length === 0) {
    return false;
  }
  const clean = phone.replace(/[\s\-()+]/g, '');
  return /^(27|0)[6-9]\d{8}$/.test(clean);
}

// ── Template variable substitution ───────────────────────────────────────────

/**
 * Replace {placeholders} in a template string with actual values.
 *
 * @param {string}              template - Template containing {key} placeholders
 * @param {Record<string,string>} vars   - Map of key → value
 * @returns {string}
 */
export function applyTemplateVars(template, vars) {
  let result = template;
  for (const [key, value] of Object.entries(vars)) {
    result = result.replaceAll(`{${key}}`, String(value ?? ''));
  }
  return result;
}

// ── Rate limiting ─────────────────────────────────────────────────────────────

// In-memory rate limiter for serverless (resets per cold start — acceptable
// for this use case; a Redis store would be needed for strict enforcement).
const _rateLimitWindow = [];

/**
 * Simple in-memory rate limiter.
 * Returns true if the request is within the allowed rate, false otherwise.
 *
 * @returns {boolean}
 */
export function checkRateLimit() {
  const now    = Date.now();
  const window = 60 * 1000; // 1 minute in ms
  const limit  = config.rateLimitPerMinute;

  // Remove entries outside the current window
  while (_rateLimitWindow.length > 0 && now - _rateLimitWindow[0] >= window) {
    _rateLimitWindow.shift();
  }

  if (_rateLimitWindow.length >= limit) {
    return false;
  }

  _rateLimitWindow.push(now);
  return true;
}

// ── Communication logging ─────────────────────────────────────────────────────

/**
 * Log a communication event to the console (Vercel captures stdout in
 * function logs). Structured as JSON for easy querying.
 *
 * @param {Object} entry - Fields: type, to, subject?, message?, success, error?, gateway_ref?, sentBy?
 */
export function logCommunication(entry) {
  console.log(JSON.stringify({
    ...entry,
    timestamp: new Date().toISOString()
  }));
}
