# Production Deployment & CI/CD Guide

This project uses **GitHub Actions** for automated Continuous Integration (CI) and Continuous Deployment (CD) to the Dokku server at `meshwar.apps.taqat.academy`.

---

## 1. Automated Pipeline Overview

Every `push` and `pull_request` to `main` triggers `.github/workflows/ci-cd.yml` which executes:

1. **PHP 8.4 & Node.js 24 environment setup**
2. **Code Style Verification:** `vendor/bin/pint --test`
3. **Frontend Compilation:** `npm ci && npm run build`
4. **Automated Test Suite:** `php artisan test --compact` (All 82 tests must pass)
5. **Production Caching Gate:** `config:cache`, `route:cache`, `view:cache`
6. **Automatic Dokku Deploy (on `main` push):** Pushes the verified code to the Dokku production container.

---

## 2. Configuring GitHub Secrets for Automatic Deployment

To enable Stage 2 (automatic push to Dokku), add two secrets in your GitHub repository:

1. Go to your repository on GitHub:
   `https://github.com/NabilDawud/meshwar/settings/secrets/actions`
2. Click **New repository secret**:
   - **`DOKKU_HOST`**:
     ```
     apps.taqat.academy
     ```
   - **`DOKKU_SSH_PRIVATE_KEY`**:
     Paste your SSH private key that has deploy access to the Dokku server (`dokku@apps.taqat.academy`).

*(If these secrets are not configured, the CI quality checks will still run and protect your codebase, and the deploy job will output a friendly notice).*

---

## 3. Manual Dokku Deployment (Alternative)

If you prefer to deploy manually from your terminal:

```bash
# Add Dokku remote once (if not already added)
git remote add dokku dokku@apps.taqat.academy:meshwar

# Push main to deploy
git push dokku main
```

---

## 4. Production Environment Variables Checklist

Ensure these key variables are set on the Dokku container (`dokku config:set meshwar KEY=VALUE`):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://meshwar.apps.taqat.academy`
- `DB_CONNECTION=mysql`
- `DB_HOST=<mysql-host>`
- `DB_DATABASE=meshwar`
- `DB_USERNAME=<mysql-user>`
- `DB_PASSWORD=<mysql-password>`
- `GOOGLE_CLIENT_ID=<your-google-oauth-client-id>`
- `GOOGLE_CLIENT_SECRET=<your-google-oauth-client-secret>`
- `PORT=80`
- `QA_MODE=0`
- `DEMO_MODE=0`
