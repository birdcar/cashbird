<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InsightStatus;
use App\Models\Insight;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_insights')]
#[Description('List active financial insights and recommendations.')]
class GetInsightsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['active', 'dismissed'])
                ->description('Filter by status (default: active)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $status = $request->get('status', 'active');
        $insights = Insight::where('user_id', $user->id)
            ->where('status', InsightStatus::from($status))
            ->orderByDesc('created_at')
            ->get();

        if ($insights->isEmpty()) {
            return Response::text('No '.$status.' insights found.');
        }

        $result = $insights->map(fn ($i) => [
            'type' => $i->type->value,
            'title' => $i->title,
            'description' => $i->description,
            'severity' => $i->severity->value,
            'created' => $i->created_at->format('M j, Y'),
        ]);

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }
}
