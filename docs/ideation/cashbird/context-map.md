# Context Map: cashbird

**Phase**: 6
**Scout Confidence**: 87/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All new/modified files identified; critical corrections on MCP registration and agent namespace |
| Pattern familiarity | 18/20 | laravel/mcp source read; agent patterns read; job patterns read; tool handle signature confirmed |
| Dependency awareness | 17/20 | routes/web.php, sidebar, dashboard, AppServiceProvider, routes/console.php all read |
| Edge case coverage | 16/20 | Most failure modes documented; MCP auth via $request->user() confirmed; insight dedup needs care |
| Test strategy | 18/20 | MCP testing via Server::tool(Tool::class, [...]); agent fake pattern confirmed |

## Prior Phase Key Risks (Phases 1-5)

- `app/Console/Kernel.php` does not exist — use `routes/console.php` and `Schedule::` facade
- Agent namespace: `App\Ai\Agents` not `App\Agents`
- JSONB column — use `->json()` which normalizes to jsonb on pgsql
- `user_id` FK uses integer PK — use `foreignId('user_id')` not `foreignUuid`

## Phase 6 Key Corrections vs Spec

- **Agent namespace**: Spec says `app/Agents/` — use `app/Ai/Agents/` (existing convention)
- **MCP registration**: Spec says `AppServiceProvider` — use `routes/ai.php` (auto-loaded by McpServiceProvider)
- **MCP Server**: Spec shows `MCP::tool()` facade — actual pattern is `protected array $tools = [...]` on Server class extending `Laravel\Mcp\Server`
- **MCP tool names**: Auto-derived as kebab-case — override `protected string $name` for underscore names
- **MCP tool handle**: Receives `Laravel\Mcp\Request` not `Illuminate\Http\Request`
- **Scheduling**: Use `routes/console.php` not `app/Console/Kernel.php`
- **Agent model**: Use `#[UseSmartestModel]` attribute not hardcoded `protected string $model`
- **MCP namespace**: Use `app/Mcp/` (lowercase c)

## Key Patterns

- `app/Ai/Agents/BudgetAgent.php` — Agent: `Agent, HasStructuredOutput`, `Promptable` trait, `#[UseSmartestModel]`, fluent builders
- `app/Ai/Agents/CategorizationAgent.php` — Agent with context injection, `#[UseCheapestModel]`, static helpers
- `vendor/laravel/mcp/src/Server.php` — MCP Server: abstract, `protected array $tools = []`
- `vendor/laravel/mcp/src/Server/Tool.php` — MCP Tool: extends `Primitive`, `schema()` + `handle()`, `Response` return
- `vendor/laravel/mcp/src/Server/Testing/PendingTestResponse.php` — Test: `Server::tool(Tool::class, $args)->assertOk()`
- `app/Jobs/GenerateBudgetProposal.php` — Job: `ShouldQueue`, 4 traits, `$tries`, `$backoff`, User constructor
- `routes/console.php` — Schedule: `->monthlyOn(1, '08:00')`, `->weekly()`

## Conventions

- **Agents**: `app/Ai/Agents/`, `#[UseSmartestModel]`, `Promptable` trait, `HasStructuredOutput`
- **MCP**: Tools in `app/Mcp/Tools/`, Server in `app/Mcp/`, routes in `routes/ai.php`
- **Migrations**: `uuid('id')->primary()`, `foreignId('user_id')`, `->json()` for JSONB
- **Testing**: `Server::actingAs($user)->tool(Tool::class, $args)`, `Agent::fake([...])`

## Risks

- **Agent namespace mismatch** — spec vs codebase
- **MCP registration location** — `routes/ai.php`, not `AppServiceProvider`
- **Tool name derivation** — kebab-case auto, need explicit underscore override
- **Tool handle() receives `Laravel\Mcp\Request`** — not Illuminate\Http\Request
- **Agent tools for InsightsAgent/QueryAgent** — need to verify laravel/ai tool pattern via search-docs
- **No `routes/ai.php`** — must create fresh
