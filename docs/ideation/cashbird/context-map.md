# Context Map: cashbird

**Phase**: 3
**Scout Confidence**: 74/100
**Verdict**: GO

## Key Risks
- `laravel/ai` v0.5.0 installed — Agent uses `Promptable` trait, `HasStructuredOutput` for typed responses
- `EventServiceProvider` doesn't exist — use `Event::listen()` in `AppServiceProvider::boot()` 
- `categorized_at` column missing from transactions — needs migration
- `user_id` FK is `foreignId` (bigint), not UUID — match in new tables
- Category model already has parent/children relationships from Phase 2
- Transaction model already has category() relationship from Phase 2
