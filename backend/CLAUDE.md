# Backend rules

## Conventions

- Node.js 20+, CommonJS (`type: commonjs` in package.json), single-file Express app for now.
- No build step. Source ships as-is to Cloud Run via the Dockerfile.
- Production runs as non-root (`USER node` in Dockerfile).
- Port from `PORT` env var (Cloud Run sets this to 8080 by default).

## Auth

- License key passed via `Authorization: Bearer <key>` or `X-License-Key` header.
- v0.11.0 stub: any non-empty key is accepted.
- Real Freemius integration ships when plugin reaches v0.13.x.

## Storage

- Firestore Native, multi-region `nam5`.
- Tenant-keyed: `tenants/{license_key}/events/{event_id}`.
- Idempotent writes via `set(..., { merge: true })`.
- Run locally without Firestore by simply not setting `GOOGLE_CLOUD_PROJECT`; the service falls back to `console.log`.

## Cookie issuance

- Only issues cookies on a parent domain when the request originates from that domain (validated against Origin header).
- Allowed cookie names: `leadstream_*` only — see `allowedKeys` in `src/index.js`.
- Cookies are `Secure; SameSite=Lax`; we never use `HttpOnly` because the plugin's JS reads them client-side.

## Deployment

`gcloud builds submit --project=leadstream-dev --config=cloudbuild.yaml .` from the `backend/` directory. The Cloud Build pipeline builds the image, pushes to Artifact Registry, and deploys to Cloud Run with the runtime service account (`leadstream-cloudrun@<project>.iam.gserviceaccount.com`).

## Don't

- Don't add file-based config. Env vars or Firestore.
- Don't mix CommonJS and ESM. Stay CommonJS until there's a real reason to switch.
- Don't store license keys long-term — they're identity for the request, not data we own.
- Don't change cookie names or domain logic without coordinating with `plugin/assets/js/capture.js` and `plugin/includes/class-rest.php`.
