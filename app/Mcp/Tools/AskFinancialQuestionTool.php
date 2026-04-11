<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Agents\QueryAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('ask_financial_question')]
#[Description('Ask a natural language question about finances and get an AI-powered answer.')]
class AskFinancialQuestionTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->required()->description('The financial question to answer'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $agent = QueryAgent::make($user->id);
        $answer = $agent->prompt($validated['question']);

        return Response::text((string) $answer);
    }
}
