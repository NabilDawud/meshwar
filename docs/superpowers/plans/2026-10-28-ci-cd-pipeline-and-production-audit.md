# Implementation Plan: GitHub Actions CI/CD Pipeline & Production Readiness

- **Date:** 2026-10-28
- **Feature:** Automated CI/CD Quality Gates & Dokku Continuous Deployment
- **Objective:** Establish a production-grade CI/CD pipeline that runs automated linting, frontend compilation, test execution, and production caching on every push and pull request, followed by automated deployment to Dokku on `meshwar.apps.taqat.academy`.

---

## 1. Scope & Deliverables
1. **GitHub Actions Workflow (`.github/workflows/ci-cd.yml`):**
   - **CI Job (`test-and-quality`):**
     - Runs on `ubuntu-latest`.
     - Matrix / Setup: PHP 8.4 + required extensions (`pdo, sqlite3, pdo_sqlite, pdo_mysql, bcmath, mbstring, intl, zip, opcache`).
     - Node.js 24 + npm caching.
     - Code style verification: `vendor/bin/pint --test`.
     - Frontend asset build: `npm ci && npm run build`.
     - Automated test suite: `php artisan test --compact` (in-memory SQLite).
     - Production cache verification: `config:cache`, `route:cache`, `view:cache`.
   - **CD Job (`deploy-dokku`):**
     - Dependent on `test-and-quality` passing.
     - Only runs on `push` to `main`.
     - Deploys to Dokku via SSH using `dokku/github-action@v1.6.0` or SSH action.
     - Gracefully detects if `DOKKU_SSH_PRIVATE_KEY` / `DOKKU_HOST` secrets are configured; if not yet set, logs clear guidance on adding them without failing the CI run.
2. **Environment Documentation (`.env.example`):**
   - Document `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
   - Document `QA_MODE=0`, `DEMO_MODE=0`.
3. **Deployment Guide (`docs/DEPLOYMENT.md`):**
   - Step-by-step setup for GitHub Secrets (`DOKKU_SSH_PRIVATE_KEY`, `DOKKU_HOST`).

---

## 2. File Changes
- **Create:** `.github/workflows/ci-cd.yml`
- **Modify:** `.env.example`
- **Create:** `docs/DEPLOYMENT.md`
- **Artifact:** Update `task.md` checklist in Antigravity.

---

## 3. Step-by-Step Execution Plan

### Task 1: Environment Variable Documentation
- Update `.env.example` to document all Google OAuth and QA/Demo flags.

### Task 2: GitHub Actions CI/CD Workflow
- Create `.github/workflows/ci-cd.yml` with dual-stage pipeline (CI Quality Gate + CD Deployment).

### Task 3: Deployment Documentation
- Create `docs/DEPLOYMENT.md` with instructions on GitHub Repository Secrets configuration.

### Task 4: Code Style & Syntax Audit
- Run `vendor/bin/pint --test` to ensure pristine PHP styling.
- Verify YAML syntax of `.github/workflows/ci-cd.yml`.

### Task 5: Systematic Verification & Git Delivery
- Run full test suite: `php artisan test --compact`.
- Commit and push to `origin main`.
