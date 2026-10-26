# Gaza Transit Domain Reference

## 1. Arterial Corridors & Route Topography
Routes in the Gaza Strip are predominantly linear north-south arteries bisected by key east-west junctions:
- **Salah al-Din Road (طريق صلاح الدين)**: The central spine connecting Rafah, Khan Younis, Deir al-Balah, al-Bureij, al-Nuseirat, and Gaza City.
- **Al-Rashid Road (شارع الرشيد الساحلي)**: The coastal axis running along the Mediterranean shoreline.
- **Key Cross Connectors**: Connecting coastal areas to the central spine (e.g., Netzarim junction axis, Wadi Gaza crossing, Khan Younis western roads).

Every fixed route must be an ordered sequence of recognized landmarks/intersections, preserving transit continuity.

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
