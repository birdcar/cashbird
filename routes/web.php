<?php

use App\Http\Controllers\TellerController;
use App\Livewire\Accounts\AccountList;
use App\Livewire\Accounts\ConnectAccount;
use App\Livewire\Transactions\TransactionList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth:workos')->group(function () {
    Route::get('/dashboard', function () {
        return view('livewire.dashboard');
    })->name('dashboard');

    Route::get('/accounts', AccountList::class)->name('accounts.index');
    Route::get('/accounts/connect', ConnectAccount::class)->name('accounts.connect');
    Route::post('/accounts/connect', [TellerController::class, 'store'])->name('teller.store');

    Route::get('/transactions', TransactionList::class)->name('transactions.index');
});

Route::post('/webhooks/teller', [TellerController::class, 'webhook'])->name('teller.webhook');
