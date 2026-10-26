# Gaza Transit Domain Reference

## 1. Arterial Corridors & Bidirectional Topography (<->)
Routes in the Gaza Strip are predominantly linear north-south arteries bisected by key east-west junctions:
- **Salah al-Din Road (طريق صلاح الدين)**: The central spine connecting Rafah <-> Khan Younis <-> Deir al-Balah <-> Middle Area <-> Gaza City.
- **Al-Rashid Road (شارع الرشيد الساحلي)**: The coastal axis running along the Mediterranean shoreline (<->).
- **Key Cross Connectors**: Connecting coastal areas to the central spine.

Every fixed route is bidirectional (<->) with an ordered sequence of recognized landmarks/intersections.

## 2. No Platform Fares (With Special Offer Exception) & No Speculative Time
- **Zero Fixed Route Fare Calculations**: The platform strictly avoids computing fares, seat costs, or multi-leg price totals on daily routes. Public transit fares are paid in cash in person.
- **Special Offer Price Exception**: For private charters / special requests (مراسلة), drivers submit their own direct, binding price quote (in ILS / ₪) as part of their bid. This is a single agreed trip price, not a complex platform fare table.
- **Real-World Positions, Never Guesswork**: Do not display artificial "Estimated Time of Arrival" (ETA) or speculative transit durations. Show exactly **where the vehicle was last recorded and how many minutes ago** (e.g., "آخر موقع: دوار الكويت، منذ 4 دقائق").
- **GPS Sync Reality**: On mobile browsers, location updates broadcast while the driver has the web app open. If Android screen/browser holds foreground sync, coordinates refresh continuously; iOS deep background tracking will be handled in a dedicated future Swift app.

## 3. Pre-Trip Rider Confirmation & Cancellation Workflow
- **Pre-Departure Check**: Before tapping "Start Trip", the driver dashboard displays the list of confirmed riders with direct `tel:` call buttons.
- **No-Show / Unready Cancellations**: If a driver calls a rider and the rider is unready or unresponsive, the driver can cancel that specific booking.
- **Rider Alert**: The cancelled rider's app immediately updates with an explanatory status banner: *"تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة"* so there is no ambiguity.

## 4. Route Drift Detection & Rider Notifications
- Drivers often must take unexpected detours (sand roads, checkpoints, diversions) without manually editing their route on the dashboard.
- **Drift Logic**: If a vehicle with confirmed bookings drifts away from its route corridor stops beyond a reasonable buffer, the system flags the vehicle as `drifting` (انحراف عن المسار).
- **Rider Alert**: All riders with active bookings on that vehicle receive an immediate contextual banner: *"المركبة غيرت مسارها حالياً — اضغط للاتصال بالسائق"* with a direct `tel:` tap-to-call button.

## 2. Vehicle Dynamics: The 'Ajala (عجلة) & One-Vehicle Rule
- **One Vehicle Per Driver Account**: Strict 1:1 relationship between a driver profile and their registered vehicle. No fleet management or multi-vehicle complexity.
- **The 'Ajala (عجلة)**: A modified passenger trailer or open-air cart towed by a motor vehicle. It is currently the primary mass-transit vehicle in several areas.
- **Capacity**: Manually calibrated by each driver (may vary from 6 to 20+ riders depending on cart size).
- **Visual Recognition**: Because distinct license plates or livery are often absent or obscured, the driver must upload high-contrast exterior photos of their specific 'Ajala so passengers can immediately spot it approaching.

## 3. Special Requests / Private Charters (مراسلة)
- **Rider Flow**:
  1. Rider publishes a private trip intent: Origin, Destination, Desired Departure Window, Seat Count, Luggage Notes.
  2. The intent appears on the driver board for eligible private car owners.
  3. Drivers post binding offers specifying: Offered Price (ILS), Vehicle Type & Photos, Driver Phone Number.
  4. In-App Direct Chat: Rider and bidding drivers can message directly before commitment.
  5. Acceptance: Rider accepts a single offer; rider's contact details unlock for that chosen driver.

## 4. Privacy & Sensitive Data Boundaries
- **No Residual Tracking**: As soon as a ride concludes or cancels, purge active coordinate tracking records.
- **Rider Anonymity**: Browsing riders do not expose phone numbers or personal handles to public feeds.
- **Explicit Geolocation**: Never silently sample user GPS. Prompt with clear Palestinian Arabic explanation before polling `navigator.geolocation`.

## 5. Driver Availability & Fleet State Machine
- **Route Editing & Dynamic Switching**:
  - Drivers can change their active route (e.g. changing from Route A -> B to Route C -> D) and daily operating hours whenever they choose.
  - Route modification is accessible directly from the driver home dashboard with instant update propagation.
- **Unified Workdays & Hours Interface**:
  - Drivers set their regular working days of the week (e.g., Saturday through Thursday) and active hours in a single simple window.
  - **Urgent Adjustments**: Any urgent change (e.g. taking tomorrow off, starting late, finishing early) is completed from the exact same interface by toggling the day or updating hours directly, without complex separate leave requests.
- **Temporary Route Deviations & Off-Route**:
  - Drivers can temporarily deviate from their assigned route or mark themselves "Off-Route / On Errand" (خارج الخط حالياً) for a specific number of hours.
- **Vehicle Maintenance Status**:
  - Vehicles maintain an explicit status: `active` | `maintenance` | `inactive`.
  - When marked `maintenance` (تحت الصيانة):
    - Automatically pauses all active route listings and prevents accepting new seat bookings or charter offers.
    - Driver dashboard displays an active maintenance banner with a single-tap "Mark Ready for Service" toggle (جاهز للعمل).
