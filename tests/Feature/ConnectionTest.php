<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Connection;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $connection = Connection::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($connection->user->is($user));
    }

    public function test_connection_belongs_to_institution(): void
    {
        $institution = Institution::factory()->create();
        $connection = Connection::factory()->create(['institution_id' => $institution->id]);

        $this->assertTrue($connection->institution->is($institution));
    }

    public function test_connection_has_many_accounts(): void
    {
        $connection = Connection::factory()->create();
        Account::factory()->count(2)->create(['connection_id' => $connection->id]);

        $this->assertCount(2, $connection->accounts);
    }

    public function test_duplicate_connection_for_same_user_and_institution_rejected(): void
    {
        $user = User::factory()->create();
        $institution = Institution::factory()->create();

        Connection::factory()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
        ]);

        $this->expectException(QueryException::class);

        Connection::factory()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'stripe_account_id' => 'fca_different',
        ]);
    }

    public function test_connection_uses_uuids(): void
    {
        $connection = Connection::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $connection->id
        );
    }

    public function test_connected_at_is_cast_to_datetime(): void
    {
        $connection = Connection::factory()->create(['connected_at' => '2026-04-12 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $connection->connected_at);
    }

    public function test_disconnected_factory_state(): void
    {
        $connection = Connection::factory()->disconnected()->create();

        $this->assertEquals('disconnected', $connection->status);
    }

    public function test_expired_factory_state(): void
    {
        $connection = Connection::factory()->expired()->create();

        $this->assertEquals('expired', $connection->status);
    }

    public function test_cascades_delete_with_user(): void
    {
        $user = User::factory()->create();
        $connection = Connection::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
    }
}
