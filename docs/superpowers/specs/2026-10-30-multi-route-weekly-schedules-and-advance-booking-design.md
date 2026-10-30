# Multi-Route Weekly Schedules & Advance Date Booking Design Spec

**Date:** 2026-10-30  
**Status:** Draft for Approval  
**Authors:** Antigravity & Nabil Dawud  
**Project:** Mishwar (مشوار غزة)

---

## 1. Executive Summary & Objective

In Gaza, transit drivers frequently operate across different corridors depending on the day of the week (e.g., student demand on university days, coastal traffic on weekends, market days). Currently, Mishwar restricts each driver to a single static route with a single departure time (`first_trip_time`), and riders can only discover or book rides for the immediate present ("Today").

### Goals:
1. **Driver Multi-Route Library**: Drivers can create, name, and store multiple corridors in their account (e.g., *"Rafah – Gaza via Salah al-Din"*, *"Khan Younis – Deir al-Balah via Al-Rashid"*).
2. **7-Day Weekly Shift Schedule**: Drivers can map each day of the week (Saturday through Friday) to a specific route, with custom shift hours (`start_time` to `end_time`) or mark the day as **Day Off / Vacation** (`إجازة أسبوعية`).
3. **Rider Forward Planning (Today / Tomorrow / Day After Tomorrow)**: Riders can easily toggle travel dates (`اليوم`, `غداً`, `بعد غد`, or pick a date) to discover scheduled vehicles in advance.
4. **Smart Time Window Filtering**: Searching for a time (e.g., `09:00 AM`) matches vehicles whose operational shift covers that time (e.g., `07:00 AM` to `03:30 PM`), rather than requiring an exact match on `first_trip_time`.
5. **Date-Scoped Seat Reservations**: Advance bookings are recorded with a `travel_date`. Seat inventory is isolated per date, with instant confirmation and real-time notification to the driver.

---

## 2. Architecture & Database Schema

### 2.1. `driver_routes` Modification
Allow drivers to own multiple distinct routes:
- Drop the `UNIQUE` constraint on `driver_routes.driver_id`.
- Add `is_active` (boolean, default true).
- Existing single-route drivers will seamlessly retain their route as Route #1.

### 2.2. New Table: `driver_schedules`
Stores the driver's weekly recurring 7-day schedule:

| Column | Type | Attributes / Details |
| :--- | :--- | :--- |
| `id` | `unsignedBigInteger` | Primary Key, Auto Increment |
| `driver_id` | `unsignedBigInteger` | Foreign Key -> `users.id`, `cascadeOnDelete` |
| `day_of_week` | `tinyInteger` | `0` (Sunday) through `6` (Saturday), per PHP standard (`w`) |
| `route_id` | `unsignedBigInteger` | Foreign Key -> `driver_routes.id`, `nullable`, `nullOnDelete` |
| `start_time` | `string(5)` | e.g., `'06:30'`, Format `HH:MM` |
| `end_time` | `string(5)` | e.g., `'15:30'`, Format `HH:MM` |
| `is_day_off` | `boolean` | Default `false`. If true, driver is off on this day |
| `created_at`, `updated_at`| `timestamps` | Standard Laravel timestamps |

**Indexes**:
- `UNIQUE(['driver_id', 'day_of_week'])` ensures exactly one schedule definition per day of week per driver.

### 2.3. `bookings` Table Modification
Support date-specific bookings:
- Add `travel_date` (`DATE`, nullable, indexed).
- Default: When omitted, defaults to `today()->toDateString()`.
- Add composite index: `INDEX(['route_id', 'travel_date', 'status'])` for fast seat count calculation per date.

---

## 3. Business Logic & API Layer

### 3.1. Searching Vehicles by Date & Time (`ActiveVehicleController::index`)
- Query Parameters:
  - `date`: Optional ISO date (`YYYY-MM-DD`). Defaults to current date in Gaza (`today()`).
  - `time`: Optional departure time (`HH:MM`).
  - `from`, `to`, `same_direction_only`: Directional & segment stops filters (already working).
- Schedule Resolution Algorithm:
  1. Determine day of week for requested `date` (`$dayOfWeek = Carbon::parse($date)->dayOfWeek`).
  2. For the target date, look up the driver's entry in `driver_schedules`:
     - If `is_day_off == true`: Driver is excluded for this date.
     - If an assigned `route_id` exists: That route is evaluated.
     - **Fallback / Legacy Grace**: If no schedule entry exists, fallback to driver's default active route and operational status for "Today".
  3. **Time Filtering**:
     - If `time` is specified, verify: `$schedule->start_time <= $time && $time <= $schedule->end_time`.
  4. **Seat Inventory by Date**:
     - Free seats = `vehicle.seat_count - confirmedBookingsForDate(route_id, date)`.
  5. **Map Pin Display Strategy**:
     - **For "Today" (`date == today`):** Returns live GPS pins, last landmark, and position age.
     - **For Future Dates (`date > today`):** Suppresses live GPS pins (since vehicle is not currently running that future shift), and instead highlights scheduled stops and departure times.

