---
name: gaza-transit-domain
description: Use when designing, implementing, or reviewing public transport features for the Gaza Strip, including vehicle types (cars, buses, Ajala carts), fixed daily driver routes, leg fares in shekels (ILS), private trip requests (مراسلة), Palestinian Arabic terminology, OSM place names, and sensitive rider/driver location privacy rules.
---

# Gaza Transit Domain

Domain-specific rules and constraints for public transit and private rides in the Gaza Strip.

## Quick Reference Rules

1. **Vehicles (One Vehicle Per Account)**:
   - Exactly **one vehicle per driver account** (1:1). No multi-vehicle or fleet abstractions.
   - Vehicle types: Standard car (4–7 seats), Bus/minibus (variable capacity), or **'Ajala (عجلة)** (passenger cart with driver-set capacity and identifier photos).
2. **Driver Routes, Workdays & Operating Hours**:
   - **Route Flexibility**: Drivers can easily change their route (e.g., from A -> B to C -> D) and daily operating hours at any time directly from their dashboard.
   - **Unified Schedule Control**: Drivers configure their weekly work days and operating hours in a single, simple window. Any urgent changes or day-off adjustments are made directly in this same window without navigating complex sub-menus.
   - **Temporary Route Deviations**: Ability to temporarily deviate, adjust hours, or go "off-route" for errands.
   - **Vehicle Maintenance**: One-tap toggle to mark vehicle "Under Maintenance" (تحت الصيانة) to freeze active schedules and seat bookings.
3. **Fares Policy**:
   - **Fixed Daily Routes**: The platform does **not** calculate, estimate, or enforce fares. Regular public transit fares are handled offline in cash.
   - **Special Offers (مراسلة) Exception**: Drivers **do** include their own custom total price quote (in ILS / ₪) when submitting a bid on a private request.
4. **Pre-Trip Confirmation & Driver Cancellations**:
   - Before tapping "Start Trip", the driver reviews all booked riders and calls them to confirm readiness.
   - If a rider is unreachable or not ready, the driver can cancel that individual booking.
   - The rider is immediately notified in-app of the cancellation.
5. **No Speculative Time Estimations & GPS Reality**:
   - Never show speculative or theoretical travel time estimates. Display **real last-known position and its age** (e.g., "At Netzarim Junction, 3 mins ago").
   - Routes run **back and forth (<->)** along main corridors.
6. **Route Drift Alerts**:
   - If a vehicle with active bookings drifts off its designated route stops, the platform alerts booked riders immediately so they can tap to call the driver.
7. **Special Requests (مراسلة)**:
   - Riders post private requests (origin, destination, date, time, seats).
   - Private drivers submit offers with a price quote, vehicle photos, and phone. Rider chats in-app and accepts one.
7. **Strict Privacy & OpenStreetMap**:
   - UI exclusively in **Palestinian Arabic (RTL)** for non-technical users. Technical jargon remains in code.
   - All place names and coordinates must come strictly from **OpenStreetMap** for Gaza. Never hallucinate locations.
6. **Strict Privacy**:
   - Minimize stored location data. Never retain rider location history after trip termination.
   - Rider live location shared only upon explicit per-trip consent.
   - Rider phone number revealed to driver *only* after booking confirmation or accepted offer. Driver phone shown with offer.

See detailed specifications in [domain-rules.md](./references/domain-rules.md).
