# LeadStream Backlog

Tracking items beyond Phase 1 that are in scope but not yet built. Source of truth for "what's next" conversations. PROJECT_PLAN.md remains the long-form roadmap.

## Priority: Hyros-parity (Phase 3 + 4)

Goal: close the loop so Google Ads, Meta, LinkedIn, and TikTok optimize against real conversions, not just browser pixels. Every submission we already capture has the raw material; the work is server-side infrastructure + ad-platform APIs.

### Immediate, no code needed (Make.com interim)

Build these in the existing Make.com workspace before Phase 3 infrastructure lands. Each gets us 80% of Hyros value per platform at roughly 4 hours of scenario-building.

- [ ] **Scenario: GF webhook → Google Ads Offline Conversion Import (OCI)**. Filter rows where `click_id_type = gclid`, SHA256 the email, post to Google Ads API `uploadClickConversions`. Use the `event_id` from our events table as Google's `orderId` for dedup.
- [ ] **Scenario: GF webhook → Meta Conversions API (CAPI)**. Hash email + phone, send `Lead` event with `event_id` for pixel dedup, include `fbc` / `fbp` when present.
- [ ] **Scenario: GF webhook → LinkedIn Conversions API** on `li_fat_id` matches.
- [ ] **Dashboard sheet**: Make.com writes a summary row per upload success/failure to a Google Sheet so we can spot-check reconciliation while building Phase 4.

### Phase 3 (server-side backend)

- [ ] Cloud Run service in `leadstream-dev` GCP project. Firestore for event storage, license-key bearer auth.
- [ ] REST endpoint `/leadstream/v1/relay` proxies attribution events to Cloud Run with hashed IP for ITP workaround.
- [ ] Server-side `Set-Cookie` response with 365-day expiry (bypasses Safari 7-day ITP cap).
- [ ] Cross-subdomain tracking: option to set cookies on `.parent-domain.com`.
- [ ] Event deduplication via `event_id` to prevent double-counting on retries.
- [ ] License key provisioning flow (Freemius → paste key → backend validates → Pro features unlock).
- [ ] Backend admin dashboard (separate from WP admin) for event inspection and manual OCI trigger.
- [ ] Deploy path: `leadstream-dev` → `leadstream-staging` → `leadstream-prod`.

### Phase 4 (offline conversion automation)

- [ ] Google Ads OCI via Google Ads API, hashed email + phone, batch every 15 minutes, partial-failure reporting.
- [ ] Google Enhanced Conversions: hashed PII sent with gclid on form submit, deduped against OCI.
- [ ] Meta CAPI: event schema, `event_id` dedup with pixel, test events endpoint for QA.
- [ ] LinkedIn Conversions API: match to `li_fat_id`.
- [ ] TikTok Events API: match to `ttclid`.
- [ ] Per-form conversion mapping UI: pick form → ad-platform conversion action(s) → value formula (static, form-field, Woo order total).
- [ ] Upload log with retry queue, dead-letter handling, reconciliation report.

## Deferred from Phase 1

- [ ] **Form integrations**: Contact Form 7, WPForms, Ninja Forms, Fluent Forms. Build when a beta site actually uses one.
- [ ] **Shortcode** `[leadstream field="..."]`. Low value for the core workflow; slot in when a real use case appears (thank-you page, debug view).

## Phase 2 (premium features, in-plugin)

From PROJECT_PLAN.md. These justify a Pro tier on plugin value alone, independent of Phases 3-4.

- [ ] 2.1 Attribution dashboard in WP admin: first-touch, last-touch, linear, date-range filters, conversion counts.
- [ ] 2.2 UTM builder: saved presets, taxonomy enforcement, bulk CSV export.
- [ ] 2.3 Multi-touch attribution model toggle: first / last / linear / position-based / time-decay.
- [ ] 2.4 Session journey log (last 20 pages per session) in the submission detail view.
- [ ] 2.5 Webhook outbound on form submit with retry + exponential backoff.
- [ ] 2.6 WooCommerce order-level attribution (deploy target: `nolimitcarts.com`).
- [ ] 2.7 Consent Mode v2 deep integration (beyond the current signal-respect pattern).
- [ ] 2.8 Freemius Pro gating for dashboards.

## Phase 5 (Agency tier)

- [ ] 5.1 White label (name, logo, color scheme).
- [ ] 5.2 Multi-client dashboard on the backend.
- [ ] 5.3 Master config API: push settings from agency dashboard to child sites.
- [ ] 5.4 Scheduled exports: Google Sheets, BigQuery, S3.
- [ ] 5.5 Branded PDF reports (weekly or monthly).

## Pre-launch admin (do before v1.0 public release)

- [ ] USPTO TESS trademark search for "LeadStream" in software + marketing services classes.
- [ ] Register `leadstream.io` (first choice) or `getleadstream.com` (fallback).
- [ ] Register `leadstream` slug on wp.org; create developer account.
- [ ] Provision `leadstream-dev`, `leadstream-staging`, `leadstream-prod` GCP projects under Timberbrook billing.
- [ ] Create Freemius developer account; configure Pro ($39/mo, $390/yr) and Agency ($129/mo, $1,290/yr).
- [ ] Remove `LEADSTREAM_BETA_DOMAINS` constant before public release.
