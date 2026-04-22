# LeadStream Project Plan

> Load this file into Claude Code at project root. Reference it in CLAUDE.md so it stays in context.

## Product

**LeadStream by Timberbrook Marketing**. Premium WordPress attribution plugin with an optional Cloud Run backend for server-side capture and offline conversion automation. Captures UTMs, click IDs, and referrer data; fills form hidden fields; pushes offline conversions back to ad platforms.

Tagline options (pick one at launch): "Every lead, traced to its source." / "Attribution that flows through your whole funnel." / "Know where every lead started."

## Identity

- **Product name**: LeadStream
- **Tagline**: "By Timberbrook Marketing" in admin footer, about page, and landing page footer. Standalone brand in all other contexts.
- **Plugin slug / text domain**: `leadstream`
- **PHP namespace**: `LeadStream\`
- **Main file**: `plugin/leadstream.php`
- **Repo name**: `leadstream`
- **Marketing domain**: `leadstream.io` first choice, `getleadstream.com` fallback (verify availability before purchase)

## Positioning

- **Free tier**: parity with competitors. Client-side UTM and click ID capture, cookies, form hidden field filling, basic admin.
- **Pro tier** ($39/mo or $390/yr, 1 site): server-side capture endpoint, offline conversion upload to Google Ads and Meta CAPI, attribution dashboard, UTM builder, WooCommerce revenue attribution.
- **Agency tier** ($129/mo or $1,290/yr, 10 sites): white label, multi-client dashboard, webhook routing, scheduled exports, branded reports.

## Locked Technical Decisions

1. **Licensing system**: Freemius. Wrap the SDK in a `LeadStream\License` abstraction so the implementation is swappable if we ever hit scale where the 7% revenue share outweighs the time cost of switching.
2. **Minimum WordPress**: 6.0. **Minimum PHP**: 7.4.
3. **Autoloading**: PSR-4 via Composer, namespace `LeadStream\`.
4. **Backend hosting** (Phase 3+): Three dedicated GCP projects: `leadstream-dev`, `leadstream-staging`, `leadstream-prod`. Not shared with Timberbrook's agent system. Reuse deploy patterns (Cloud Build yaml, Cloud Run service config, Firestore conventions) from the agent system; do not reuse the project itself.
5. **Dev environment**: Local by Flywheel site at `leadstream.local` for each developer. Disposable Lando config checked into repo for CI parity.
6. **Distribution**:
   - Free tier submitted to wp.org plugin repo after Phase 1.
   - Pro and Agency distributed via Freemius checkout on `leadstream.io` after Phase 2.
   - Bundled into Timberbrook retainer as an included service.

## Beta Rollout Sequence

Phased to manage risk, starting with controlled environments and ending on the highest-stakes client site.

1. **Phase 1 complete**: deploy to `timberbrookmarketing.com` first. Our site, full control, validates free tier end to end before external users see it.
2. **Phase 2 partial (dashboard + WooCommerce)**: add `nolimitcarts.com` second. Real e-commerce traffic and active paid ads generate the Google and Meta click IDs needed to validate WooCommerce order-level attribution, which is a key differentiator vs LeadSourcePro and Gravity-only competitors.
3. **Phase 2 complete**: add `controlstation.com` third. We already audited their Google Ads and LinkedIn setup, so we know the conversion structure. Validates form integrations and attribution accuracy on a B2B industrial site.
4. **Phase 3+**: add `nexeris.us` last, once their Elementor build is out of active iteration. Adding an attribution plugin to a site still being heavily built creates debugging noise we do not need.

All four beta sites get a `LEADSTREAM_BETA_DOMAINS` constant that enables verbose logging and bypasses rate limits on beta domains. Remove before public launch.

## Repo Structure

```
/
├── CLAUDE.md                        # Root context (conventions, skills, rules)
├── PROJECT_PLAN.md                  # This file
├── README.md                        # Public-facing readme
├── .github/workflows/               # CI: phpcs, PHPUnit, JS lint, release
├── plugin/
│   ├── CLAUDE.md                    # Plugin-specific rules (WP standards)
│   ├── leadstream.php               # Main plugin file with header
│   ├── composer.json
│   ├── package.json
│   ├── includes/
│   │   ├── class-core.php           # Bootstrap, hooks registration
│   │   ├── class-capture.php        # Click ID detection, classification
│   │   ├── class-cookies.php        # First-party cookie management
│   │   ├── class-forms/             # One class per form integration
│   │   ├── class-rest.php           # REST endpoints for server-side relay
│   │   ├── class-admin.php          # Settings, dashboard UI
│   │   ├── class-license.php        # Freemius wrapper
│   │   └── class-security.php       # Sanitize, escape, nonce helpers
│   ├── assets/
│   │   ├── js/capture.js            # Port of current attribution.js
│   │   ├── js/admin.js
│   │   └── css/
│   └── tests/
│       ├── phpunit/                 # WP_Mock or Brain Monkey
│       └── e2e/                     # Playwright against Local site
├── backend/                         # Phase 3+ only
│   ├── CLAUDE.md                    # Backend-specific rules
│   ├── src/
│   │   ├── ingest/                  # Attribution event ingestion
│   │   ├── connectors/              # Google Ads, Meta, LinkedIn, TikTok
│   │   ├── storage/                 # Firestore models
│   │   └── auth/                    # License key validation
│   ├── Dockerfile
│   └── cloudbuild.yaml
└── docs/
    ├── user-guide.md
    ├── integration-hubspot.md
    └── offline-conversions-setup.md
