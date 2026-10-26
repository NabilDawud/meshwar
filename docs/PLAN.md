# Mishwar (مشوار) Implementation Plan

## Architecture & Stack
- **Backend**: Laravel 13, PHP 8.4, MySQL (`meshwar`)
- **Authentication**: Laravel Fortify (headless) + Laravel Socialite (Google OAuth with local dev mock fallback)
- **Frontend**: Blade + Tailwind CSS (RTL logical properties) + Alpine.js
- **Maps**: MapLibre GL JS with Vector Tiles & `@mapbox/mapbox-gl-rtl-text`
- **Testing & QA Contract**: Autonomous terminal tests (`php artisan test`), `qa/routes.txt`, `/api/health`, and zero-credential `/api/qa-login` (active when `QA_MODE=1`).

---

## Milestone Checklist

- [ ] **M1: Shell, Fortify & Socialite Auth, Health & Contract**
  - **Branch**: `m1-shell-auth`
  - **Scope**: Fortify headless auth, Socialite Google login (with local mock fallback), role selection (`rider` / `driver`), Blade layout with iOS PWA meta/safe-areas, `/api/health`, `/api/qa-login`, `qa/routes.txt`.
  - **Terminal Check**: `php artisan test --filter=Milestone1Test` & `curl http://127.0.0.1:8000/api/health`
  - **Deferred if late**: Real Google Cloud keys (runs in mock mode until `.env` keys provided).

- [ ] **M2: Driver Vehicle Registration, Photos & Route Setup**
  - **Branch**: `m2-driver-vehicle-route`
  - **Scope**: Single-vehicle registration (car, bus, عجلة), photo upload, ordered stop sequence on Salah al-Din / al-Rashid corridors, unified workdays/hours editor.
  - **Terminal Check**: `php artisan test --filter=DriverVehicleRouteTest` & `php artisan meshwar:check-driver-route --driver=1`
  - **Deferred if late**: Multiple photo uploads cut to single primary exterior photo.

- [ ] **M3: Rider Map & Active Vehicle Tracking API**
  - **Branch**: `m3-rider-map-api`
  - **Scope**: MapLibre GL JS view, `/api/vehicles/active` returning GeoJSON of active vehicles with last-known position and relative age in minutes.
  - **Terminal Check**: `php artisan test --filter=ActiveVehiclesApiTest` & `curl http://127.0.0.1:8000/api/vehicles/active`
  - **Deferred if late**: Vehicle clustering cut; direct SVG point markers only.

- [ ] **M4: Trip Planner Logic & Offline Transfer Graph Search**
  - **Branch**: `m4-trip-planner`
  - **Scope**: Bidirectional transfer pathfinding over stops (direct routes and 1-transfer connections, sorted by earliest departure/arrival). Offline-cached route graph. Zero fare calculation.
  - **Terminal Check**: `php artisan test --filter=TripPlannerGraphTest` & `php artisan meshwar:plan-trip "رفح" "غزة"`
  - **Deferred if late**: Multi-transfer connections cut to max 1 transfer.

- [ ] **M5: Seat Booking, Pre-Trip Confirmation & Route Drift Alert**
  - **Branch**: `m5-booking-confirmation-drift`
  - **Scope**: Server-side atomic seat reservation (first booking wins). Driver pre-trip rider confirmation list with tap-to-call (`tel:`) and no-show cancellation alert. Driver location request toggle with 15-minute expiry. Route drift detection alert for booked riders.
  - **Terminal Check**: `php artisan test --filter=BookingConcurrencyTest` & `php artisan test --filter=RouteDriftAlertTest`
  - **Deferred if late**: 15-minute countdown auto-expiration cut to manual end-trip button.

- [ ] **M6: Special Requests (مراسلة), Offers & Direct Chat**
  - **Branch**: `m6-special-requests-chat`
  - **Scope**: Rider posts private charter request. Drivers submit binding price quotes (in ILS / ₪). Dedicated in-app chat thread per offer. Offer acceptance unlocks rider phone.
  - **Terminal Check**: `php artisan test --filter=SpecialRequestOfferChatTest` & `php artisan meshwar:simulate-offer --request=1 --price=150`
  - **Deferred if late**: Unread message badges cut; sequential message list only.

- [ ] **M7: Trip History & 1–5 Mutual Rating System**
  - **Branch**: `m7-history-ratings`
  - **Scope**: Completed trips feed for riders and drivers. Dual-sided 1–5 star rating system with average score calculations.
  - **Terminal Check**: `php artisan test --filter=TripRatingTest` & `php artisan meshwar:user-score --user=1`
  - **Deferred if late**: Written feedback comments cut; integer 1–5 star rating only.

---

## The Cut / Deferral List
1. **Fixed Route Fares**: Cut entirely. Daily transit fares are handled in cash in person.
2. **Speculative ETAs**: Cut entirely. Only real last-recorded positions and their age (in minutes) are displayed.
3. **Fleet / Multi-Vehicle**: Cut entirely. Strict 1:1 limit of one vehicle per driver account.
4. **Separate Leave Request Modules**: Cut entirely. Workdays, active hours, and urgent days off are handled in a single unified interface.
5. **Deep Background Geolocation on iOS**: Cut from PWA. Foreground browser location used; background tracking deferred to future native Swift app.
