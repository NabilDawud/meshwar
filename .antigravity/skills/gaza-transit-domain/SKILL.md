---
name: gaza-transit-domain
description: Use when designing, implementing, or reviewing public transport features for the Gaza Strip, including vehicle types (cars, buses, Ajala carts), fixed daily driver routes, leg fares in shekels (ILS), private trip requests (مراسلة), Palestinian Arabic terminology, OSM place names, and sensitive rider/driver location privacy rules.
---

# Gaza Transit Domain

Domain-specific rules and constraints for public transit and private rides in the Gaza Strip.

## Quick Reference Rules

1. **Vehicles**:
   - Standard passenger cars (4–7 seats).
   - Buses and minibuses (variable capacity).
   - **Ajala (أجلة)**: Passenger cart towed by a vehicle; driver-set capacity and distinct driver-taken photos for street identification.
2. **Driver Routes**:
   - Each driver registers exactly **ONE main daily route** (ordered stops along Salah al-Din, al-Rashid coastal road, or cross streets) with a start point and first-trip departure time.
3. **Fares & Currency**:
   - Priced per seat, per leg, in New Israeli Shekels (**ILS / ₪**). Multi-leg journeys may span multiple vehicles.
4. **Special Requests (مراسلة)**:
   - Riders post private requests (origin, destination, date, time, seats).
   - Private drivers submit offers (driver name, vehicle photos, phone, price). Rider chats in-app and accepts one.
5. **Localization & Names**:
   - UI exclusively in **Palestinian Arabic (RTL)** for non-technical users. Technical jargon remains in code.
   - All place names and coordinates must come strictly from **OpenStreetMap** for Gaza. Never hallucinate locations.
6. **Strict Privacy**:
   - Minimize stored location data. Never retain rider location history after trip termination.
   - Rider live location shared only upon explicit per-trip consent.
   - Rider phone number revealed to driver *only* after booking confirmation or accepted offer. Driver phone shown with offer.

See detailed specifications in [domain-rules.md](./references/domain-rules.md).
