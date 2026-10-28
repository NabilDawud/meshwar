# Production UX & Data Accuracy Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve five production usability and data accuracy defects: default 4.9 driver rating, automatic fallback to Bani Suheila GPS coordinates, 0 ILS trip completion fare, driver dashboard UI flash / unselected status, and 2-3s delay on "My Bookings".

**Architecture:** 
1. Eloquent model accessor refinement: Return real calculated rating or `null`/`0.0` for new drivers, with Arabic UI badges for unrated profiles ("جديد / لم يُقيّم بعد").
2. Robust Geolocation handling: Remove silent mock coordinate broadcasts on GPS error; prompt driver/rider to enable GPS and retain last known verified position without corrupting database landmarks.
3. Fare Calculation & Completion Amount: Compute line booking fares from route stops upon booking creation, and provide an interactive fare confirmation input in the driver manifest upon trip completion.
4. Immediate CSS Cloaking: Register `[x-cloak]` in both `app.css` and the Blade `<head>` and render initial status button styles server-side to prevent unstyled state flashes.
5. Zero-Latency Bookings: Preload rider bookings directly in the Blade view payload and cache in `localStorage`, eliminating the secondary HTTP waterfall and restricting route drift calculation to active rides.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS v4, Alpine.js, PHPUnit.

---

## Global Constraints
- All PHP code must adhere to PHP 8.4 syntax, strict types, and Laravel Pint formatting.
- Arabic language and RTL orientation (`dir="rtl"`) must be maintained across all UI enhancements.
- No regression of existing 82 tests in the PHPUnit test suite.

---

### Task 1: Fix Driver Rating Default (Real Calculation & Unrated State)
**Files:**
- Modify: `app/Models/User.php:65-70`
- Modify: `app/Http/Controllers/SpecialRequestController.php:442`
- Modify: `resources/views/rides/request_detail.blade.php:120-125`
- Modify: `resources/views/driver/history.blade.php:48-52, 160-165`
- Modify: `resources/views/rides/history.blade.php:108-112`
- Test: `tests/Feature/DriverRatingAccuracyTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/DriverRatingAccuracyTest.php` verifying:
  - New driver without reviews returns `null` or `0.0` for `average_rating`, NOT `4.9`.
  - Special request offer returns real driver rating or `null`/`0.0`.
  - Seeded/reviewed driver returns exact calculated average.
- [ ] Run test to verify RED state: `php artisan test --filter=DriverRatingAccuracyTest`.
- [ ] In `app/Models/User.php`: change `getAverageRatingAttribute()` to return `$avg !== null ? round((float) $avg, 1) : 0.0`.
- [ ] In `app/Http/Controllers/SpecialRequestController.php`: pass `$driver->average_rating` instead of hardcoded `4.9`.
- [ ] In Blade views: format unrated state with `★ جديد (لم يُقيّم بعد)` or `—` when rating is 0.0 or null.
- [ ] Run `php artisan test --filter=DriverRatingAccuracyTest` to verify GREEN state.
- [ ] Commit: `git commit -m "fix(rating): return 0.0/unrated badge for drivers without reviews instead of 4.9"`.

---

### Task 2: Fix Location GPS Fallback & Bani Suheila Overwrite
**Files:**
- Modify: `resources/views/driver/dashboard.blade.php:360-380`
- Modify: `resources/views/rider/bookings.blade.php:385-400`
- Test: `tests/Feature/LocationBroadcastSafetyTest.php`

**Steps:**
- [ ] Write test in `tests/Feature/LocationBroadcastSafetyTest.php` verifying driver position endpoint only accepts valid inputs and does not corrupt route landmarks when GPS is unavailable.
- [ ] In `driver/dashboard.blade.php`: remove `err => sendPos(31.3456, 34.3182, 'دوار بني سهيلا')`. On error callback, set status to `تعذر تحديد إشارة GPS بدقة، يرجى تفعيل الموقع` and DO NOT send fake coordinates to the server.
- [ ] In `rider/bookings.blade.php`: remove `mockLat = 31.3456... sendUpdate(mockLat, mockLng)`. On error, warn user and do not broadcast fake Bani Suheila coordinates.
- [ ] Run test: `php artisan test --filter=LocationBroadcastSafetyTest`.
- [ ] Commit: `git commit -m "fix(gps): remove synthetic Bani Suheila coordinates fallback on GPS error"`.

