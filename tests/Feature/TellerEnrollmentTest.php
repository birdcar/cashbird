<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncAllAccounts;
use App\Models\Institution;
use App\Models\TellerEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TellerEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_stores_encrypted_access_token(): void
    {
        $enrollment = TellerEnrollment::factory()->create([
            'access_token' => 'raw_test_token',
        ]);

        $this->assertNotEquals('raw_test_token', $enrollment->getRawOriginal('access_token'));

        $decrypted = $enrollment->getDecryptedAccessToken();
        $this->assertEquals('raw_test_token', $decrypted);
    }

    public function test_enrollment_creation_via_controller(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('teller.store'), [
                'access_token' => 'test_access_token_123',
                'enrollment_id' => 'enr_001',
                'institution' => [
                    'id' => 'inst_chase',
                    'name' => 'Chase',
                ],
            ]);

        $response->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('institutions', [
            'teller_id' => 'inst_chase',
            'name' => 'Chase',
        ]);

        $this->assertDatabaseCount('teller_enrollments', 1);

        $enrollment = TellerEnrollment::first();
        $this->assertEquals($user->id, $enrollment->user_id);
        $this->assertNotEquals('test_access_token_123', $enrollment->getRawOriginal('access_token'));

        Queue::assertPushed(SyncAllAccounts::class);
    }

    public function test_duplicate_enrollment_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $institution = Institution::factory()->create(['teller_id' => 'inst_chase']);
        TellerEnrollment::factory()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
        ]);

        $response = $this->actingAs($user, 'workos')
            ->post(route('teller.store'), [
                'access_token' => 'another_token',
                'enrollment_id' => 'enr_002',
                'institution' => [
                    'id' => 'inst_chase',
                    'name' => 'Chase',
                ],
            ]);

        $response->assertRedirect(route('accounts.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('teller_enrollments', 1);

        Queue::assertNotPushed(SyncAllAccounts::class);
    }

    public function test_enrollment_requires_authentication(): void
    {
        $response = $this->post(route('teller.store'), [
            'access_token' => 'test_token',
            'enrollment_id' => 'enr_001',
            'institution' => ['id' => 'inst_1', 'name' => 'Bank'],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_enrollment_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('teller.store'), []);

        $response->assertSessionHasErrors(['access_token', 'enrollment_id', 'institution.id', 'institution.name']);
    }
}
