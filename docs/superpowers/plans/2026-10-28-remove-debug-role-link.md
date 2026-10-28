# Implementation Plan: Clean Login Screen & Remove Debug Role Test Link

- **Date:** 2026-10-28
- **Feature:** Clean Login Screen & Remove Debug Role Test Link
- **Objective:** Eliminate the legacy developer test link (`دخول مستخدم جديد...`) from the login screen across all environments (local, QA, and production) to ensure a clean, professional user experience.

---

## 1. Problem Statement
The login screen currently contains a legacy developer test link (`دخول مستخدم جديد بدون دور محدد (اختبار شاشة التسجيل)`) rendered inside `resources/views/auth/login.blade.php`. Because `QA_MODE=1` is active on Dokku and `local` is active locally, this link displays on both environments. Real users should only interact with Google OAuth and the demo role buttons. The link has no operational value and causes confusion.

---

## 2. File Changes
- **Create:** `tests/Feature/LoginViewTest.php`
  - Assert that the login screen renders the official Google login button and demo rider/driver buttons.
  - Assert that the legacy test link (`اختبار شاشة التسجيل` / `دخول مستخدم جديد`) is **never** present in the rendered HTML regardless of `QA_MODE` or environment.
- **Modify:** `resources/views/auth/login.blade.php`
  - Remove lines 42–46 containing the debug anchor tag.
- **Artifact:** Update Antigravity task artifact checklist (`task.md`).

---

## 3. Step-by-Step Execution Plan

### Task 1: Write the Failing Test (Red)
- Create `tests/Feature/LoginViewTest.php` with:
  - `test_login_page_renders_cleanly_without_developer_role_choice_link()`
- Run: `php artisan test --filter=LoginViewTest --compact`
- Expected: Test fails because the link is currently in `login.blade.php`.

### Task 2: Implement the Fix (Green)
- Edit `resources/views/auth/login.blade.php` to remove the debug link block.
- Run: `php artisan test --filter=LoginViewTest --compact`
- Expected: Test passes.

### Task 3: Style & Code Review (Refactor)
- Run: `vendor/bin/pint --dirty --format agent`
- Ensure formatting adheres to Laravel Pint standards.

### Task 4: Systematic Verification & Evidence
- Run the full test suite: `php artisan test --compact`
- Verify 100% tests pass (80 tests).
- Stage, commit with message `fix(auth): remove legacy role choice test link from login screen`.
- Push to `origin main`.
