# syntax=docker/dockerfile:1.7
# Dedicated nginx image bundling:
#   - compiled Vite assets (CSS/JS) from server/
#   - Laravel public/ folder
#   - downloadable agent zip + install.sh for the "Add monitored host" flow
#
# Build context = repo root (so we can reach both ./server and ./agent).
# Build with: `docker compose build web`

# ----- Stage 1: build frontend assets -----
FROM node:22-alpine AS assets

WORKDIR /app
COPY server/package.json server/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY server/postcss.config.js server/tailwind.config.js server/vite.config.js ./
COPY server/resources/ resources/
RUN npm run build

# ----- Stage 2: zip the agent for download by monitored hosts -----
FROM alpine:3 AS agent-zip

RUN apk add --no-cache zip
WORKDIR /build
COPY agent/ ./nicewatch-agent/
# Strip anything that shouldn't ship to monitored hosts.
RUN find nicewatch-agent -name '.DS_Store' -delete \
    && rm -rf nicewatch-agent/vendor nicewatch-agent/composer.lock nicewatch-agent/config.php \
    && zip -rq nicewatch-agent.zip nicewatch-agent

# ----- Stage 3: nginx -----
FROM nginx:1.27-alpine

# Laravel public/ (favicon, robots.txt, .htaccess, index.php placeholder, etc.)
COPY server/public/ /var/www/html/public/

# Vite build output (CSS, JS, manifest.json) — overrides empty public/build
COPY --from=assets /app/public/build /var/www/html/public/build

# Agent ZIP + one-liner installer, served as plain static files
COPY --from=agent-zip /build/nicewatch-agent.zip /var/www/html/public/downloads/nicewatch-agent.zip
COPY agent/install.sh /var/www/html/public/install.sh

# Server config
COPY server/docker/nginx.conf /etc/nginx/nginx.conf
