<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile_page(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'محمد أحمد',
            'email' => 'mohammed@example.com',
            'phone' => '0599123456',
            'role' => 'rider',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('محمد أحمد');
        $response->assertSee('0599123456');
        $response->assertSee('تعديل البيانات');
    }

    public function test_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'اسم قديم',
            'phone' => '0599111111',
            'role' => 'driver',
        ]);

        $response = $this->actingAs($user)->post('/profile', [
            'name' => 'خالد السائق الجديد',
            'phone' => '0569222222',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('status', 'تم تحديث بياناتك الشخصية بنجاح.');

        $user->refresh();
        $this->assertEquals('خالد السائق الجديد', $user->name);
        $this->assertEquals('0569222222', $user->phone);
    }

    public function test_name_and_phone_validation_enforces_rules(): void
    {
        $user = User::factory()->create([
            'name' => 'أحمد العاصي',
            'phone' => '0599111111',
            'role' => 'rider',
        ]);

        // Invalid phone and too short name
        $response = $this->actingAs($user)->post('/profile', [
            'name' => 'أ',
            'phone' => '12345',
        ]);

        $response->assertSessionHasErrors(['name', 'phone']);

        $user->refresh();
        $this->assertEquals('أحمد العاصي', $user->name);
    }

    public function test_user_cannot_update_profile_during_active_boarded_trip(): void
    {
        $rider = User::factory()->create([
            'name' => 'راكب المشوار',
            'phone' => '0599123456',
            'role' => 'rider',
        ]);

        $driver = User::factory()->create(['role' => 'driver', 'phone' => '0599654321']);
        $vehicle = Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'ajala',
            'seat_count' => 6,
            'license_plate' => 'غزة - 9876',
            'status' => 'active',
        ]);
        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'دوار العودة',
            'stops' => ['دوار العودة', 'مفترق النجار'],
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_trip_active' => true,
        ]);

        // Active boarded booking
        Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'pickup_name' => 'دوار الكويت',
            'pickup_lat' => 31.4800,
            'pickup_lng' => 34.4300,
            'dropoff_name' => 'دوار النجمة',
            'dropoff_lat' => 31.2800,
            'dropoff_lng' => 34.2500,
            'seats' => 1,
            'status' => 'boarded', // Live ride in progress
        ]);

        $response = $this->actingAs($rider)->post('/profile', [
            'name' => 'اسم آخر',
            'phone' => '0569999999',
        ]);

        $response->assertSessionHasErrors(['profile']);

        $rider->refresh();
        $this->assertEquals('راكب المشوار', $rider->name);
    }
}
