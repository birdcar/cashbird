<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\QueryAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QueryAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_answers_financial_question(): void
    {
        QueryAgent::fake([
            'You spent $450.00 on dining in March 2026.',
        ]);

        $agent = QueryAgent::make($this->user->id);
        $response = $agent->prompt('How much did I spend on dining in March?');

        $this->assertStringContainsString('450', (string) $response);
    }

    public function test_handles_question_with_no_data(): void
    {
        QueryAgent::fake([
            'I don\'t have enough data to answer that question. No transactions found for the specified period.',
        ]);

        $agent = QueryAgent::make($this->user->id);
        $response = $agent->prompt('How much did I spend on travel last year?');

        $this->assertStringContainsString('enough data', (string) $response);
    }

    public function test_agent_is_prompted(): void
    {
        QueryAgent::fake(['Test response']);

        $agent = QueryAgent::make($this->user->id);
        $agent->prompt('What is my biggest expense?');

        QueryAgent::assertPrompted('What is my biggest expense?');
    }
}
