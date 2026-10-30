<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $rider;

    protected DriverRoute $route1;

    protected DriverRoute $route2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'سائق الجداول',
            'email' => 'driver-schedule-api@meshwar.local',
            'phone' => '0599555111',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-5511-99',
            'status' => 'active',
        ]);

        $this->route1 = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
            ],
        ]);

        $this->route2 = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'خط الرشيد الساحلي',
            'start_point' => 'ميناء غزة',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'ميناء غزة', 'lat' => 31.5270, 'lng' => 34.4330, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 6.0],
            ],
        ]);

        $this->rider = User::create([
            'name' => 'راكب غير مخول',
            'email' => 'unauth-rider-schedule@meshwar.local',
            'phone' => '0599555222',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_driver_can_list_all_their_saved_routes(): void
    {
        $response = $this->actingAs($this->driver)->getJson('/api/driver/routes');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        $routes = $response->json('routes');
        $this->assertCount(2, $routes);
        $this->assertEquals('خط صلاح الدين', $routes[0]['name']);
        $this->assertEquals('خط الرشيد الساحلي', $routes[1]['name']);
    }

    public function test_driver_can_get_and_bulk_save_weekly_schedules(): void
    {
        // 1. Initial GET returns 7 days structure
        $getRes = $this->actingAs($this->driver)->getJson('/api/driver/schedules');
        $getRes->assertStatus(200)
            ->assertJson(['ok' => true]);
        $schedules = $getRes->json('schedules');
        $this->assertCount(7, $schedules);

        // 2. POST save weekly matrix
        $payload = [
            'schedules' => [
                // Saturday (6)
                ['day_of_week' => 6, 'route_id' => $this->route1->id, 'start_time' => '07:00', 'end_time' => '16:00', 'is_day_off' => false],
                // Sunday (0)
                ['day_of_week' => 0, 'route_id' => $this->route1->id, 'start_time' => '07:00', 'end_time' => '16:00', 'is_day_off' => false],
                // Monday (1)
                ['day_of_week' => 1, 'route_id' => $this->route2->id, 'start_time' => '08:00', 'end_time' => '15:00', 'is_day_off' => false],
                // Tuesday (2)
                ['day_of_week' => 2, 'route_id' => $this->route1->id, 'start_time' => '07:00', 'end_time' => '16:00', 'is_day_off' => false],
                // Wednesday (3)
                ['day_of_week' => 3, 'route_id' => $this->route1->id, 'start_time' => '07:00', 'end_time' => '16:00', 'is_day_off' => false],
                // Thursday (4)
                ['day_of_week' => 4, 'route_id' => $this->route2->id, 'start_time' => '08:00', 'end_time' => '14:00', 'is_day_off' => false],
                // Friday (5) - Day off
                ['day_of_week' => 5, 'route_id' => null, 'start_time' => '00:00', 'end_time' => '00:00', 'is_day_off' => true],
            ],
        ];

        $postRes = $this->actingAs($this->driver)->postJson('/api/driver/schedules', $payload);
        $postRes->assertStatus(200)
            ->assertJson(['ok' => true]);

        // Verify persisted records
        $this->assertDatabaseHas('driver_schedules', [
            'driver_id' => $this->driver->id,
            'day_of_week' => 6,
            'route_id' => $this->route1->id,
            'is_day_off' => false,
        ]);

        $this->assertDatabaseHas('driver_schedules', [
            'driver_id' => $this->driver->id,
            'day_of_week' => 5,
            'is_day_off' => true,
        ]);
    }

    public function test_rider_cannot_access_driver_schedule_endpoints(): void
    {
        $response = $this->actingAs($this->rider)->getJson('/api/driver/schedules');
        $response->assertStatus(403);

        $responsePost = $this->actingAs($this->rider)->postJson('/api/driver/schedules', ['schedules' => []]);
        $responsePost->assertStatus(403);
    }

    public function test_driver_can_delete_route_when_they_have_multiple_routes(): void
    {
        $this->assertEquals(2, $this->driver->driverRoutes()->count());

        $deleteRes = $this->actingAs($this->driver)->deleteJson("/api/driver/routes/{$this->route2->id}");
        $deleteRes->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('driver_routes', ['id' => $this->route2->id]);
        $this->assertEquals(1, $this->driver->driverRoutes()->count());
    }

    public function test_driver_cannot_delete_their_only_remaining_route(): void
    {
        // Delete route 2 first
        $this->route2->delete();
        $this->assertEquals(1, $this->driver->driverRoutes()->count());

        // Attempting to delete the only remaining route should fail with 422
        $deleteRes = $this->actingAs($this->driver)->deleteJson("/api/driver/routes/{$this->route1->id}");
        $deleteRes->assertStatus(422)
            ->assertJson(['ok' => false, 'error' => 'لا يمكنك حذف مسارك الوحيد المتبقي. يجب أن تمتلك مساراً واحداً على الأقل.']);

        $this->assertDatabaseHas('driver_routes', ['id' => $this->route1->id]);
    }

    public function test_driver_route_page_can_load_specific_route(): void
    {
        $response = $this->actingAs($this->driver)->get("/driver/route?route_id={$this->route2->id}");
        $response->assertStatus(200);
        $response->assertSee('خط الرشيد الساحلي');
    }
}
