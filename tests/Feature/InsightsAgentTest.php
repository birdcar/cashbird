<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\InsightsAgent;
use App\Enums\InsightStatus;
use App\Jobs\AnalyzeSpendingInsights;
use App\Models\Insight;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InsightsAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_creates_insights_from_agent_response(): void
    {
        InsightsAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'spending_spike',
                        'title' => 'Dining spending up 80%',
                        'description' => 'Your dining spending increased from $200 to $360 this month.',
                        'severity' => 'warning',
                        'data' => ['category' => 'Dining', 'amount' => 36000],
                    ],
                ],
            ],
        ]);

        $job = new AnalyzeSpendingInsights($this->user);
        $job->handle();

        $this->assertDatabaseCount('insights', 1);
        $insight = Insight::where('user_id', $this->user->id)->first();
        $this->assertEquals('Dining spending up 80%', $insight->title);
        $this->assertEquals('warning', $insight->severity->value);
    }

    public function test_does_not_resurface_dismissed_insights(): void
    {
        Insight::factory()->dismissed()->create([
            'user_id' => $this->user->id,
            'title' => 'Dining spending up 80%',
        ]);

        InsightsAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'spending_spike',
                        'title' => 'Dining spending up 80%',
                        'description' => 'Same insight again.',
                        'severity' => 'warning',
                        'data' => ['category' => 'Dining'],
                    ],
                ],
            ],
        ]);

        $job = new AnalyzeSpendingInsights($this->user);
        $job->handle();

        $activeInsights = Insight::where('user_id', $this->user->id)
            ->where('status', InsightStatus::Active)
            ->count();
        $this->assertEquals(0, $activeInsights);
    }

    public function test_handles_empty_insights_response(): void
    {
        InsightsAgent::fake([
            ['insights' => []],
        ]);

        $job = new AnalyzeSpendingInsights($this->user);
        $job->handle();

        $this->assertDatabaseCount('insights', 0);
    }

    public function test_insight_can_be_dismissed(): void
    {
        $insight = Insight::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $insight->dismiss();

        $insight->refresh();
        $this->assertEquals(InsightStatus::Dismissed, $insight->status);
        $this->assertNotNull($insight->dismissed_at);
    }
}
