<?php

use App\Jobs\GenerateBudgetProposal;
use App\Jobs\SyncAllAccounts;
use App\Models\User;
use App\Services\Debt\DebtSynchronizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    User::whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
        ->chunkById(100, fn ($users) => $users->each(
            fn (User $user) => SyncAllAccounts::dispatch($user)
        ));
})->daily()->name('sync-all-accounts')->withoutOverlapping();

Schedule::call(function () {
    User::whereHas('budget', fn ($q) => $q->whereHas('periods', fn ($p) => $p->where('status', 'active')))
        ->chunkById(100, fn ($users) => $users->each(
            fn (User $user) => GenerateBudgetProposal::dispatch($user)
        ));
})->monthlyOn(28, '08:00')->name('generate-budget-proposals')->withoutOverlapping();

Schedule::call(function () {
    $synchronizer = app(DebtSynchronizer::class);
    User::whereHas('debts', fn ($q) => $q->where('status', 'active'))
        ->chunkById(100, fn ($users) => $users->each(
            fn (User $user) => $synchronizer->syncForUser($user)
        ));
})->daily()->name('sync-debt-balances')->withoutOverlapping();
