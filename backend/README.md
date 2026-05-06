# LeadStream Backend

Cloud Run service that issues HTTP-set first-party cookies on behalf of WP sites running the LeadStream plugin, and ingests attribution events into Firestore.

## Why this exists

Browsers (Safari ITP especially) cap DOM-set cookies (`document.cookie =`) at 7 days from creation. HTTP-set first-party cookies (via `Set-Cookie` response header) follow a more lenient inactivity rule and survive longer for active visitors. The plugin's `class-rest.php` already does this on the WordPress site itself, but on cached pages the WP REST endpoint never runs and we lose the long-lived cookie.

This service runs on a CNAMEd subdomain of each beta site (e.g. `relay.timberbrookmarketing.com`). Because the subdomain shares the registrable domain, the browser accepts `Set-Cookie` with `Domain=.timberbrookmarketing.com` as first-party. The plugin's `capture.js` makes one cross-origin POST to this service per pageview; the response sets the long-lived cookies.

## Routes

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/health` | none | Liveness probe (`/healthz` is intercepted by Cloud Run's GFE, hence `/health`) |
| POST | `/v1/cookie` | License key + Origin check | Issues `Set-Cookie` for the parent domain |
| POST | `/v1/events` | License key | Ingests attribution events into Firestore |

Auth is currently a stub: any non-empty `Authorization: Bearer <key>` or `X-License-Key` header is accepted. Real Freemius integration lands in plugin v0.13.x.

## Local dev

```bash
cd backend
npm install
npm run dev
```

Service runs on `http://localhost:8080` with `console.log` instead of Firestore writes (no credentials required for local dev).

## Deploy to leadstream-dev

The GCP project, billing, APIs, Firestore database, runtime service account, and Artifact Registry repo are already provisioned. To deploy:

```powershell
cd C:\Users\User\Projects\leadstream\backend
gcloud builds submit --project=leadstream-dev --config=cloudbuild.yaml .
```

Cloud Build will build the image, push to Artifact Registry, and deploy to Cloud Run with the runtime service account. First deployment takes ~3-5 minutes; subsequent deploys ~1-2 minutes.

After the first deploy, get the service URL:

```powershell
gcloud run services describe leadstream-backend --project=leadstream-dev --region=us-central1 --format="value(status.url)"
```

That URL is the default Cloud Run URL (e.g. `https://leadstream-backend-xyz-uc.a.run.app`). It works for testing but does not allow setting cookies on client domains — the CNAME step below is what unlocks that.

## Per-site CNAME setup

For the cookie issuance to actually defeat ITP, the service must be reachable on a subdomain of each beta site so Set-Cookie can target the parent domain.

Map a custom domain in Cloud Run for each site:

```powershell
gcloud run domain-mappings create --service=leadstream-backend `
  --domain=relay.timberbrookmarketing.com `
  --project=leadstream-dev --region=us-central1
```

Cloud Run will return DNS records you need to add at the site's DNS provider. Typically two:
- An `A` record on `relay.timberbrookmarketing.com` pointing at Google's load balancer IP
- An `AAAA` record for IPv6
- Or, simpler, a `CNAME` to `ghs.googlehosted.com.` if the parent zone supports CNAMEs at non-apex names

After DNS propagates (5-30 min), Google issues a managed TLS cert automatically. Verify with:

```powershell
curl https://relay.timberbrookmarketing.com/health
```

Repeat for each beta site:
- `relay.timberbrookmarketing.com`
- `relay.ascendpropertymanagement.co`
- `relay.controlstation.com`
- `relay.nexeris.us`
- `relay.nolimitcarts.com` (when 2.6 ships and we onboard nolimitcarts)

## Plugin configuration per site

In each WP site's **LeadStream → Settings → Backend**:
- **Backend URL**: `https://relay.<that-site>` (the CNAMEd subdomain)
- **License key**: any non-empty value for now; real keys when Freemius lands

The plugin will then POST events to `<backend>/v1/events` and have the browser hit `<backend>/v1/cookie` for ITP-safe cookie issuance.

## Promoting to staging / prod

`leadstream-dev` is for active development. When the service stabilizes:

```powershell
# Provision leadstream-staging the same way leadstream-dev was
gcloud projects create leadstream-staging --name="LeadStream Staging"
gcloud billing projects link leadstream-staging --billing-account=01F9E0-D63035-ED66A4
gcloud services enable run.googleapis.com cloudbuild.googleapis.com `
  artifactregistry.googleapis.com firestore.googleapis.com `
  iam.googleapis.com secretmanager.googleapis.com --project=leadstream-staging
# ... and the rest of the dev setup steps from memory ...

# Then deploy:
gcloud builds submit --project=leadstream-staging --config=cloudbuild.yaml .
```

`leadstream-prod` already exists (it holds the Google Ads OAuth client). Before deploying the backend there:
1. Migrate billing from personal to `Timberbrook Marketing (Clients)`
2. Run the same setup steps as dev (Firestore, SA, Artifact Registry)
3. `gcloud builds submit --project=leadstream-prod ...`

## Cost

Cloud Run + Firestore at beta scale (5 sites, ~few hundred events/day each):
- Cloud Run: free tier covers it (2M requests/month free)
- Firestore: free tier covers it (50k reads/20k writes/day, 1GB storage)
- Cloud Build: 120 free build-minutes/day (we use ~2 per deploy)
- Artifact Registry: ~$0.10/month for image storage

Realistic cost at this scale: $0-5/month total. Scales linearly with traffic.
