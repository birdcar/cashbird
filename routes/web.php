<?php

use App\Http\Controllers\TellerController;
use App\Livewire\Accounts\AccountList;
use App\Livewire\Accounts\ConnectAccount;
use App\Livewire\Budget\BudgetOverview;
use App\Livewire\Chat\FinancialChat;
use App\Livewire\Debt\AddManualDebt;
use App\Livewire\Debt\DebtDashboard;
use App\Livewire\Debt\DebtDetail;
use App\Livewire\Insights\InsightsFeed;
use App\Livewire\Reports\ReportList;
use App\Livewire\Reports\ReportView;
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

    Route::get('/budget', BudgetOverview::class)->name('budget.index');

    Route::get('/debt', DebtDashboard::class)->name('debt.index');
    Route::get('/debt/create', AddManualDebt::class)->name('debt.create');
    Route::get('/debt/{debt}', DebtDetail::class)->name('debt.show');

    Route::get('/reports', ReportList::class)->name('reports.index');
    Route::get('/reports/{report}', ReportView::class)->name('reports.show');

    Route::get('/insights', InsightsFeed::class)->name('insights.index');

    Route::get('/chat', FinancialChat::class)->name('chat.index');
});

Route::post('/webhooks/teller', [TellerController::class, 'webhook'])->name('teller.webhook');
