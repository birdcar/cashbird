<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_debts_user_status');
        });

        Schema::table('debt_payments', function (Blueprint $table) {
            $table->index('transaction_id', 'idx_debt_payments_transaction');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex('idx_debts_user_status');
        });

        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropIndex('idx_debt_payments_transaction');
        });
    }
};
