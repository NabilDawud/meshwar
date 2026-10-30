<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\DriverSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRouteScheduleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_have_multiple_routes_with_is_active_flag(): void
    {
        $driver = User::create([
            'name' => 'سائق المسارات المتعددة',
            'email' => 'multi-route-driver@meshwar.local',
            'phone' => '0599111888',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route1 = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'رفح - غزة (صلاح الدين)',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $route2 = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خانيونس - دير البلح (الرشيد)',
            'start_point' => 'دوار بني سهيلا',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $this->assertCount(2, $driver->driverRoutes);
        $this->assertTrue($route1->is_active);
        $this->assertTrue($route2->is_active);
    }

    public function test_driver_schedule_schema_and_model_relations(): void
    {
        $driver = User::create([
            'name' => 'سائق الجدول الأسبوعي',
            'email' => 'schedule-driver@meshwar.local',
            'phone' => '0599222777',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'مسار السبت',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        // Create schedule for Saturday (day 6)
        $scheduleSat = DriverSchedule::create([
            'driver_id' => $driver->id,
            'day_of_week' => 6,
            'route_id' => $route->id,
            'start_time' => '07:00',
            'end_time' => '16:00',
            'is_day_off' => false,
        ]);

        // Create schedule for Friday (day 5) - Day off
        $scheduleFri = DriverSchedule::create([
            'driver_id' => $driver->id,
            'day_of_week' => 5,
            'route_id' => null,
            'start_time' => '00:00',
            'end_time' => '00:00',
            'is_day_off' => true,
        ]);

        $this->assertEquals($driver->id, $scheduleSat->driver->id);
        $this->assertEquals($route->id, $scheduleSat->route->id);
        $this->assertFalse($scheduleSat->is_day_off);
        $this->assertTrue($scheduleFri->is_day_off);
        $this->assertCount(2, $driver->schedules);
    }

    public function test_booking_has_travel_date_attribute(): void
    {
        $driver = User::create([
            'name' => 'سائق الحجز المسبق',
            'email' => 'booking-driver@meshwar.local',
            'phone' => '0599333666',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'مسار تجريبي',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $rider = User::create([
            'name' => 'راكب الغد',
            'email' => 'advance-rider@meshwar.local',
            'phone' => '0599444555',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $tomorrow = now()->addDay()->toDateString();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'travel_date' => $tomorrow,
            'status' => 'confirmed',
        ]);

        $this->assertEquals($tomorrow, $booking->fresh()->travel_date->format('Y-m-d'));
    }
}