---

### Task 3: Automatic Booking Fare Calculation & Driver Completion Fare Input
**Files:**
- Modify: `app/Http/Controllers/BookingController.php:35-100, 305-350`
- Modify: `resources/views/rider/home.blade.php:480-495`
- Modify: `resources/views/driver/manifest.blade.php:115-130, 260-280`
- Test: `tests/Feature/BookingFareCalculationTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/BookingFareCalculationTest.php` verifying:
  - When booking without explicit `fare_ils`, the controller auto-calculates fare from the route's matching stop price multiplied by seats.
  - Driver can complete booking with confirmed/custom fare amount.
  - Trip history reflects exact fare, never default 0.
- [ ] Run test to verify RED state: `php artisan test --filter=BookingFareCalculationTest`.
- [ ] In `BookingController::store()`: If `fare_ils` is not provided, look up `dropoff_stop` or last stop in `$route->stops` and calculate `$fare * $seatsRequested`.
- [ ] In `BookingController::completeBooking()`: Accept optional `fare_ils` in request body, updating the booking's fare if provided.
- [ ] In `resources/views/rider/home.blade.php`: Calculate and pass `fare_ils` from the selected destination stop.
- [ ] In `resources/views/driver/manifest.blade.php`: Prompt or provide a quick modal/confirmation for fare collection upon completing dropoff ("تأكيد النزول وتأكيد الأجرة").
- [ ] Run `php artisan test --filter=BookingFareCalculationTest` to verify GREEN state.
- [ ] Commit: `git commit -m "fix(fare): auto-calculate line booking fare and enable driver completion amount confirmation"`.

---

### Task 4: Fix Alpine Cloaking & Driver Status Selection Flash
**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/layouts/app.blade.php:20-30`
- Modify: `resources/views/driver/dashboard.blade.php:30-70`

**Steps:**
- [ ] Add `[x-cloak] { display: none !important; }` to `resources/css/app.css` and directly inside `<head><style>` in `layouts/app.blade.php`.
- [ ] In `driver/dashboard.blade.php`: Add server-rendered Blade conditional class to status buttons so the current route status is styled on initial HTML paint without waiting 1-2s for Alpine.
- [ ] Verify Pint & frontend build: `npm run build`.
- [ ] Commit: `git commit -m "fix(ui): eliminate notification center flash with x-cloak and server-render initial status button state"`.

---

### Task 5: Zero-Delay Bookings Loading & Performance Optimization
**Files:**
- Modify: `routes/web.php:95-98`
- Modify: `app/Http/Controllers/BookingController.php:125-140, 360-370`
- Modify: `resources/views/rider/bookings.blade.php:1-30, 205-235`
- Test: `tests/Feature/RiderBookingsPerformanceTest.php`

**Steps:**
- [ ] Write test in `tests/Feature/RiderBookingsPerformanceTest.php` asserting that `GET /rider/bookings` returns initial bookings directly in view data.
- [ ] In `routes/web.php`: Route `/rider/bookings` to `BookingController::riderBookingsView()`, which passes `$initialBookings` to the Blade view.
- [ ] In `BookingController::formatBookingForRider()`: Only run `RouteDriftService::checkDrift()` if booking status is `confirmed` or `boarded` (skip for `completed`/`cancelled`).
- [ ] In `resources/views/rider/bookings.blade.php`: Initialize Alpine state with `@js($initialBookings)`, cache in `localStorage` (`mishwar_cached_bookings`), and remove initial loading bounce.
- [ ] Run test: `php artisan test --filter=RiderBookingsPerformanceTest`.
- [ ] Commit: `git commit -m "perf(bookings): render initial bookings server-side for zero-latency loading and offline caching"`.

---

### Task 6: Comprehensive Verification & CI/CD Deployment
**Steps:**
- [ ] Run Pint formatter: `vendor/bin/pint --format agent`.
- [ ] Run complete test suite: `php artisan test --compact`.
- [ ] Push to `origin main` and verify GitHub Actions CI/CD run succeeds.