### 3.2. Driver Schedule Management (`DriverScheduleController`)
- `GET /api/driver/schedules`: Returns 7-day array (`0` to `6`) with route details, shift times, and day-off flags.
- `POST /api/driver/schedules`:
  - Validates full weekly matrix:
    ```json
    {
      "schedules": [
        { "day_of_week": 6, "route_id": 1, "start_time": "07:00", "end_time": "16:00", "is_day_off": false },
        { "day_of_week": 5, "route_id": null, "start_time": "00:00", "end_time": "00:00", "is_day_off": true }
      ]
    }
    ```
  - Uses `upsert` or database transaction to guarantee consistency.

### 3.3. Advance Bookings (`BookingController::store`)
- Rider booking payload accepts `travel_date` (defaults to today).
- System validates requested seats against the vehicle's remaining capacity on that specific `travel_date`.
- **Instant Auto-Confirmation**: Status is set directly to `confirmed`.
- Sends driver notification:
  - Title: `حجز مسبق جديد: مقعد لرحلة غداً`
  - Body: `الراكب [الاسم] حجز مقعداً لتاريخ [التاريخ] من [محطة الركوب] إلى [محطة النزول].`

### 3.4. Driver Manifest Grouping (`BookingController::driverManifest`)
The driver's passenger manifest displays tabs:
- **`ركاب اليوم` (Today's Passengers)**: Active boarding, tap-to-call, live trip completion.
- **`الحجوزات المسبقة` (Upcoming Advance Bookings)**: Organized by date (`غداً`, `بعد غد`), with passenger names, stops, and booked seats.

---

## 4. User Interface & User Experience (RTL & Tailwind CSS)

### 4.1. Rider Home Screen (`resources/views/rider/home.blade.php`)
- **Interactive Day Selector Pills**:
  Located above the search inputs:
  - `[ اليوم (Today) ]` (Active by default, animated teal pill)
  - `[ غداً (Tomorrow) ]`
  - `[ بعد غد (Day After Tomorrow) ]`
  - `[ 📅 تاريخ آخر (Calendar Picker) ]`
- **Shift Badge on Cards**:
  Instead of only `⏰ 07:00`, cards display the full working window:
  `⏰ 7:00 ص – 3:30 م`
- **Seat Booking Sheet**:
  Displays selected `travel_date` prominently:  
  `📅 موعد الرحلة: السبت 31 أكتوبر 2026`

### 4.2. Driver Dashboard & Schedule Editor
- A dedicated **"جدول الأسبوع والخطوط" (Weekly Schedule & Routes)** section:
  - Clean 7-day card layout (Saturday through Friday).
  - Quick action: **`⚡ تطبيق المواعيد على جميع أيام العمل`** (copies Saturday's hours and route to Sun–Thu in 1 tap).
  - Quick toggle for Friday or any chosen day as `عطلة أسبوعية / إجازة`.
  - Multi-route management: Drivers can view all saved routes and create new ones.

---

## 5. Backward Compatibility & Edge Cases

1. **Existing Drivers**: All existing driver routes in the database will continue working without interruption. A migration helper will seed default schedule entries using their current `first_trip_time` (e.g. `07:00` to `16:00`, Sat–Thu work, Friday day-off).
2. **Offline Outbox**: Advance bookings made while offline save `travel_date` to `localStorage` and sync automatically when connectivity returns.
3. **Past Dates Rejection**: Booking or searching for dates before today is strictly validated and rejected (`date >= today`).

---

## 6. Implementation Plan Breakdown (Next Step)
Upon approval of this design spec, we will author the implementation plan and execute:
- **Task 1**: Migrations for multiple routes, `driver_schedules` table, and `bookings.travel_date`.
- **Task 2**: `DriverSchedule` model, relationships, and `DriverScheduleController` API with TDD feature tests.
- **Task 3**: Date-aware and time-window search matching in `ActiveVehicleController` with TDD feature tests.
- **Task 4**: Advance booking seat inventory & manifest date tabs in `BookingController` with TDD feature tests.
- **Task 5**: UI Implementation: Rider Day Pills, Time Window filter, and Driver Weekly Schedule Planner.
- **Task 6**: End-to-end verification, Pint formatting, full test suite pass, and CI/CD Dokku deployment.
