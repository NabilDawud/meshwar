# Sub-Route Stations, Segmented Fare Calculation & Directional Vehicle Matching Design

## 1. Intent & Problem Statement
In Gaza's daily transit ecosystem (along Salah al-Din and Al-Rasheed corridors), microbuses and shared transit vehicles serve dozens of intermediate stops between major hubs (e.g., Rafah $\rightarrow$ Khan Younis $\rightarrow$ Deir al-Balah $\rightarrow$ Gaza City). 

Currently:
- Drivers can add multiple stops with cumulative fares, but riders only see and book the full line from start to finish.
- Riders who search for intermediate segments (e.g., from Khan Younis to Deir al-Balah) cannot see the specific segment price or select intermediate boarding/dropoff points.
- Routes do not track directional status (Outbound vs. Return), meaning riders have no way of knowing whether a van on the line is driving toward them or heading in the opposite direction.

This feature introduces:
1. **Accurate Sub-Route Segment Fare Calculation:** Calculating the exact leg price based on stop fare deltas ($|\text{Fare}_{\text{to}} - \text{Fare}_{\text{from}}|$).
2. **Route Two-Way vs. One-Way Specification:** Allowing drivers to mark their route as a Round-Trip (Two-Way) or Single Direction (One-Way).
3. **Live Trip Direction Heading:** A one-tap toggle on the driver dashboard indicating whether the active trip is Outbound (ذهاب) or Return (إياب).
4. **Smart Prioritized Vehicle Matching:** Showing matching-direction vehicles first with a prominent `متجه لوجهتك الآن 🟢` badge, while keeping return-leg vehicles accessible with `على خط العودة 🔄` and a "matching direction only" toggle.
5. **Interactive Seat Booking Modal:** Enabling riders to choose exact boarding and dropoff stops with real-time seat and fare calculations.

---

## 2. Approaches Considered

### Option 1: Strict Direction Matching Only
- *Description:* Filter out any vehicle whose current active heading does not match the rider's travel direction.
- *Trade-off:* Cleanest mental model, but risks returning "0 vehicles found" during turnaround times when a driver is just 2–5 minutes away from switching to the return leg.

### Option 2: Smart Prioritized Matching with Direction Toggle (Selected)
- *Description:* 
  - Rank vehicles heading in the rider's direction at the top with a green `متجه لوجهتك الآن 🟢` badge.
  - Show vehicles on the reverse leg below with a `على خط العودة 🔄` badge and direct call capability.
  - Provide a 1-tap filter toggle `[✓ نفس الاتجاه فقط]` (enabled by default) so the rider can easily view or hide opposite-leg vehicles.
- *Reason for Selection:* Best matches the real-world operational pattern of Gaza transit lines, preventing false "no vehicles" dead ends while maintaining maximum clarity.

---

## 3. Data Schema Changes

### `driver_routes` table
Add the following columns via migration:
- `is_two_way` (boolean, default: `true`): Indicates if the line operates as a two-way round-trip or one-way.
- `active_direction` (string/enum: `'outbound' | 'return'`, default: `'outbound'`): The current driving direction of an active trip.

---

## 4. Segment Fare Calculation Logic

Given a route with stops $S_0, S_1, \dots, S_n$, where each stop has a cumulative fare `fare_ils` from the origin $S_0$:
- For a rider boarding at stop $S_i$ and disembarking at stop $S_j$:
  $$\text{Unit Fare} = |\text{Fare}(S_j) - \text{Fare}(S_i)|$$
  $$\text{Total Fare} = \max(\text{Minimum Fare (2 ₪)}, \text{Unit Fare} \times \text{Seats})$$
- **Symmetry:**
  - Outbound ($S_1 \rightarrow S_2$): $|5 - 3| = 2 \text{ ₪}$
  - Return ($S_2 \rightarrow S_1$): $|3 - 5| = 2 \text{ ₪}$
- **Fallback:** If intermediate stop fares are zero or equal, fallback to standard base stop fare or minimum transit fare (2 ₪).

---

## 5. Live Search & Directional Filtering (`ActiveVehicleController`)

When a rider searches with `from` and `to`:
1. Find the index of stop matching `from` ($idx_{\text{from}}$) and stop matching `to` ($idx_{\text{to}}$) in the route's stops array.
2. Determine rider travel direction:
   - If $idx_{\text{from}} < idx_{\text{to}}$: Rider is traveling **Outbound** (ذهاب).
   - If $idx_{\text{from}} > idx_{\text{to}}$: Rider is traveling **Return** (إياب).
3. Check route compatibility:
   - If route is `is_two_way == false` and rider wants Return: exclude from results.
   - If route is `is_two_way == true`:
     - If `active_direction == rider_direction`: `direction_match = 'active'` (`متجه لوجهتك الآن 🟢`).
     - If `active_direction != rider_direction`: `direction_match = 'return_leg'` (`على خط العودة 🔄`).
4. If request has `same_direction_only=1`, filter out `return_leg` vehicles.
5. Attach `segment_fare_ils`, `segment_pickup`, `segment_dropoff`, and `direction_match` to each formatted vehicle in the API response.

---

## 6. User Interface & Experience

### A. Driver Dashboard & Route Builder
1. **Route Builder (`driver/route.blade.php`):**
   - Add a radio toggle:
     - ⇄ **خط سير ذهاب وإياب (اتجاهين)** — Default
     - ➔ **خط سير اتجاه واحد فقط**
2. **Driver Dashboard (`driver/dashboard.blade.php`):**
   - Under the active trip card, provide a direction switcher button:
     - ➡️ **رحلة الذهاب (من البداية للنهاية)**
     - ⬅️ **رحلة العودة (بالاتجاه المعاكس)**
   - Updates `active_direction` via API (`POST /api/driver/route/direction`).

### B. Rider Home & Search (`rider/home.blade.php`)
1. **Directional Badge on Vehicle Cards:**
   - Prominent badge indicating `متجه لوجهتك الآن 🟢` or `على خط العودة 🔄`.
   - Clear display of the calculated segment fare for the searched leg (e.g. `الأجرة لمشوارك: 2 ₪`).
2. **Interactive Seat Reservation Sheet / Modal:**
   - Clicking *"احجز مقعدك"* opens a modal containing:
     - 🟢 **نقطة الركوب (Pickup):** Dropdown of route stops (preselected).
     - 🔴 **نقطة النزول (Dropoff):** Dropdown of route stops (preselected).
     - 👥 **عدد المقاعد (Seats):** 1 to remaining free seats.
     - 💰 **الأجرة المحسوبة ديناميكياً:** Displays the exact segment fare in real-time.
     - Confirmation button to create the booking.

---

## 7. Verification & Testing Plan
1. **Unit & Feature Tests:**
   - Test segment fare calculation formula for forward and reverse directions.
   - Test search filtering for two-way routes and one-way routes.
   - Test driver direction toggle API and persistence.
   - Test booking creation with sub-route segment stops and correct fares.
2. **Regression:**
   - Verify all 90 existing tests pass.
