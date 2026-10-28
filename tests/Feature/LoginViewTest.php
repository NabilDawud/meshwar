<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_cleanly_without_developer_role_choice_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('المتابعة باستخدام Google');
        $response->assertSee('دخول كـ راكب');
        $response->assertSee('دخول كـ سائق');

        // Assert that the developer debug links are never rendered
        $response->assertDontSee('اختبار شاشة التسجيل');
        $response->assertDontSee('اختيار الدور');
        $response->assertDontSee('دخول مستخدم جديد');
    }
}
