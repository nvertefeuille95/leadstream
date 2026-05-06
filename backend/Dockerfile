# LeadStream backend service container.
# Built and pushed by Cloud Build (cloudbuild.yaml) into the leadstream
# Artifact Registry repo, then deployed to Cloud Run.

FROM node:20-alpine

WORKDIR /app

# Dependencies first so the layer caches across source-only changes.
COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund

COPY src ./src

ENV NODE_ENV=production
ENV PORT=8080
EXPOSE 8080

# Run as non-root for the principle of least privilege.
USER node

CMD ["node", "src/index.js"]
