# Multi-Route Weekly Schedules and Advance Booking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow drivers in Gaza to save multiple corridors and map them to a 7-day weekly schedule with operating hours and day-off statuses, and enable riders to search vehicles and reserve seats for future dates (Today, Tomorrow, Day After Tomorrow) with smart shift time-window filtering.

**Architecture:**
1. Schema & Models: Relax `driver_routes.driver_id` unique constraint for multi-route support, create `driver_schedules` table for 7-day recurring schedules, and add `travel_date` to `bookings`.
2. Driver Schedule Engine: API for managing a driver's route library and weekly 7-day schedule matrix with quick bulk-apply.
3. Forward Search & Time Window Filtering: `ActiveVehicleController` resolves routes based on the requested date's day-of-week, checks working hours window (`start_time <= time <= end_time`), and calculates seat inventory isolated per date.
4. Advance Seat Booking: `BookingController` records `travel_date`, confirms seats instantly, and separates passenger lists in the driver manifest by date.
5. User Experience: Interactive 1-tap day pills (`اليوم`, `غداً`, `بعد غد`) on rider home, working hours badge, and 7-day schedule editor for drivers.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS v4, Alpine.js, PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-10-30-multi-route-weekly-schedules-and-advance-booking-design.md`](file:///D:/ai-projects/meshwar/docs/superpowers/specs/2026-10-30-multi-route-weekly-schedules-and-advance-booking-design.md)

---

## Global Constraints
- Strict adherence to PHP 8.4 typing, PSR-12/Pint formatting, and existing codebase conventions.
- Arabic RTL layout integrity across all views.
- Strict backwards compatibility: existing drivers with single routes must continue operating smoothly without requiring immediate schedule configuration.
- Past dates (`travel_date < today()`) must be rejected.
- All 108 existing tests must continue to pass without regression.

---

## Review Focus
1. **Seat count isolation**: Reserving a seat for tomorrow must never decrement available seats for today.
2. **Weekly Day-of-Week mapping**: A driver who is off on Fridays (`is_day_off = true`) must never appear when riders search for Friday rides.
3. **Time-window matching**: Searching for 09:00 AM must match a driver working 07:00 AM to 03:00 PM, and reject a driver working 01:00 PM to 08:00 PM.
4. **Fallback for unconfigured schedules**: If a driver has a registered route but has not configured a weekly schedule, they must still appear for "Today" so existing drivers are never broken.
5. **Offline outbox integrity**: Booking an advance date ride while offline must preserve `travel_date` in `localStorage` outbox and send it on reconnect.

---

### Task 1: Database Migrations & Models for Schedules, Multi-Routes & Booking Dates

**Files:**
- Create: `database/migrations/2026_10_30_100000_update_driver_routes_table_for_multiple_routes.php`
- Create: `database/migrations/2026_10_30_101000_create_driver_schedules_table.php`
- Create: `database/migrations/2026_10_30_102000_add_travel_date_to_bookings_table.php`
- Create: `app/Models/DriverSchedule.php`
- Modify: `app/Models/DriverRoute.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Booking.php`
- Test: `tests/Feature/MultiRouteScheduleSchemaTest.php`

**Interfaces:**
- Produces: `DriverSchedule` model with `driver()` and `route()` relationships.
- Produces: `User::driverRoutes(): HasMany`, `User::activeDriverRoute(): ?DriverRoute`, `User::schedules(): HasMany`.
- Produces: `Booking::$travel_date` cast as date.

**Steps:**
- [ ] **Step 1: Write failing schema test** in `tests/Feature/MultiRouteScheduleSchemaTest.php` testing:
  - A driver can create multiple `DriverRoute` records.
  - `DriverSchedule` stores day_of_week (0-6), start_time, end_time, is_day_off, route_id.
  - `Booking` persists and casts `travel_date`.
- [ ] **Step 2: Run test to verify RED state:** `php artisan test --filter=MultiRouteScheduleSchemaTest`.
- [ ] **Step 3: Create migrations**:
  - `2026_10_30_100000_update_driver_routes_table_for_multiple_routes.php`: drop unique index on `driver_id`, add `is_active` (boolean, default true).
  - `2026_10_30_101000_create_driver_schedules_table.php`: create table with `driver_id`, `day_of_week`, `route_id`, `start_time`, `end_time`, `is_day_off`.
  - `2026_10_30_102000_add_travel_date_to_bookings_table.php`: add `travel_date` (date, default today) and composite index `[route_id, travel_date, status]`.
- [ ] **Step 4: Run migrations:** `php artisan migrate`.
- [ ] **Step 5: Create and update models:**
  - Create `app/Models/DriverSchedule.php` with fillable and casts (`is_day_off` => bool).
  - Update `User.php` with `driverRoutes(): HasMany`, `schedules(): HasMany`.
  - Update `DriverRoute.php` with `schedules(): HasMany`.
  - Update `Booking.php` with `'travel_date' => 'date:Y-m-d'`.
- [ ] **Step 6: Run test to verify GREEN state:** `php artisan test --filter=MultiRouteScheduleSchemaTest`.
- [ ] **Step 7: Commit:** `git commit -m "feat(schema): add multi-route support, driver_schedules table, and booking travel_date"`.

---

### Task 2: Driver Schedule Controller & Multi-Route APIs

**Files:**
- Create: `app/Http/Controllers/Driver/DriverScheduleController.php`
- Modify: `app/Http/Controllers/Driver/DriverRouteController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DriverScheduleApiTest.php`

**Interfaces:**
- Consumes: `DriverSchedule`, `DriverRoute`, `User`.
- Produces: `GET /api/driver/schedules`, `POST /api/driver/schedules`, `GET /api/driver/routes`.

**Steps:**
- [ ] **Step 1: Write failing test** in `tests/Feature/DriverScheduleApiTest.php`:
  - `GET /api/driver/schedules` returns all 7 days with day names in Arabic.
  - `POST /api/driver/schedules` upserts weekly schedule matrix.
  - Driver can retrieve list of all their created routes (`GET /api/driver/routes`).
  - Rider cannot access driver schedule endpoints (403).
- [ ] **Step 2: Run test to verify RED state:** `php artisan test --filter=DriverScheduleApiTest`.
- [ ] **Step 3: Create `DriverScheduleController.php`**:
  - `index()`: builds 7-day structure (Saturday to Friday) with assigned route info, times, and day-off flags.
  - `store()`: validates array of schedules, upserts entries per day_of_week.
- [ ] **Step 4: Update `DriverRouteController.php`**:
  - Add `index()` returning all routes belonging to the authenticated driver.
- [ ] **Step 5: Register routes in `routes/web.php`**.
- [ ] **Step 6: Run test to verify GREEN state:** `php artisan test --filter=DriverScheduleApiTest`.
- [ ] **Step 7: Commit:** `git commit -m "feat(driver): add driver schedules and multi-route management APIs"`.

---

### Task 3: Forward Date & Time Window Filtering in ActiveVehicleController

**Files:**
- Modify: `app/Http/Controllers/Rides/ActiveVehicleController.php`
- Test: `tests/Feature/AdvanceDateVehicleSearchTest.php`

**Interfaces:**
- Consumes: `ActiveVehicleController::index(Request $request)`.
- Produces: Query support for `date=YYYY-MM-DD` and working window `time=HH:MM`.

**Steps:**
- [ ] **Step 1: Write failing test** in `tests/Feature/AdvanceDateVehicleSearchTest.php`:
  - Searching with `date=tomorrow` returns vehicles scheduled for tomorrow's day of week.
  - A driver marked `is_day_off = true` for tomorrow is excluded.
  - Time filter: Searching `time=09:00` matches a shift `07:00 - 15:00`, and rejects `16:00 - 20:00`.
  - Available seats calculation on future dates counts only bookings on that specific date.
  - For future dates, `is_future_date = true` is passed and live GPS coordinates are suppressed.
- [ ] **Step 2: Run test to verify RED state:** `php artisan test --filter=AdvanceDateVehicleSearchTest`.
- [ ] **Step 3: Implement date resolution and time-window filtering in `ActiveVehicleController.php`**:
  - Parse `date` (defaulting to today in Gaza timezone).
  - Map `date` to day-of-week (`0` to `6`).
  - Resolve route from `driver_schedules` matching that day-of-week. Fallback to active route if today and no schedule configured.
  - Exclude if `is_day_off`.
  - Fix time filter: check `$time >= $start && $time <= $end`.
  - Calculate `free_seats` filtered by `where('travel_date', $targetDate)`.
  - Attach `operating_window` (`start_time`, `end_time`) to formatted payload.
- [ ] **Step 4: Run test to verify GREEN state:** `php artisan test --filter=AdvanceDateVehicleSearchTest`.
- [ ] **Step 5: Commit:** `git commit -m "feat(search): add forward date resolution and operational time-window filtering"`.

---

### Task 4: Advance Seat Booking & Date-Segmented Manifest in BookingController

**Files:**
- Modify: `app/Http/Controllers/BookingController.php`
- Test: `tests/Feature/AdvanceBookingAndManifestTest.php`

**Interfaces:**
- Consumes: `BookingController::store()`, `BookingController::driverManifest()`.
- Produces: Date-isolated booking and manifest tabs.

**Steps:**
- [ ] **Step 1: Write failing test** in `tests/Feature/AdvanceBookingAndManifestTest.php`:
  - Rider can book a seat for `travel_date=tomorrow`.
  - Booking tomorrow for 2 seats does NOT decrement today's free seats.
  - Past dates are rejected with validation error (422).
  - `GET /api/driver/manifest?date=tomorrow` returns tomorrow's confirmed passengers.
- [ ] **Step 2: Run test to verify RED state:** `php artisan test --filter=AdvanceBookingAndManifestTest`.
- [ ] **Step 3: Update `BookingController.php`**:
  - In `store()`: validate `travel_date` (`nullable|date|after_or_equal:today`). Default to `today()->toDateString()`.
  - Check free seats for the specific `travel_date`.
  - Store `travel_date` on the `Booking` record.
  - In `driverManifest()`: support `date` parameter (default today). Return manifest statistics and counts for `today_count` and `tomorrow_count`.
- [ ] **Step 4: Run test to verify GREEN state:** `php artisan test --filter=AdvanceBookingAndManifestTest`.
- [ ] **Step 5: Commit:** `git commit -m "feat(booking): implement date-isolated advance seat reservations and manifest filtering"`.

---

### Task 5: Rider UI - Day Selector Pills, Time Filter & Advance Booking Modal

**Files:**
- Modify: `resources/views/rider/home.blade.php`
- Modify: `resources/views/rider/bookings.blade.php`

**Steps:**
- [ ] **Step 1: Update search bar in `rider/home.blade.php`**:
  - Add interactive Day Selector Pills: `[ اليوم ]`, `[ غداً ]`, `[ بعد غد ]`, `[ 📅 تاريخ آخر ]`.
  - Time input hooked up to operating hours window.
  - Map behavior: show live GPS pins when "اليوم" is selected; show scheduled corridor route when future days are selected.
- [ ] **Step 2: Update vehicle cards in `rider/home.blade.php`**:
  - Display shift hours badge: `⏰ يعمل من 7:00 ص حتى 3:30 م`.
  - Display date-specific available seats.
- [ ] **Step 3: Update Booking Sheet Modal in `rider/home.blade.php`**:
  - Display chosen travel date prominently with Arabic day name (e.g. `موعد الرحلة: غداً (السبت 31 أكتوبر)`).
  - Submit `travel_date` in `bookSeat()` API payload and save in `localStorage` outbox if offline.
- [ ] **Step 4: Update `rider/bookings.blade.php`**:
  - Display `travel_date` on booking cards with clear Arabic label.
- [ ] **Step 5: Test UI manually in browser and verify styling**.
- [ ] **Step 6: Commit:** `git commit -m "feat(ui): add day selector pills, shift hours badges, and advance booking sheet"`.

---

### Task 6: Driver UI - Weekly Schedule Planner & Manifest Date Tabs

**Files:**
- Modify: `resources/views/driver/dashboard.blade.php`
- Modify: `resources/views/driver/route.blade.php`
- Modify: `resources/views/driver/manifest.blade.php`

**Steps:**
- [ ] **Step 1: Add Weekly Schedule Planner section to `driver/dashboard.blade.php`**:
  - 7-day card matrix (Saturday through Friday).
  - Dropdown to assign route per day.
  - Time range pickers for shift (`من` and `إلى`).
  - Quick toggle for Day Off / Vacation (`🏖️ إجازة`).
  - 1-tap quick action: `⚡ تطبيق المواعيد على جميع أيام العمل` (Sat–Thu).
- [ ] **Step 2: Update `driver/manifest.blade.php`**:
  - Add Date Tabs: `[ ركاب اليوم ]` and `[ حجوزات الغد المسبقة ]`.
  - Driver can review advance passenger manifest with pickup and dropoff stops.
- [ ] **Step 3: Commit:** `git commit -m "feat(driver-ui): add weekly schedule planner and advance manifest date tabs"`.

---

### Task 7: Comprehensive Verification & CI/CD Deployment

**Steps:**
- [ ] **Step 1: Format code with Laravel Pint:** `vendor/bin/pint --format agent`.
- [ ] **Step 2: Run full test suite:** `php artisan test --compact` (ensure all tests pass 100%).
- [ ] **Step 3: Compile frontend assets:** `npm run build`.
- [ ] **Step 4: Push to `origin main` and verify GitHub Actions CI/CD deployment completes 100% green**.
