# Context Map: cashbird

**Phase**: 4
**Scout Confidence**: 82/100
**Verdict**: GO

## Key Risks
- Phase 5 debt model doesn't exist — stub debt minimums at 0
- Console/Kernel.php and EventServiceProvider don't exist — use routes/console.php and AppServiceProvider
- BudgetAgent namespace: spec says App\Agents, existing agent is App\Ai\Agents — use App\Ai\Agents for consistency
- daily_safe division-by-zero on last day of month — guard with max(1, $daysRemaining)
- Redis in tests — use Cache facade or Redis fake
- JSONB column — use ->json() which normalizes to jsonb on pgsql
