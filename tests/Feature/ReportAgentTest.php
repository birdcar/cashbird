<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\ReportAgent;
use App\Jobs\GenerateMonthlyReport;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_generates_monthly_report(): void
    {
        ReportAgent::fake([
            "## Monthly Summary\n\nYou earned \$5,000 and spent \$3,500.\n\n## Category Breakdown\n\nGroceries: \$800",
        ]);

        $job = new GenerateMonthlyReport($this->user, Carbon::parse('2026-03-01'));
        $job->handle();

        $this->assertDatabaseCount('reports', 1);
        $report = Report::where('user_id', $this->user->id)->first();
        $this->assertEquals('Monthly Report — March 2026', $report->title);
        $this->assertStringContainsString('Monthly Summary', $report->content);
    }

    public function test_does_not_duplicate_report_for_same_month(): void
    {
        ReportAgent::fake(['Test report content']);

        Report::factory()->create([
            'user_id' => $this->user->id,
            'period_month' => '2026-03-01',
        ]);

        $job = new GenerateMonthlyReport($this->user, Carbon::parse('2026-03-01'));
        $job->handle();

        $this->assertDatabaseCount('reports', 1);
    }

    public function test_extracts_summary_from_report_content(): void
    {
        ReportAgent::fake([
            "## Monthly Summary\n\nYou earned \$5,000 and spent \$3,500 this month.\n\n## Category Breakdown\n\nDetails here.",
        ]);

        $job = new GenerateMonthlyReport($this->user, Carbon::parse('2026-03-01'));
        $job->handle();

        $report = Report::where('user_id', $this->user->id)->first();
        $this->assertNotNull($report->summary);
        $this->assertStringContainsString('5,000', $report->summary);
    }
}
