<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use WorkOS\AuthKit\Models\Concerns\HasWorkOSId;
use WorkOS\AuthKit\Models\Concerns\HasWorkOSPermissions;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_exists(): void
    {
        $this->assertTrue(
            Route::has('login'),
            'Login route should be registered'
        );
    }

    public function test_callback_route_exists(): void
    {
        $this->assertTrue(
            Route::has('workos.callback'),
            'WorkOS callback route should be registered'
        );
    }

    public function test_logout_route_exists(): void
    {
        $this->assertTrue(
            Route::has('logout'),
            'Logout route should be registered'
        );
    }

    public function test_unauthenticated_dashboard_access_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_workos_guard_is_default(): void
    {
        $this->assertEquals(
            'workos',
            config('auth.defaults.guard'),
            'Default auth guard should be workos'
        );
    }

    public function test_workos_config_published(): void
    {
        $this->assertNotNull(config('workos'));
        $this->assertEquals('workos', config('workos.guard'));
        $this->assertTrue(config('workos.routes.enabled'));
        $this->assertEquals('auth', config('workos.routes.prefix'));
    }

    public function test_user_model_has_workos_traits(): void
    {
        $user = new User;
        $traits = class_uses_recursive($user);

        $this->assertContains(
            HasWorkOSId::class,
            $traits,
            'User model should use HasWorkOSId trait'
        );

        $this->assertContains(
            HasWorkOSPermissions::class,
            $traits,
            'User model should use HasWorkOSPermissions trait'
        );
    }
}
