# Implementation Plan: Phone Number Update Rate Limit (Option 4)

- **Date:** 2026-10-28
- **Feature:** Phone Number 24-Hour Rate Limiting with In-Ride Lock
- **Objective:** Restrict phone number modifications to a maximum of 3 changes within any sliding 24-hour window, while retaining the existing lock that prevents phone or name updates during active rides.

---

## 1. Requirements & Behavior
1. **Sliding 24-Hour Window:** A user may update their phone number up to 3 times in a rolling 24-hour window.
2. **Typo Protection:** Users have immediate room for correction (changes 1, 2, and 3 happen instantly).
3. **Threshold Enforcement:** Upon the 4th attempt to modify the phone number within 24 hours, validation fails with an Arabic error message explaining the daily limit.
4. **Name-Only Exemption:** If a user only updates their name without altering their phone number, the phone rate limit counter must **not** increment or block the update.
5. **Active Ride Lock Retained:** Any update attempt while a passenger is boarded or a ride is in progress remains strictly blocked.

---

## 2. File Changes
- **Modify:** `app/Http/Controllers/ProfileController.php`
  - Implement cache-based sliding window tracker (`user_phone_updates_{id}`).
  - Only increment when `$validated['phone'] !== $user->phone`.
  - Throw validation exception on `phone` if limit (3) is reached.
- **Modify:** `tests/Feature/ProfileTest.php`
  - Add `test_phone_update_is_rate_limited_to_three_times_in_24_hours()`.
  - Add `test_updating_name_only_does_not_consume_phone_rate_limit()`.
- **Artifact:** Update `task.md` checklist in Antigravity.

---

## 3. Step-by-Step TDD Execution

### Task 1: Write Failing Tests (Red)
- Add tests in `tests/Feature/ProfileTest.php` asserting that changing the phone number 3 times succeeds, and the 4th attempt within 24 hours fails with a validation error on `phone`.
- Assert that updating the name 5 times without changing the phone number continues to succeed.
- Run: `php artisan test --filter=ProfileTest --compact` $\rightarrow$ Confirm tests fail.

### Task 2: Implement Rate Limiter in ProfileController (Green)
- Add rate limit verification in `ProfileController::update()` before saving.
- Record the timestamp upon successful phone number alteration.
- Run: `php artisan test --filter=ProfileTest --compact` $\rightarrow$ Confirm tests pass.

### Task 3: Refactor & Code Formatting
- Run: `vendor/bin/pint --dirty --format agent`.

### Task 4: Systematic Verification & Evidence
- Full test suite execution: `php artisan test --compact`.
- Commit and push to `origin main`.
