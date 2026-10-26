# Mishwar (مشوار) Comprehensive Architecture & Implementation Plan

## 1. Architecture & Stack Foundations
- **Backend**: Laravel 13 running on PHP 8.4.
- **Database**: MySQL 9.7 (`meshwar` database, UTF8mb4 Unicode).
- **Authentication**: 
  - **Laravel Fortify** (headless backend authentication).
  - **Laravel Socialite** (Google OAuth with automatic local mock fallback in development, auto-switching to real Google OAuth when `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are provided in `.env`).
  - Single login screen for everyone ("Continue with Google"). On first login, the user selects either **Rider** (راكب) or **Driver** (سائق). The selection is permanently stored on the user record; future logins immediately route to their respective home screen.
- **Frontend**: Blade + Tailwind CSS (RTL layout using logical properties: `ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`) + Alpine.js for lightweight client interactivity.
- **Maps**: MapLibre GL JS with vector tiles and `@mapbox/mapbox-gl-rtl-text` for full Arabic bidirectional label rendering.
- **Deployment & QA Contract**:
  - Dokku server destination: `https://mishwar.apps.taqat.academy`.
  - Production container: PHP 8.4 FrankenPHP / Nginx multi-stage `Dockerfile` with startup database migration gate (`php artisan migrate --force`).
  - Health check endpoint: `GET /api/health` returning `{"ok":true,"commit":env('GIT_REV')}`.
  - QA login endpoint: `GET /api/qa-login?role=driver|rider` (active only when `QA_MODE=1`, returns 404 otherwise).

---

## 2. Gaza Transit Domain Specifications

1. **Vehicles & Single-Vehicle Rule**:
   - Strict **1:1 relationship between Driver and Vehicle**. No fleet management overhead.
   - Types:
     - **Car** (سيارة ملاكي / عمومي): 4–7 seats.
     - **Bus / Minibus** (باص / ميكروباص): Variable capacity.
     - **'Ajala (عجلة)**: A passenger trailer or open-air cart towed by a motor vehicle. Driver-calibrated capacity and mandatory exterior street photos uploaded by the driver so riders recognize it on the street.
2. **Corridors & Bidirectional Routes (`<->`)**:
   - Core arteries: **Salah al-Din Road (طريق صلاح الدين)** and **Al-Rashid Coastal Road (شارع الرشيد الساحلي)**, plus key cross-corridors.
   - Routes run **back and forth (`<->`)** as an ordered sequence of recognized OpenStreetMap landmarks and intersections.
3. **Operational State Machine (Dashboard Controls)**:
   - **On Default Route** (عامل على الخط المعتاد): Standard daily schedule active.
   - **Temporary Custom Route** (مسار مؤقت): Driver can change the route (e.g. from A → B to C → D) and daily operating hours at any time with immediate effect.
   - **Off-Route / On Errand** (خارج الخط حالياً): Temporarily hides the vehicle from public transit searches for a selected number of hours.
   - **Days Off & Skip Work**: Driver sets regular weekly workdays (e.g., Sat–Thu) and hours in a single unified window. Urgent days off or hour modifications are handled directly in that same window.
   - **Vehicle Maintenance Status**: Vehicle status enum (`active` | `maintenance`). When set to `maintenance` (تحت الصيانة), all active route listings and bookings are frozen until toggled back.
4. **Fares Policy**:
   - **Fixed Daily Routes**: **NO platform fares**. Fares are not calculated, estimated, or collected by the app. Fares fluctuate and vary by driver and vehicle, and are handled in cash offline.
   - **Special Offers (مراسلة) Exception**: Drivers **do** include their own binding total price quote (in ILS / ₪) when placing a bid on a private request.
5. **Pre-Trip Confirmation & Cancellations**:
   - Before tapping "Start Trip", the driver dashboard displays the confirmed riders list with direct `tel:` call buttons.
   - If a rider is unready or unreachable after calling, the driver can cancel that individual booking.
   - The rider's app immediately displays an in-app notice: *"تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة"*.
6. **No Speculative Time Estimations & GPS Reality**:
   - No algorithmic ETAs or speculative duration guesses. The UI strictly displays **last known recorded position and its age in minutes** (e.g., *"آخر موقع: دوار الكويت، منذ 4 دقائق"*).
7. **Route Drift Alerts**:
   - If a vehicle with active bookings drifts off its designated corridor stops beyond a reasonable buffer, riders with bookings receive an instant banner: *"المركبة غيرت مسارها حالياً — اضغط للاتصال بالسائق"* with a direct `tel:` button.
8. **Privacy Standards**:
   - Rider phone number is revealed to the driver *only* after booking confirmation or an accepted private offer. Driver phone is shown on active offers and confirmed bookings.
   - Rider live location is shared only after explicit per-request consent, and tracking records are purged once a ride concludes.

---

## 3. Offline-First & Mobile PWA Engineering

1. **App Shell & Data Caching (Workbox)**:
   - Full HTML shell, CSS, JavaScript, web fonts, and vector map styles cached locally via Service Worker.
   - Application boots with zero internet, presenting the cached interface and last-known data with data age indicators.
2. **Local-First Writes (IndexedDB Outbox)**:
   - Every user mutation (booking, offer, request, chat message) writes immediately to local IndexedDB.
   - Optimistic UI update: displays *"تم الحفظ على الهاتف، سيتم الإرسال فور توفر الإنترنت"* (Saved on phone, will send when online).
   - Syncs automatically when `navigator.onLine` fires.
3. **Low-Bandwidth Delta Sync**:
   - Synchronization payload exchanges only incremental deltas since the last timestamp sync token.
   - Byte budget strictly under **10 KB per sync cycle**.
4. **Photo Download & Upload Gating**:
   - Photos are completely segregated from the JSON sync stream.
   - Downloaded photos are cached permanently on the device.
   - Photos upload and download *only* on unmetered Wi-Fi connections. On cellular links, placeholders appear with a manual tap-to-download button (*"تنزيل الصور الآن"*) and a *"Wi-Fi فقط"* toggle.
5. **Deterministic Booking Conflicts**:
   - For seat reservations: **The server authoritative clock decides; first booking processed wins**.
   - If a conflict occurs upon offline sync, the client notifies the rider immediately and presents alternative options.
6. **iPhone Safari Native Feel**:
   - Meta viewport: `width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover`.
   - Safe area padding: `env(safe-area-inset-top)` for top status bar, `env(safe-area-inset-bottom)` for bottom navigation.
   - Dynamic viewport height: strictly `100dvh` (or `h-dvh` in Tailwind).
   - Accidental zoom suppression: font-size is at least `16px` on all inputs, textareas, and selects; `touch-action: manipulation` on all buttons.
   - Native controls: Fixed bottom tab bar for main navigation, bottom slide-up sheets instead of centered popup dialogs, 44px minimum touch targets, skeletons instead of blocking spinners.
   - Arabic typography: System font stack (`-apple-system, BlinkMacSystemFont, "Segoe UI", "IBM Plex Sans Arabic", "Cairo", sans-serif`) with `line-height: 1.65` to prevent clipped diacritics.

---

## 4. User Journeys & Screens Registry (`qa/routes.txt`)

### User Journeys

#### A. Guest Journey (الزائر)
1. Opens `/`: Lands on interactive map of the Gaza Strip showing active vehicles, last known coordinates, and position age.
2. Explores transit corridors (Salah al-Din / Al-Rashid) and views stop sequences.
3. Taps "حجز مقعد" (Book Seat) → Prompted to sign in: *"سجل الدخول للمتابعة"*.
4. Taps "متابعة باستخدام Google".

#### B. First-Time Authentication Journey (تسجيل الدخول واختيار الدور)
1. User logs in via Google OAuth (or Mock Dev Login).
2. Callback detects new user without a role → Redirects to `/auth/role-choice`.
3. Screen displays two large, touch-friendly cards:
   - **أنا راكب (I am a Rider)**: Book seats, track trips, post private charter requests.
   - **أنا سائق (I am a Driver)**: Register vehicle, setup daily corridor route, manage bookings, bid on private requests.
4. User selects role → Saved permanently to MySQL `users` table → User redirected to Rider Home or Driver Dashboard.
5. All future logins automatically skip role choice and route directly.

#### C. Driver Journey (السائق)
1. **Vehicle Registration** (`/driver/vehicle`):
   - Selects vehicle type: Car (4–7 seats), Bus/Minibus, or 'Ajala (عجلة).
   - Enters seat capacity.
   - Uploads high-contrast exterior photos for street recognition.
2. **Route & Schedule Setup** (`/driver/route`):
   - Sets main corridor (Salah al-Din or Al-Rashid) with ordered stops, start point, and first trip time.
   - Sets weekly workdays (e.g., Sat–Thu) and daily active hours in a single unified window.
3. **Daily Operations Dashboard** (`/driver`):
   - Quick operational toggles: On Default Route, Switch to Temporary Route, Off-Route for X hours, Day Off, or Under Maintenance.
   - Pre-Trip Manifest: Reviews booked riders, calls each rider (`tel:` tap) to confirm readiness.
   - Can cancel unready/no-show riders with one tap.
   - Taps "بدء الرحلة" (Start Trip) → broadcasts live location while app is open.
   - Taps "إنهاء الرحلة" (End Trip) → closes trip and prompts to rate riders.
4. **Special Requests Board** (`/driver/offers`):
   - Views open private charter requests (مراسلة) in Gaza.
   - Submits a custom price quote in ILS (₪).
   - In-app chat with rider to finalize details.

#### D. Rider Journey (الراكب)
1. **Rider Home** (`/rides`):
   - Map displays active vehicles along corridors with last known location and position age.
   - Taps a vehicle to view driver name, vehicle type ('Ajala/Car/Bus), and recognition photos.
2. **Booking Flow** (`/bookings`):
   - Selects departure stop and destination stop.
   - Confirms seat reservation (atomic server-side lock; first booking wins).
   - Booking card displays confirmed status and driver phone with direct `tel:` tap-to-call link.
   - Receives route drift alert if driver takes unexpected detour.
   - Receives cancellation alert if driver marks unready.
3. **Live Location Consent**:
   - If driver requests rider location, rider sees explicit popup: *"هل توافق على مشاركة موقعك الحالي مع السائق لتسهيل اللقاء؟"*
   - Rider taps Yes/No. If Yes, temporary coordinates share for max 15 minutes or until trip starts.
4. **Special Requests (مراسلة)** (`/requests/new`):
   - Posts private trip: Origin, Destination, Date, Time Window, Seats needed, Notes.
   - Receives driver offers with price quotes in ILS and vehicle photos.
   - Chats directly with bidding drivers (`/chat/{offer_id}`).
   - Accepts offer → driver phone unlocked, charter confirmed.
5. **Trip History & Mutual Rating** (`/history`):
   - Views completed trips.
   - Rates driver from 1 to 5 stars with optional comment.

### QA Routes Registry (`qa/routes.txt`)

```text
guest    /                       LandingMapScreen
guest    /login                  LoginScreen
guest    /auth/google/callback   GoogleAuthCallback
guest    /auth/role-choice       RoleSelectionScreen
guest    /api/health             HealthEndpoint
guest    /api/qa-login           QALoginEndpoint
rider    /rides                  RiderMapHome
rider    /plan                   TripPlannerScreen
rider    /bookings               MyBookingsScreen
rider    /requests               MyRequestsScreen
rider    /requests/new           NewRequestScreen
driver   /driver                 DriverDashboard
driver   /driver/vehicle         VehicleSetupScreen
driver   /driver/route           RouteEditorScreen
driver   /driver/offers          OfferBoardScreen
all      /chat/{offer_id}        OfferChatScreen
all      /history                TripHistoryScreen
```

---

## 5. Seven Milestones: Technical Implementation & Terminal Contracts

### Milestone 1: Foundation Shell, Fortify & Socialite Auth, Health & Contract
- **Branch**: `m1-shell-auth` | **PR Title**: `M1: Shell, Fortify & Socialite Auth, Health Endpoint, and QA Contract`
- **Database Schema**:
  - `users`: `id`, `name`, `email`, `phone`, `role` (enum: `'rider'`, `'driver'`, `null`), `google_id`, `avatar`, `created_at`, `updated_at`.
- **Backend Components**:
  - `config/fortify.php` and `app/Providers/FortifyServiceProvider.php` (headless registration/login pipeline).
  - `app/Http/Controllers/Auth/GoogleAuthController.php`: Handles redirect and callback. In development without Google keys, provides one-click mock login.
  - `app/Http/Middleware/EnsureRoleSelected.php`: Intercepts authenticated users with `role === null` and routes to `/auth/role-choice`.
  - Health check endpoint: `GET /api/health` returning `{"ok":true,"commit":env('GIT_REV')}`.
  - QA login endpoint: `GET /api/qa-login?role=driver|rider` (active only when `QA_MODE=1`).
- **Frontend Views**:
  - `resources/views/layouts/app.blade.php`: Mobile PWA base layout with iOS safe areas (`env(safe-area-inset-top)` / `bottom`), `100dvh`, and RTL direction.
  - `resources/views/auth/login.blade.php`: Clean Palestinian Arabic login view with "متابعة باستخدام Google" button and dev bypass links.
  - `resources/views/auth/role-choice.blade.php`: Two large interactive cards for Rider / Driver selection.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=Milestone1Test
  curl -s http://127.0.0.1:8000/api/health
  curl -s "http://127.0.0.1:8000/api/qa-login?role=rider"
  ```
- **Expected CLI Output**:
  - `{"tool":"phpunit","result":"passed","tests":4,"passed":4}`
  - `{"ok":true,"commit":"..."}`
  - `{"ok":true,"role":"rider","user_id":...}`
- **Cut/Deferral Policy**: Real Google Cloud keys deferred (runs in mock mode until `.env` keys provided).

---

### Milestone 2: Driver Vehicle Registration, Photos & Route Setup
- **Branch**: `m2-driver-vehicle-route` | **PR Title**: `M2: Driver Vehicle Registration, Photos, Route Stops, and Workday Controls`
- **Database Schema**:
  - `vehicles`: `id`, `driver_id` (foreignId, unique 1:1), `type` (enum: `'car'`, `'bus'`, `'ajala'`), `capacity` (int), `photos` (json), `status` (enum: `'active'`, `'maintenance'`), timestamps.
  - `routes`: `id`, `driver_id` (foreignId), `name` (string), `corridor` (enum: `'salah_al_din'`, `'al_rashid'`, `'cross'`), `start_point` (string), `first_trip_time` (time), `work_days` (json), `active_status` (enum: `'default'`, `'custom'`, `'off_route'`), `off_route_until` (timestamp, nullable), timestamps.
  - `route_stops`: `id`, `route_id` (foreignId), `stop_order` (int), `stop_name` (string), `lat` (decimal 10,7), `lng` (decimal 10,7), timestamps.
- **Backend Components**:
  - `app/Http/Controllers/Driver/VehicleController.php`: Store/update vehicle details, handle photo storage.
  - `app/Http/Controllers/Driver/RouteController.php`: Ordered stop sequence creation, workdays and operating hours configuration.
  - `app/Console/Commands/CheckDriverRouteCommand.php`: Artisan command `meshwar:check-driver-route --driver={id}` to output driver vehicle and stop sequence.
- **Frontend Views**:
  - `resources/views/driver/vehicle.blade.php`: Vehicle type picker (including 'Ajala cart with photo upload preview), capacity stepper.
  - `resources/views/driver/route.blade.php`: Ordered stop builder along Salah al-Din / Al-Rashid, workdays checkboxes (Sat–Thu), operating hours inputs, and single-tap operational toggles.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=DriverVehicleRouteTest
  php artisan meshwar:check-driver-route --driver=1
  ```
- **Expected CLI Output**:
  - `PASS (tests for 1:1 vehicle rule, photo storage, route stop order, and maintenance toggles)`.
  - ASCII table showing registered vehicle, capacity, operating corridor, and ordered stops.
- **Cut/Deferral Policy**: Multiple photo uploads cut to single primary exterior photo.

---

### Milestone 3: Rider Map & Active Vehicle Tracking API
- **Branch**: `m3-rider-map-api` | **PR Title**: `M3: MapLibre GL RTL Map, Active Vehicles GeoJSON API, and Position Age Display`
- **Database Schema**:
  - `vehicle_locations`: `id`, `vehicle_id` (foreignId), `lat` (decimal 10,7), `lng` (decimal 10,7), `recorded_at` (timestamp), timestamps.
- **Backend Components**:
  - `app/Http/Controllers/Api/VehicleLocationController.php`:
    - `POST /api/driver/location`: Driver app broadcasts foreground coordinates.
    - `GET /api/vehicles/active`: Returns GeoJSON `FeatureCollection` of active vehicles, filtering out vehicles marked `maintenance` or `off_route`, and computing `age_minutes` since `recorded_at`.
- **Frontend Views**:
  - `resources/views/rides/index.blade.php`: Fullscreen MapLibre GL container, RTL text plugin integration, bottom drawer card showing tapped vehicle info (driver name, vehicle type, photos, last seen location and age).
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=ActiveVehiclesApiTest
  curl -s http://127.0.0.1:8000/api/vehicles/active
  ```
- **Expected CLI Output**:
  - `PASS (tests filtering out maintenance vehicles and verifying GeoJSON format)`.
  - Valid GeoJSON output with `features[].properties.age_minutes` and coordinates.
- **Cut/Deferral Policy**: Vehicle clustering cut; direct SVG point markers with vehicle type icons only.

---

### Milestone 4: Trip Planner Logic & Offline Transfer Graph Search
- **Branch**: `m4-trip-planner` | **PR Title**: `M4: Bidirectional Corridor Pathfinding, 1-Transfer Graph Search, and Offline Cache`
- **Backend Components**:
  - `app/Services/TripPlannerService.php`:
    - Graph search algorithm over active `route_stops`.
    - Identifies:
      1. Direct routes connecting origin and destination along the same corridor (`<->`).
      2. 1-transfer connections at intersection junctions (e.g. Salah al-Din cross-connector to Al-Rashid).
    - Sorts options strictly by earliest departure time without speculative ETAs.
    - Zero platform fare computation.
  - `app/Console/Commands/PlanTripCommand.php`: Artisan command `meshwar:plan-trip {from} {to}` to test pathfinding directly in the CLI.
  - `GET /api/routes/graph`: Dumps lightweight JSON of all active route corridors (< 10 KB) for client-side IndexedDB caching.
- **Frontend Views**:
  - `resources/views/rides/plan.blade.php`: Origin/Destination selector (Gaza OpenStreetMap landmarks), itinerary card showing departure, stops, and transfer point if applicable.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=TripPlannerGraphTest
  php artisan meshwar:plan-trip "رفح" "غزة"
  ```
- **Expected CLI Output**:
  - `PASS (tests direct corridor matching and 1-transfer junction resolution)`.
  - Terminal prints top 3 routes with vehicle IDs, departure times, and transfer points.
- **Cut/Deferral Policy**: Multi-transfer connections cut to maximum 1 transfer point.

---

### Milestone 5: Seat Booking, Pre-Trip Confirmation & Route Drift Alert
- **Branch**: `m5-booking-confirmation-drift` | **PR Title**: `M5: Atomic Seat Reservation, Pre-Trip Manifest, Driver Cancellation, and Drift Alerts`
- **Database Schema**:
  - `bookings`: `id`, `rider_id` (foreignId), `route_id` (foreignId), `seats` (int), `status` (enum: `'confirmed'`, `'cancelled_by_driver'`, `'cancelled_by_rider'`, `'completed'`), `cancellation_reason` (string, nullable), timestamps.
  - `location_shares`: `id`, `rider_id` (foreignId), `driver_id` (foreignId), `status` (enum: `'requested'`, `'active'`, `'ended'`), `expires_at` (timestamp), timestamps.
- **Backend Components**:
  - `app/Http/Controllers/BookingController.php`:
    - `POST /api/bookings`: Atomic seat reservation using database transaction and `lockForUpdate()`. First booking processed wins; rejects when capacity is exceeded.
    - `POST /api/driver/bookings/{id}/cancel`: Driver cancels unready rider with cancellation reason.
  - `app/Services/RouteDriftService.php`: Compares vehicle live coordinate against corridor route stops. If distance exceeds buffer (e.g., 800m), flags booking as `drifting`.
  - `app/Http/Controllers/LocationShareController.php`: Driver requests rider live location; rider accepts or rejects; 15-minute expiration window.
- **Frontend Views**:
  - `resources/views/driver/manifest.blade.php`: Pre-trip rider confirmation list with direct `tel:` call buttons and "إلغاء لعدم الجاهزية" action.
  - `resources/views/rider/bookings.blade.php`: Active booking card with driver phone link, drift alert banner, and cancellation notification banner.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=BookingConcurrencyTest
  php artisan test --filter=RouteDriftAlertTest
  ```
- **Expected CLI Output**:
  - `PASS (confirms concurrent seat lock prevents overselling, confirms cancellation notifies rider, and confirms drift detection triggers alert)`.
- **Cut/Deferral Policy**: 15-minute countdown auto-expiration cut to manual end-trip button.

---

### Milestone 6: Special Requests (مراسلة), Offers & Direct Chat
- **Branch**: `m6-special-requests-chat` | **PR Title**: `M6: Private Charter Requests, Binding Driver Price Bids, and Direct Chat`
- **Database Schema**:
  - `special_requests`: `id`, `rider_id` (foreignId), `origin_name` (string), `destination_name` (string), `departure_date` (date), `departure_window` (string), `seats_needed` (int), `notes` (text, nullable), `status` (enum: `'open'`, `'accepted'`, `'cancelled'`), timestamps.
  - `special_offers`: `id`, `request_id` (foreignId), `driver_id` (foreignId), `price_ils` (decimal 8,2), `notes` (text, nullable), `status` (enum: `'pending'`, `'accepted'`, `'rejected'`), timestamps.
  - `chat_messages`: `id`, `offer_id` (foreignId), `sender_id` (foreignId), `message` (text), timestamps.
- **Backend Components**:
  - `app/Http/Controllers/SpecialRequestController.php`: Rider creates private request; drivers view board.
  - `app/Http/Controllers/SpecialOfferController.php`: Driver submits binding ILS price bid; rider accepts offer (unlocks rider phone to driver).
  - `app/Http/Controllers/ChatController.php`: Sequential text message exchange between rider and bidding driver.
- **Frontend Views**:
  - `resources/views/rider/requests.blade.php`: Create special request form, list submitted requests.
  - `resources/views/driver/offers.blade.php`: Driver opportunity board with quick ILS bid modal.
  - `resources/views/chat/thread.blade.php`: Lightweight mobile chat thread with tap-to-call link on accepted offers.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=SpecialRequestOfferChatTest
  ```
- **Expected CLI Output**:
  - `PASS (verifies request publishing, ILS offer bid, offer acceptance unlocking contact details, and chat messaging)`.
- **Cut/Deferral Policy**: Unread message count badges cut; standard sequential message list only.

---

### Milestone 7: Trip History & 1–5 Mutual Rating System
- **Branch**: `m7-history-ratings` | **PR Title**: `M7: Completed Trips History and 1-5 Star Mutual Rating System`
- **Database Schema**:
  - `ratings`: `id`, `booking_id` (foreignId, nullable), `special_offer_id` (foreignId, nullable), `rater_id` (foreignId), `ratee_id` (foreignId), `score` (tinyint unsigned, 1–5), `comment` (text, nullable), timestamps.
- **Backend Components**:
  - `app/Http/Controllers/HistoryController.php`: Fetches completed bookings and charter trips for authenticated rider or driver.
  - `app/Http/Controllers/RatingController.php`: Submits 1–5 score, updates rider/driver cached rating average.
  - `app/Console/Commands/UserScoreCommand.php`: Artisan command `meshwar:user-score --user={id}` to verify user score in terminal.
- **Frontend Views**:
  - `resources/views/history/index.blade.php`: Completed trip cards with route corridor, date, driver/rider name, and rating prompt.
  - `resources/views/history/rate.blade.php`: 1–5 star rating sheet.
- **Terminal CLI Verification**:
  ```bash
  php artisan test --filter=TripRatingTest
  php artisan meshwar:user-score --user=1
  ```
- **Expected CLI Output**:
  - `PASS (verifies rating creation, constraint to 1-5, and average score calculation)`.
  - Terminal prints: `User #1 Rating: 4.8 / 5.0 (based on 12 trips)`.
- **Cut/Deferral Policy**: Written feedback comments cut; integer 1–5 star rating only.

---

## 6. The Cut / Deferral List (What We Do NOT Build)
1. **Fixed Route Fares**: Cut entirely. Public transit corridor fares are negotiated/paid in cash offline.
2. **Speculative ETAs**: Cut entirely. Only real last-recorded positions and their age in minutes are displayed.
3. **Fleet / Multi-Vehicle**: Cut entirely. Strict 1:1 limit of one vehicle per driver account.
4. **Separate Leave Request Modules**: Cut entirely. Workdays, active hours, and urgent days off are handled in a single unified interface.
5. **Deep Background Geolocation on iOS**: Cut from PWA. Foreground browser location used; background tracking deferred to future native Swift app.
6. **Artificial Intelligence Free-Text Parsing**: Cut from core MVP; structured landmark selectors used.
