<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\InsightsAgent;
use App\Enums\InsightSeverity;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeSpendingInsights implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        $agent = InsightsAgent::make($this->user->id);
        $response = $agent->prompt(
            'Analyze my financial data and identify any actionable insights. Use the tools to review my subscriptions, spending trends, budget, and debt.'
        );

        $insights = $response['insights'] ?? [];

        $existingTitles = Insight::where('user_id', $this->user->id)
            ->whereIn('status', [InsightStatus::Active, InsightStatus::Dismissed])
            ->pluck('title')
            ->flip();

        foreach ($insights as $insight) {
            if ($existingTitles->has($insight['title'])) {
                continue;
            }

            Insight::create([
                'user_id' => $this->user->id,
                'type' => $insight['type'],
                'title' => $insight['title'],
                'description' => $insight['description'],
                'data' => $insight['data'] ?? null,
                'severity' => $insight['severity'] ?? InsightSeverity::Info->value,
                'status' => InsightStatus::Active,
            ]);
        }
    }
}
