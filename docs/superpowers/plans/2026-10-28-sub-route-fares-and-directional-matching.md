# Sub-Route Fares and Directional Matching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable riders to search, filter, and book intermediate route stops with automatic segment fare calculation, and support Two-Way (round-trip) vs. One-Way routes with live driver heading status.

**Architecture:** 
1. Database schema expansion: Add `is_two_way` and `active_direction` to `driver_routes`.
2. Segment fare engine: Dynamic calculation $|\text{Fare}(dropoff) - \text{Fare}(pickup)| \times seats$, symmetrical across outbound and return directions.
3. Directional search ranking: `ActiveVehicleController` matches rider's start-to-end vector against driver's current heading, labeling vehicles as active direction (`متجه لوجهتك الآن 🟢`) or return leg (`على خط العودة 🔄`).
4. Driver live direction switcher: Driver dashboard controls to switch heading between Outbound and Return.
5. Interactive booking modal: Rider seat reservation sheet to select specific pickup and dropoff stops with real-time fare preview.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS v4, Alpine.js, PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-10-28-sub-route-fares-and-directional-matching-design.md`](file:///D:/ai-projects/meshwar/docs/superpowers/specs/2026-10-28-sub-route-fares-and-directional-matching-design.md)

---

## Global Constraints
- Adhere strictly to PHP 8.4, strict typing, and Pint code formatting.
- Preserve Arabic RTL layout integrity across all views.
- Ensure all 90 existing tests continue to pass without regressions.

---

## Review Focus
1. Symmetrical segment fares: booking $B \rightarrow C$ and return $C \rightarrow B$ must yield the exact same non-negative fare.
2. One-way routes: must never match reverse searches where dropoff precedes pickup.
3. Live direction accuracy: switching direction in driver dashboard must immediately alter search matching.
4. Intermediate stops booking: rider selecting intermediate stops must save exact pickup/dropoff in `Booking` record.
5. Offline outbox support: seat reservation with custom stops must persist cleanly in `localStorage` when offline.

---

### Task 1: Migration & Model Updates for Directional Routes
**Files:**
- Create: `database/migrations/2026_10_28_220000_add_direction_fields_to_driver_routes_table.php`
- Modify: `app/Models/DriverRoute.php:15-50`
- Test: `tests/Feature/RouteDirectionSchemaTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/RouteDirectionSchemaTest.php` asserting `is_two_way` (defaults to true) and `active_direction` (defaults to 'outbound') exist and cast properly.
- [ ] Run test to verify RED state: `php artisan test --filter=RouteDirectionSchemaTest`.
- [ ] Create migration adding `is_two_way` and `active_direction` to `driver_routes`.
- [ ] Run migration: `php artisan migrate`.
- [ ] Update `app/Models/DriverRoute.php` with `$fillable`, `$casts`, and helper methods (`isTwoWay()`, `isOutbound()`, `isReturn()`, `getActiveDirectionArabicAttribute()`).
- [ ] Run test to verify GREEN state: `php artisan test --filter=RouteDirectionSchemaTest`.
- [ ] Commit: `git commit -m "feat(routes): add is_two_way and active_direction to driver_routes"`.

---

### Task 2: Segment Fare Calculation Engine
**Files:**
- Modify: `app/Models/DriverRoute.php`
- Modify: `app/Http/Controllers/BookingController.php`
- Test: `tests/Feature/SegmentFareCalculationTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/SegmentFareCalculationTest.php` testing:
  - Outbound segment $B \rightarrow C$ calculates $|5 - 3| = 2$ ILS.
  - Return segment $C \rightarrow B$ calculates $|3 - 5| = 2$ ILS.
  - Multiple seats multiply accurately ($2 \times 3 = 6$ ILS).
  - Full route $A \rightarrow D$ calculates full terminal fare.
  - Fallback to minimum 2 ILS if stops have zero/identical fare.
- [ ] Run test to verify RED state: `php artisan test --filter=SegmentFareCalculationTest`.
- [ ] Implement `calculateSegmentFare(?string $pickup, ?string $dropoff, int $seats = 1): float` on `DriverRoute`.
- [ ] Integrate into `BookingController::store()` and `BookingController::completeBooking()`.
- [ ] Run test to verify GREEN state: `php artisan test --filter=SegmentFareCalculationTest`.
- [ ] Commit: `git commit -m "feat(fare): implement symmetrical sub-route segment fare calculation"`.

---

### Task 3: Directional Search Matching in `ActiveVehicleController`
**Files:**
- Modify: `app/Http/Controllers/Rides/ActiveVehicleController.php`
- Test: `tests/Feature/DirectionalSearchFilteringTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/DirectionalSearchFilteringTest.php` verifying:
  - When searching $B \rightarrow C$, vehicle with `active_direction = 'outbound'` is labeled `direction_match: 'active'`.
  - When searching $B \rightarrow C$, vehicle with `active_direction = 'return'` is labeled `direction_match: 'return_leg'`.
  - If route has `is_two_way = false`, reverse searches are excluded.
  - Passing `same_direction_only=1` excludes return-leg vehicles.
  - Formatted response includes `segment_fare_ils` and `direction_match`.
- [ ] Run test to verify RED state: `php artisan test --filter=DirectionalSearchFilteringTest`.
- [ ] Implement directional matching and segment fare payload in `ActiveVehicleController.php`.
- [ ] Run test to verify GREEN state: `php artisan test --filter=DirectionalSearchFilteringTest`.
- [ ] Commit: `git commit -m "feat(search): add directional vehicle ranking and sub-route segment matching"`.

---

### Task 4: Driver Live Direction Control & Route Builder Setup
**Files:**
- Modify: `app/Http/Controllers/Driver/DriverRouteController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/driver/dashboard.blade.php`
- Modify: `resources/views/driver/route.blade.php`
- Test: `tests/Feature/DriverDirectionControlTest.php`

**Steps:**
- [ ] Write failing test in `tests/Feature/DriverDirectionControlTest.php` testing `POST /api/driver/route/direction` updates `active_direction`.
- [ ] Run test to verify RED state: `php artisan test --filter=DriverDirectionControlTest`.
- [ ] Add `updateDirection` in `DriverRouteController.php` and register route in `routes/web.php`.
- [ ] Update `resources/views/driver/dashboard.blade.php` with 1-tap live direction toggle (➡️ ذهاب / ⬅️ إياب).
- [ ] Update `resources/views/driver/route.blade.php` with Two-Way vs. One-Way route selector.
- [ ] Run test to verify GREEN state: `php artisan test --filter=DriverDirectionControlTest`.
- [ ] Commit: `git commit -m "feat(driver): add direction toggle to dashboard and two-way toggle to route builder"`.

---

### Task 5: Interactive Seat Booking Modal & Sub-Route UI on Rider Home
**Files:**
- Modify: `resources/views/rider/home.blade.php`
- Modify: `resources/views/rider/bookings.blade.php`

**Steps:**
- [ ] Update vehicle card in `rider/home.blade.php` to display:
  - Direction badge (`متجه لوجهتك الآن 🟢` vs `على خط العودة 🔄`).
  - Segment fare badge (`الأجرة لمشوارك: X ₪`).
- [ ] Add filter toggle on search bar: `[✓ نفس الاتجاه فقط]`.
- [ ] Build interactive Seat Reservation modal in `rider/home.blade.php`:
  - Boarding stop dropdown.
  - Dropoff stop dropdown.
  - Real-time dynamic segment fare calculation.
  - Seat count selector (1 to free seats).
- [ ] Connect modal confirm button to `bookSeat(vehicle, pickup, dropoff, seats, fare)`.
- [ ] In `rider/bookings.blade.php`, display segment pickup, dropoff, and calculated fare clearly.
- [ ] Commit: `git commit -m "feat(ui): add interactive seat reservation modal with real-time segment fare calculation"`.

---

### Task 6: Comprehensive Verification & CI/CD Deployment
**Steps:**
- [ ] Run Pint formatter: `vendor/bin/pint --format agent`.
- [ ] Run full test suite: `php artisan test --compact`.
- [ ] Verify frontend build: `npm run build`.
- [ ] Push to `origin main` and verify GitHub Actions CI/CD deployment completes 100% green.
