# CLAUDE.md

## Project

**LeadStream by Timberbrook Marketing**. A premium WordPress attribution plugin (free tier) plus a Cloud Run backend (Pro and Agency tiers). Captures UTMs, click IDs, and referrer data; fills form hidden fields; pushes offline conversions back to ad platforms.

Read `PROJECT_PLAN.md` for phases, tickets, and acceptance criteria before starting any new work. When I say "work on 2.3" I mean ticket 2.3 in that file.

## Identity conventions (do not drift from these)

- **Product name**: LeadStream
- **Attribution**: "By Timberbrook Marketing" in admin footer and about page only. Keep the rest of the UI standalone-branded.
- **Plugin slug / text domain**: `leadstream`
- **PHP namespace**: `LeadStream\` (PSR-4 autoloaded via Composer)
- **Main plugin file**: `plugin/leadstream.php`
- **Constants**: prefix with `LEADSTREAM_` (e.g. `LEADSTREAM_VERSION`, `LEADSTREAM_PATH`, `LEADSTREAM_URL`)
- **Database table prefix**: `{$wpdb->prefix}leadstream_`
- **Cookie names**: `leadstream_utm_source`, `leadstream_click_id`, etc. Prefix everything with `leadstream_` to avoid collisions with other attribution plugins on the same site.
- **Hook names**: prefix with `leadstream_` (e.g. `leadstream_before_capture`, `leadstream_after_form_submit`)
- **REST namespace**: `leadstream/v1`
- **Option keys**: prefix with `leadstream_` (e.g. `leadstream_settings`, `leadstream_cookie_duration`)

## Skills to load automatically

Load the relevant skill before writing code, not after. These are mandatory, not optional:

- `wordpress-pro` when touching any PHP in `plugin/`, any plugin header, any hooks/filters, any WooCommerce or Gutenberg code, any REST API endpoint.
- `wp-performance-review` before opening any PR. Treat it as a required audit, not a suggestion.
- `frontend-design` when writing admin UI (dashboards, settings pages, UTM builder).
- `xlsx` only if building a CSV or Excel export feature.

## Non-negotiable rules

### Security (every PR)

1. Sanitize every `$_POST`, `$_GET`, `$_REQUEST`, `$_COOKIE` at the ingest point. Use the right function: `sanitize_text_field`, `sanitize_email`, `absint`, `esc_url_raw`, `sanitize_key`.
2. Escape every output. `esc_html` for text, `esc_attr` for attributes, `esc_url` for links, `wp_kses_post` for rich HTML.
3. Every admin form uses `wp_nonce_field` on render and `check_admin_referer` on handle.
4. Every REST endpoint has a real `permission_callback`. Never `__return_true` in production code.
5. Every `$wpdb` query with variables uses `$wpdb->prepare` with placeholders.
6. No `eval`, no `extract` with user input, no `unserialize` of external input, no remote code execution, no telemetry without user consent.
7. Every admin action checks `current_user_can()` with the narrowest appropriate capability.

If you catch yourself writing any of these wrong, stop and fix it. Do not wait for review.

### Code style

- PHP follows WordPress Coding Standards. Run `phpcs` before declaring a task done.
- PHP minimum: 7.4. WordPress minimum: 6.0. Do not use syntax that breaks either.
- PSR-4 autoloading via Composer. Namespace: `LeadStream\`.
- JavaScript: no jQuery unless the WP core dep already loads it. Prefer vanilla ES6+.
- CSS: use CSS variables for theming so white-label in Phase 5 is trivial.
- File naming: `class-thing.php` for classes, `thing.php` for procedural files.

### Freemius wrapping

Never call the Freemius SDK directly from feature code. Every license check, tier gate, or entitlement lookup goes through `LeadStream\License`. Example:

```php
// wrong
if ( my_freemius()->is_paying() ) { ... }

// right
if ( \LeadStream\License::is_pro() ) { ... }
```

This keeps the path open to swap licensing providers later without rewriting every feature.

### Testing

- Every new public method gets a PHPUnit test covering at least the happy path and one edge case.
- Form integrations get integration tests against a WP test harness.
- If you change attribution logic, add a test case to the click ID detection and source classification suite.
- Do not skip tests to ship faster. The whole product depends on correctness here.

### Writing style

No em dashes, anywhere, including in comments and admin UI copy. Use a period, comma, semicolon, or parens instead.

## Beta site awareness

The plugin ships with a `LEADSTREAM_BETA_DOMAINS` constant listing our four beta sites: `timberbrookmarketing.com`, `nolimitcarts.com`, `controlstation.com`, `nexeris.us`. On these domains, the plugin enables verbose logging and bypasses rate limits. This is an intentional pre-launch feature. Remove the constant and its usages before the v1.0 public release.

## Development environment

- Local WP site via Local by Flywheel. Site name: `leadstream.local`.
- PHP debug enabled: `WP_DEBUG`, `WP_DEBUG_LOG`, `SAVEQUERIES`, `SCRIPT_DEBUG` all true in local `wp-config.php`.
- Before declaring any ticket done, activate the plugin in the Local site, perform the user flow from the ticket's acceptance criteria, and check `debug.log` for warnings.

## What to do when stuck

- If you are unsure about a WordPress API behavior, load `wordpress-pro` and check its references rather than guessing.
- If you are unsure about an ad platform API (Google Ads, Meta CAPI, LinkedIn, TikTok), web-search the current official docs; do not rely on training data. These APIs change.
- If you find a security concern in existing code, flag it in the response and fix it in the same PR. Do not defer security.

## Commits and PRs

- Conventional commits: `feat(capture): add fbclid detection`, `fix(admin): escape settings output`.
- One ticket per PR where possible. Reference the ticket number: "Closes 1.3".
- PR description includes: what changed, why, how to test, any security implications.

## What I care about

- Correctness over speed. A wrong attribution is worse than a missing one.
- No security regressions, ever.
- Code that I can hand to another developer in 18 months without explanation.
- Backward compatibility once we ship to the wp.org repo. Breaking changes require a major version bump.

## What I do not care about

- Perfect coverage of every edge case on first pass. Ship the common case, write a test, move on.
- Lines of code. Short and clear beats long and defensive.
- Gold-plating admin UI before the core logic is proven.
