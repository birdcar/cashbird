<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_shows_landing_page_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_root_redirects_authenticated_users_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_dashboard_route_is_named(): void
    {
        $this->assertTrue(
            Route::has('dashboard'),
            'Dashboard route should be named'
        );
    }

    public function test_pgsql_database_connection_configured(): void
    {
        $this->assertArrayHasKey(
            'pgsql',
            config('database.connections'),
            'PostgreSQL database connection should be configured'
        );

        $pgsql = config('database.connections.pgsql');
        $this->assertEquals('pgsql', $pgsql['driver']);
    }

    public function test_cache_store_configured_for_redis(): void
    {
        // In production, CACHE_STORE=redis. Testing overrides to array.
        $this->assertArrayHasKey(
            'redis',
            config('cache.stores'),
            'Redis cache store should be configured'
        );
    }

    public function test_queue_connection_redis_configured(): void
    {
        $this->assertArrayHasKey(
            'redis',
            config('queue.connections'),
            'Redis queue connection should be configured'
        );
    }

    public function test_sidebar_nav_links_present_in_layout(): void
    {
        $sidebarContent = file_get_contents(
            resource_path('views/livewire/layout/sidebar.blade.php')
        );

        $this->assertStringContainsString('Dashboard', $sidebarContent);
        $this->assertStringContainsString('Accounts', $sidebarContent);
        $this->assertStringContainsString('Budget', $sidebarContent);
        $this->assertStringContainsString('Debt', $sidebarContent);
        $this->assertStringContainsString('Reports', $sidebarContent);
    }
}
