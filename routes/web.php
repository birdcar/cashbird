<?php

use App\Http\Controllers\TellerController;
use App\Http\Controllers\UndoController;
use App\Livewire\Accounts\AccountList;
use App\Livewire\Accounts\ConnectAccount;
use App\Livewire\Budget\BudgetOverview;
use App\Livewire\Chat\FinancialChat;
use App\Livewire\Debt\AddManualDebt;
use App\Livewire\Debt\DebtDashboard;
use App\Livewire\Debt\DebtDetail;
use App\Livewire\Insights\InsightsFeed;
use App\Livewire\NetWorth\NetWorthDashboard;
use App\Livewire\Reports\ReportList;
use App\Livewire\Reports\ReportView;
use App\Livewire\Savings\CreateGoal;
use App\Livewire\Savings\SavingsGoalsList;
use App\Livewire\Sharing\ManageSharing;
use App\Livewire\Sharing\SharedWithMe;
use App\Livewire\Transactions\TransactionList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('workos')->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
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

    Route::get('/net-worth', NetWorthDashboard::class)->name('net-worth.index');

    Route::get('/savings', SavingsGoalsList::class)->name('savings.index');
    Route::get('/savings/create', CreateGoal::class)->name('savings.create');

    Route::get('/debt', DebtDashboard::class)->name('debt.index');
    Route::get('/debt/create', AddManualDebt::class)->name('debt.create');
    Route::get('/debt/{debt}', DebtDetail::class)->name('debt.show');

    Route::get('/reports', ReportList::class)->name('reports.index');
    Route::get('/reports/{report}', ReportView::class)->name('reports.show');

    Route::get('/insights', InsightsFeed::class)->name('insights.index');

    Route::get('/chat', FinancialChat::class)->name('chat.index');

    Route::get('/sharing', ManageSharing::class)->name('sharing.index');
    Route::get('/sharing/shared-with-me', SharedWithMe::class)->name('sharing.shared');

    Route::post('/undo/proposal', [UndoController::class, 'undoProposalApprove'])->name('undo.proposal');
    Route::post('/undo/sharing', [UndoController::class, 'undoSharingRevoke'])->name('undo.sharing');
});
