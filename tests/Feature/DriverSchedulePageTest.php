<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\DriverSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverSchedulePageTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $rider;

    protected DriverRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'الكابتن محمد',
            'email' => 'driver-page@meshwar.local',
            'phone' => '0599112233',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => '3-1122-33',
            'status' => 'active',
        ]);

        $this->route = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'خانيونس - رفح عبر البحر',
            'start_point' => 'دوار بني سهيلا',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دوار العودة رفح', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 6.0],
            ],
        ]);

        $this->rider = User::create([
            'name' => 'راكب تجريبي',
            'email' => 'rider-page@meshwar.local',
            'phone' => '0599445566',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_guest_is_redirected_from_driver_schedule_page(): void
    {
        $response = $this->get('/driver/schedule');
        $response->assertRedirect('/login');
    }

    public function test_rider_is_forbidden_from_driver_schedule_page(): void
    {
        $response = $this->actingAs($this->rider)->get('/driver/schedule');
        $response->assertStatus(403);
    }

    public function test_driver_can_render_dedicated_schedule_page(): void
    {
        // Seed Saturday schedule
        DriverSchedule::create([
            'driver_id' => $this->driver->id,
            'day_of_week' => 6,
            'route_id' => $this->route->id,
            'start_time' => '06:30',
            'end_time' => '17:00',
            'is_day_off' => false,
        ]);

        $response = $this->actingAs($this->driver)->get('/driver/schedule');

        $response->assertStatus(200);
        $response->assertSee('جدول مواعيد ومسارات الأسبوع');
        $response->assertSee('خانيونس - رفح عبر البحر');
        $response->assertSee('تطبيق مواعيد السبت على باقي أيام العمل');
    }

    public function test_driver_dashboard_renders_with_schedule_summary(): void
    {
        $response = $this->actingAs($this->driver)->get('/driver');

        $response->assertStatus(200);
        $response->assertSee('جدول ومواعيد العمل الأسبوعية');
        $response->assertSee('/driver/schedule');
        $response->assertSee('لوحة قيادة السائق');
    }
}
