<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\ReportAgent;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public User $user,
        public ?Carbon $month = null,
    ) {}

    public function handle(): void
    {
        $month = ($this->month ?? Carbon::now()->subMonth())->startOfMonth();

        $existing = Report::where('user_id', $this->user->id)
            ->whereDate('period_month', $month)
            ->exists();

        if ($existing) {
            return;
        }

        $agent = ReportAgent::make($this->user->id);
        $response = $agent->prompt(
            "Generate the monthly financial report for {$month->format('F Y')}. Use your tools to gather spending, budget, and debt data."
        );

        $content = (string) $response;

        Report::create([
            'user_id' => $this->user->id,
            'period_month' => $month->toDateString(),
            'title' => "Monthly Report — {$month->format('F Y')}",
            'content' => $content,
            'summary' => $this->extractSummary($content),
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'month' => $month->format('Y-m'),
            ],
        ]);
    }

    private function extractSummary(string $content): string
    {
        if (preg_match('/## Monthly Summary\s*\n\n(.+?)(?:\n\n|$)/s', $content, $matches)) {
            return trim(substr($matches[1], 0, 500));
        }

        return substr(strip_tags($content), 0, 200);
    }
}
