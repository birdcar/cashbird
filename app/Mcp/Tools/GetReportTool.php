<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Report;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_report')]
#[Description('Retrieve a monthly financial report by month.')]
class GetReportTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->string()->description('Month in YYYY-MM format (default: most recent)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $query = Report::where('user_id', $user->id);

        if ($request->get('month')) {
            $query->whereDate('period_month', $request->get('month').'-01');
        } else {
            $query->orderByDesc('period_month');
        }

        $report = $query->first();

        if (! $report) {
            return Response::text('No report found for the specified month.');
        }

        return Response::text($report->content);
    }
}
