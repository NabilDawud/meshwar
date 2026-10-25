---
name: taqat-deploy
description: Use when configuring Dokku deployment for the Taqat apps server, building the production Laravel PHP container, running startup database migrations against MySQL, validating the /api/health endpoint with GIT_REV commit verification, or provisioning server environment secrets securely via SSH.
---

# Taqat Deploy (Laravel & MySQL)

Deployment automation and infrastructure rules for the Dokku-managed "Taqat apps" server running Laravel.

## Core Rules

1. **Production Destination**:
   - Host: Dokku server "Taqat apps".
   - Application Name: `mishwar`.
   - Production Domain: `https://mishwar.apps.taqat.academy`.
   - Managed Services: Pre-provisioned MySQL database (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` or `DATABASE_URL`), automated TLS termination, and internal port routing.
2. **Container & Build Architecture**:
   - Build using a lightweight production **PHP 8.4 + Nginx / FrankenPHP** `Dockerfile`.
   - The application container must listen on the assigned `PORT` environment variable (default: `80` or `3000` via Nginx/FrankenPHP).
   - Startup command must run database migrations (`php artisan migrate --force`) *before* serving requests.
   - **Hard gate**: If database migrations fail, the container must terminate immediately with a non-zero exit code to prevent deploying broken schema.
3. **Continuous Deployment Health Gate**:
   - Every commit merged into `main` automatically triggers Dokku deployment.
   - Live health verification endpoint: `GET /api/health`.
   - Payload requirement:
     ```json
     {
       "ok": true,
       "commit": "<GIT_REV>"
     }
     ```
     Where `GIT_REV` is read from `env('GIT_REV', 'unknown')`.
4. **Secure Secret Provisioning**:
   - Environment variables and credentials must be transmitted to Dokku exclusively via secure SSH commands from a configuration file.
   - Standard output and logs must be piped or discarded (`> /dev/null 2>&1`) so that no database passwords, app keys, or tokens are ever printed to logs or terminal screens.

See Dockerfile blueprint and deploy runbooks in [dokku-pipeline.md](./references/dokku-pipeline.md).
