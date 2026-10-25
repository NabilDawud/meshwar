---
name: ship-safely
description: Use when planning milestones, structuring pull requests, managing database migrations with Laravel and MySQL, maintaining QA screen registries (qa/routes.txt), implementing credential-free Laravel QA login endpoints, or running automated unit and feature tests.
---

# Ship Safely (Laravel & MySQL)

Delivery framework and technical contract for shipping atomic milestones to production in Laravel.

## Core Rules

1. **Autonomous Validation**:
   - Because there is no external QA agent or pre-written test runner, Antigravity takes full responsibility for deciding how to test, validate, and structure the code based on the existing repository and files.
2. **Branching & PR Discipline**:
   - **Never push directly to `main`**.
   - Work proceeds strictly in **one branch and one pull request per milestone**.
   - Every PR description must state in plain words:
     1. *What a user can now do*.
     2. *What was cut or deferred*.
     3. *Step-by-step instructions on how to see and verify it*.
3. **The Technical Contract (Laravel 13 & MySQL)**:
   - **Verification Commands**: `php artisan test` (PHPUnit/Pest), `npm run build` (Vite assets).
   - **Database**: Laravel Eloquent migrations targeting **MySQL**.
   - **QA Routes Registry**: Keep `qa/routes.txt` updated with one line per screen: `<guest|driver|rider> <path> <short-name>`.
   - **Zero-Credential QA Login**:
     - `GET /api/qa-login?role=driver|rider` signs in a simulated user of that role instantly.
     - **Safety constraint**: Available ONLY when `config('app.qa_mode')` or `env('QA_MODE') === '1'`. If `QA_MODE` is unset or `0`, it must return an immediate `404 Not Found`. Production must never set `QA_MODE`.
     - In QA mode, the app must boot with zero credentials required.
4. **Milestone Verification Gate**:
   - Every milestone must register all new screens in `qa/routes.txt`.
   - Every milestone must include at least one automated test (`php artisan test`) covering its most complex business rule.
   - Build the smallest thing that works. No speculative abstractions.

See full protocol and checklist in [qa-and-pr-contract.md](./references/qa-and-pr-contract.md).
