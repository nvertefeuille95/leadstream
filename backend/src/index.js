/**
 * LeadStream backend service.
 *
 * Two routes that justify the service:
 *
 *   POST /v1/cookie  Issues a Set-Cookie HTTP header on the parent domain
 *                    (e.g. .timberbrookmarketing.com) so cookies dodge Safari
 *                    ITP's 7-day creation cap on DOM-set cookies. Requires
 *                    the service to be reachable on a CNAMEd subdomain of
 *                    the WP site (relay.<site>) — without that, browsers
 *                    will not accept the Set-Cookie cross-domain.
 *
 *   POST /v1/events  Receives attribution events from the plugin's
 *                    `/leadstream/v1/relay` proxy. Stores in Firestore
 *                    keyed by license_key + event_id. Provides cross-site
 *                    aggregation for the agency tier.
 *
 * License auth is currently a stub: any non-empty key is accepted. Real
 * Freemius integration lands in v0.13.x.
 */

'use strict';

const express = require('express');
const { Firestore } = require('@google-cloud/firestore');

const app = express();

app.set('trust proxy', true);
app.disable('x-powered-by');
app.use(express.json({ limit: '64kb' }));

// Firestore client. Cloud Run picks up the project + ADC automatically.
const firestore = process.env.GOOGLE_CLOUD_PROJECT
  ? new Firestore({ projectId: process.env.GOOGLE_CLOUD_PROJECT })
  : null;

// --- Middleware ---------------------------------------------------------

/**
 * License key gate. Reads `Authorization: Bearer <key>` or `X-License-Key`
 * header. v0.11.0 stub: any non-empty key passes. Real validation lands
 * in v0.13.x with Freemius.
 */
function requireLicense(req, res, next) {
  const auth = req.get('authorization') || '';
  const headerKey = req.get('x-license-key') || '';
  const bearerMatch = auth.match(/^Bearer\s+(.+)$/i);
  const key = bearerMatch ? bearerMatch[1] : headerKey;

  if (!key || key.trim() === '') {
    return res.status(401).json({ error: 'license_key_required' });
  }

  req.licenseKey = key.trim();
  next();
}

/**
 * Validate that the request is from a domain we expect. Caller passes the
 * `domain` parameter we should set the cookie on; we cross-check it
 * against the Origin header so a stolen license key cannot set cookies
 * for an arbitrary third-party domain.
 */
function validateDomain(claimed, originHeader) {
  if (!claimed || typeof claimed !== 'string') return null;
  const lower = claimed.toLowerCase().trim();
  if (!/^[a-z0-9-]+(\.[a-z0-9-]+)+$/.test(lower)) return null;
  if (lower.length > 253) return null;
  if (!originHeader) return lower; // permissive when origin missing (server-to-server)
  try {
    const origin = new URL(originHeader);
    const host = origin.hostname.toLowerCase();
    // Origin must equal claimed domain or be a subdomain of it.
    if (host === lower || host.endsWith('.' + lower)) return lower;
  } catch {
    return null;
  }
  return null;
}

// --- Routes -------------------------------------------------------------

app.get('/healthz', (_req, res) => {
  res.status(200).json({ status: 'ok', version: '0.1.0' });
});

/**
 * Issue HTTP-set cookies on the parent domain.
 *
 * Body: {
 *   domain: 'timberbrookmarketing.com',
 *   cookies: { utm_source: 'google', click_id: 'abc', ... },
 *   max_age_days: 365
 * }
 *
 * Returns 204 No Content with one Set-Cookie header per attribution
 * field. The browser accepts these as first-party because the request
 * went to relay.<domain>, which shares the registrable domain.
 */
app.post('/v1/cookie', requireLicense, (req, res) => {
  const { domain, cookies, max_age_days } = req.body || {};

  const sanitizedDomain = validateDomain(domain, req.get('origin'));
  if (!sanitizedDomain) {
    return res.status(400).json({ error: 'invalid_domain' });
  }
  if (!cookies || typeof cookies !== 'object') {
    return res.status(400).json({ error: 'cookies_required' });
  }

  const maxAge = Math.max(1, Math.min(365, parseInt(max_age_days, 10) || 365)) * 86400;
  const allowedKeys = [
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'click_id', 'click_id_type', 'first_page', 'referrer', 'visitor'
  ];

  const setCookies = [];
  for (const [key, value] of Object.entries(cookies)) {
    if (!allowedKeys.includes(key)) continue;
    if (typeof value !== 'string' || value.length === 0 || value.length > 1024) continue;
    const cookieName = 'leadstream_' + key;
    const encodedValue = encodeURIComponent(value);
    setCookies.push(
      `${cookieName}=${encodedValue}; Domain=.${sanitizedDomain}; Path=/; ` +
      `Max-Age=${maxAge}; Secure; SameSite=Lax`
    );
  }

  if (setCookies.length === 0) {
    return res.status(400).json({ error: 'no_valid_cookies' });
  }

  res.set('Set-Cookie', setCookies);
  res.set('Access-Control-Allow-Origin', req.get('origin') || '*');
  res.set('Access-Control-Allow-Credentials', 'true');
  res.status(204).send();
});

/**
 * Ingest attribution events.
 *
 * Body: { event_id, visitor_id, type, ...payload }
 *
 * Stores under `tenants/{license_key}/events/{event_id}` so multiple
 * sites under one license stay in the same partition. Idempotent on
 * event_id (duplicate writes are no-ops).
 */
app.post('/v1/events', requireLicense, async (req, res) => {
  const event = req.body || {};
  const eventId = typeof event.event_id === 'string' ? event.event_id : null;

  if (!eventId || !/^[0-9a-f-]{16,128}$/i.test(eventId)) {
    return res.status(400).json({ error: 'event_id_required' });
  }

  const record = {
    ...event,
    received_at: new Date().toISOString(),
    license_key: req.licenseKey,
  };

  if (firestore) {
    try {
      const ref = firestore
        .collection('tenants')
        .doc(req.licenseKey)
        .collection('events')
        .doc(eventId);
      await ref.set(record, { merge: true });
    } catch (err) {
      console.error('firestore_write_failed', { eventId, error: err.message });
      return res.status(500).json({ error: 'storage_failed' });
    }
  } else {
    // Local dev: just log so we can verify the contract without GCP creds.
    console.log('event_received', { eventId, license: req.licenseKey, record });
  }

  res.status(202).json({ ok: true, event_id: eventId });
});

// CORS preflight for the two POST routes the browser hits cross-origin.
app.options('/v1/cookie', (req, res) => {
  res.set('Access-Control-Allow-Origin', req.get('origin') || '*');
  res.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.set('Access-Control-Allow-Headers', 'Authorization, X-License-Key, Content-Type');
  res.set('Access-Control-Allow-Credentials', 'true');
  res.set('Access-Control-Max-Age', '86400');
  res.status(204).send();
});

app.use((err, _req, res, _next) => {
  console.error('unhandled_error', { error: err.message, stack: err.stack });
  res.status(500).json({ error: 'internal' });
});

const PORT = process.env.PORT || 8080;
app.listen(PORT, () => {
  console.log(`leadstream-backend listening on :${PORT}`);
});
