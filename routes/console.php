<?php

use App\Jobs\SyncAllAccounts;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    User::whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
        ->each(fn (User $user) => SyncAllAccounts::dispatch($user));
})->daily()->name('sync-all-accounts');
