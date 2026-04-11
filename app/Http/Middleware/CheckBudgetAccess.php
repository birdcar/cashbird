<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BudgetAllocation;
use App\Services\WorkOS\FGAService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBudgetAccess
{
    public function __construct(
        private readonly FGAService $fga,
    ) {}

    public function handle(Request $request, Closure $next, string $relation = 'viewer'): Response
    {
        $categoryId = $request->route('category');

        if (! $categoryId) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isOwner = BudgetAllocation::whereHas('period.budget', fn ($q) => $q->where('user_id', $user->id))
            ->where('category_id', $categoryId)
            ->exists();

        if ($isOwner) {
            return $next($request);
        }

        $workosId = $user->workos_id;
        if (! $workosId) {
            abort(403);
        }

        if (! $this->fga->check('budget_category', $categoryId, $relation, 'user', $workosId)) {
            abort(403);
        }

        return $next($request);
    }
}