```

## Phase 0: Foundation (Week 1)

**Goal**: Repo, dev environment, and plugin scaffold exist. Nothing user-facing yet.

**Tickets**:

- 0.1 Initialize repo with `CLAUDE.md`, `PROJECT_PLAN.md`, `.gitignore`, `README.md`.
- 0.2 Set up Local by Flywheel site at `leadstream.local`. Document setup in `docs/dev-setup.md`.
- 0.3 Create `plugin/leadstream.php` with plugin header (Name: "LeadStream by Timberbrook Marketing"), activation hook (creates DB table for attribution events), deactivation hook (preserves data), uninstall hook (full cleanup behind setting).
- 0.4 Add Composer and PSR-4 autoloading with `LeadStream\` namespace.
- 0.5 Add PHPUnit config with WP_Mock. Write one passing test to prove setup.
- 0.6 Add PHPCS with WordPress Coding Standards ruleset. Add to CI.
- 0.7 Stub Freemius SDK integration wrapped in `LeadStream\License` abstraction.
- 0.8 Create `leadstream-dev` GCP project (no services deployed yet; reserve the name).

**Acceptance**: `composer install && vendor/bin/phpunit` passes. Plugin activates in Local site with no warnings in debug.log. Plugin header shows "LeadStream by Timberbrook Marketing" in WP admin plugins list.

## Phase 1: Free Plugin Core (Weeks 2 to 4)

**Goal**: Feature parity with HandL free and AFL free, but with cleaner paid-click-first classification logic from existing code.

**Tickets**:

- 1.1 Port existing `attribution.js` into `assets/js/capture.js`. Fix gaps: add `SameSite=Lax` and `Secure` flags, handle subdomain cookies (`.example.com`) optionally, add fbclid detection, add consent-mode-aware gating.
- 1.2 Build `class-capture.php` as PHP-side equivalent that runs on `wp` hook and writes the same cookies server-side when JS is disabled (PHP fallback).
- 1.3 Click ID detection for: gclid, dclid, gbraid, wbraid, gad_source, msclkid, fbclid, ttclid, twclid, li_fat_id, ScCid (Snap), epik (Pinterest). Unit test each.
- 1.4 Source classification precedence: explicit UTM > click ID > referrer map > direct. Match AFL UTM Tracker's referrer classification breadth at minimum.
- 1.5 Form integrations (one class each): Elementor Pro, Gravity Forms, Contact Form 7, WPForms, Ninja Forms, Fluent Forms. Each handles hidden field detection by field name matching.
- 1.6 Shortcodes: `[leadstream field="utm_source"]`, `[leadstream field="click_id"]`, `[leadstream field="first_page"]` for displaying stored values in templates.
- 1.7 Admin settings page: cookie duration (1 to 365 days), which parameters to track, consent plugin integration (Cookiebot, Complianz, WP Consent API), subdomain tracking toggle, debug mode, beta-domain verbose logging flag.
- 1.8 Admin "recent submissions" tab showing last 100 captured attributions (stored in custom table).
- 1.9 Uninstall cleanup with opt-out setting.

**Acceptance**: 
- `timberbrookmarketing.com` with Elementor form captures utm_source, utm_medium, click_id, first_page correctly across direct, Google Ads (gclid only, no UTMs), Google organic, Facebook referral, and TikTok click scenarios.
- Plugin passes WordPress Plugin Check tool with zero errors.
- No PHPCS violations on WordPress-Extra ruleset.
- Works on cached pages (WP Rocket test).

## Phase 2: Premium Plugin Features (Weeks 5 to 8)

**Goal**: Justify a Pro tier purchase on plugin features alone, before any backend work.

**Tickets**:

- 2.1 Attribution dashboard in admin: first-touch by source/medium/campaign, last-touch, linear attribution, with date range filter and conversion counts.
- 2.2 UTM builder: form in admin that generates tagged URLs with saved source/medium/campaign presets, taxonomy enforcement (warn on `Google` vs `google` mismatch), bulk CSV generator.
- 2.3 Multi-touch attribution model toggle: first, last, linear, position-based (40/20/40), time-decay.
- 2.4 Session journey log: store pageview array per session, show in admin submission detail view (last 20 pages before submit).
- 2.5 Webhook outbound: on form submit, POST enriched payload (form data plus attribution) to configured URL. Retry with exponential backoff on 5xx.
- 2.6 WooCommerce order-level attribution: hook `woocommerce_new_order`, write attribution to order meta, expose in order detail view, report revenue by source. **Deploy to `nolimitcarts.com` after this ticket passes internal QA.**
- 2.7 Consent Mode v2 integration: respect `analytics_storage` and `ad_storage` signals from Cookiebot, Complianz, WP Consent API before setting cookies.
- 2.8 Freemius Pro gating: fields in 1.7 available in free, dashboards in 2.1 through 2.6 gated to Pro.

**Acceptance**:
- Pro-tier test license unlocks dashboards correctly; free license sees upgrade prompts.
- WooCommerce test order on `nolimitcarts.com` submitted via Google Ads gclid flow retains attribution through order meta and shows in revenue report.
- Webhook delivery succeeds against a Make.com test endpoint.
- `controlstation.com` deployment validates B2B form integrations against Gravity Forms.

## Phase 3: Server-Side Backend (Weeks 9 to 12)

**Goal**: Cloud Run service exists, plugin can relay events to it, cookies can be set server-side to defeat ITP.

**Tickets**:

- 3.1 Scaffold Cloud Run service in `leadstream-dev` GCP project using same patterns as Timberbrook agent system. Firestore for event storage, license-key-as-bearer-token auth.
- 3.2 Plugin REST endpoint `/leadstream/v1/relay` that proxies attribution events from the WP site to Cloud Run, including hashed IP for Safari ITP workaround.
- 3.3 Server-side Set-Cookie response that writes a first-party cookie on the WP domain with 365-day expiry (bypasses 7-day ITP cap).
- 3.4 Cross-subdomain tracking: option to set cookies on `.parent-domain.com` so `app.site.com` and `www.site.com` share attribution.
- 3.5 Event deduplication via `event_id` to prevent double-counting on retries.
- 3.6 License key provisioning flow: Freemius license issued, user pastes key into plugin, plugin calls backend to validate and activates Pro/Agency features.
- 3.7 Backend admin dashboard (separate from WP admin) for event inspection and manual conversion upload triggering.
- 3.8 Promote service to `leadstream-staging` for integration testing, then to `leadstream-prod` before beta use.
- 3.9 Deploy to `nexeris.us` once their Elementor build work settles.

**Acceptance**:
- Safari 17 test: cookie persists beyond 7 days when server-side relay is on, vs 7-day expiry without it.
- Plugin continues to function fully if backend is unreachable (graceful degradation to client-side only).
- All four beta sites running Phase 3 build successfully.

## Phase 4: Offline Conversion Automation (Weeks 13 to 16)

**Goal**: The real moat. Competitors do not do this natively.

**Tickets**:

- 4.1 Google Ads Offline Conversion Import via Google Ads API: hash email and phone (SHA256), batch uploads every 15 minutes, handle partial failure reporting.
- 4.2 Enhanced Conversions for Google Ads: send hashed PII with gclid on form submit, dedupe against OCI.
- 4.3 Meta Conversions API: event schema, event_id dedup with client-side pixel, test events endpoint.
- 4.4 LinkedIn Conversions API: match events to li_fat_id.
- 4.5 TikTok Events API: event match to ttclid.
- 4.6 Per-form conversion mapping UI: user selects a form, maps it to one or more ad platform conversion actions, sets a value formula (static, form field, WooCommerce order total).
- 4.7 Upload log with retry queue, dead-letter handling, and reconciliation report.

**Acceptance**:
- Control Station Google Ads test account shows OCI conversions within 3 hours of form submit on `controlstation.com`.
- Meta Events Manager test events endpoint shows CAPI events matching pixel events with dedup confirmed on `nolimitcarts.com`.

## Phase 5: Agency Tier (Weeks 17 to 20)

**Goal**: $1,290/year justified.

**Tickets**:

- 5.1 White label: custom plugin name, logo, color scheme in admin UI (Agency tier only).
- 5.2 Multi-client dashboard at backend: agency user sees all connected client sites in one view.
- 5.3 Master config API: push settings from agency dashboard to connected child sites.
- 5.4 Scheduled exports: Google Sheets, BigQuery, S3.
- 5.5 Branded PDF reports: weekly or monthly email with attribution summary per client.

**Acceptance**:
- Timberbrook agency dashboard shows all four beta sites in one unified view and can push a settings change to all four in one action.

## Security Checklist (applies to every phase)

Every PR must pass these. Add to CLAUDE.md as enforced rules.

1. Every `$_POST`, `$_GET`, `$_REQUEST`, `$_COOKIE` read is sanitized at ingest with the appropriate function (`sanitize_text_field`, `sanitize_email`, `absint`, etc.).
2. Every echo or attribute output is escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
3. Every form POST uses `wp_nonce_field` on render and `check_admin_referer` on handle.
4. Every admin action gates on `current_user_can('manage_options')` or a more specific cap.
5. All database writes use `$wpdb->prepare` with placeholders.
6. All REST endpoints have a `permission_callback` that is not `__return_true`.
7. No `eval`, no `extract`, no unserialize of external input, no remote code loading.
8. Secrets (license keys, API tokens) stored encrypted or in constants, never echoed in admin UI beyond the last 4 characters.

## Testing Strategy

- **Unit (PHPUnit + WP_Mock)**: click ID detection, source classification, sanitization helpers, hash generators.
- **Integration (PHPUnit + WP test harness)**: form integrations, REST endpoints, admin settings save/load.
- **E2E (Playwright)**: full flow from landing-with-gclid through form submission through expected data in admin and (in Phase 4) in ad platform test account.
- **Manual browser matrix per release**: Chrome, Safari, Firefox, iOS Safari. Focus on Safari ITP behavior.

## Release Strategy

- **End of Phase 1**: submit free version to wp.org repo. Expect 2 to 4 week review cycle on first submission. Deploy to `timberbrookmarketing.com`.
- **End of Phase 2**: Freemius Pro launch on `leadstream.io`. Deploy to `nolimitcarts.com` and `controlstation.com`. Soft announce to Timberbrook client base.
- **End of Phase 3**: Deploy to `nexeris.us`. All four beta sites live.
- **End of Phase 4**: Product Hunt launch, content campaign on offline conversion automation.
- **End of Phase 5**: Agency tier launch, direct outreach to WordPress-centric marketing agencies.

## Pre-Launch Open Items (handle before Phase 0 kickoff)

1. USPTO TESS trademark search for "LeadStream" in software and marketing services classes.
2. Register domain: `leadstream.io` first choice, `getleadstream.com` fallback.
3. Register `leadstream` slug on wp.org by creating a developer account and submitting a reservation (or submit the Phase 1 build directly when ready).
4. Create `leadstream-dev`, `leadstream-staging`, `leadstream-prod` GCP projects under Timberbrook billing account.
5. Create Freemius developer account, register product, configure pricing tiers to match Pro ($39/mo, $390/yr) and Agency ($129/mo, $1,290/yr).
