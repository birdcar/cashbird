<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TransactionsCategorized;
use App\Events\TransactionsSynced;
use App\Listeners\CategorizeNewTransactions;
use App\Listeners\EmbedCategorizedTransactions;
use App\Listeners\InvalidateSpendingCache;
use App\Listeners\SyncDebtsOnTransactionSync;
use App\Listeners\UpdateReadyToSpendOnTransaction;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeFinancialConnectionsClient::class, fn () => new StripeFinancialConnectionsClient(
            secretKey: config('stripe.secret_key', ''),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TransactionsSynced::class, CategorizeNewTransactions::class);
        Event::listen(TransactionsSynced::class, SyncDebtsOnTransactionSync::class);
        Event::listen(TransactionsCategorized::class, InvalidateSpendingCache::class);
        Event::listen(TransactionsCategorized::class, UpdateReadyToSpendOnTransaction::class);
        Event::listen(TransactionsCategorized::class, EmbedCategorizedTransactions::class);
    }
}
