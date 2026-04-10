<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('teller_id')->unique();
            $table->bigInteger('amount');
            $table->date('date');
            $table->string('description', 500);
            $table->string('merchant_name')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 50)->default('posted');
            $table->string('type', 50)->nullable();
            $table->bigInteger('running_balance')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date'], 'idx_transactions_user_date');
            $table->index('account_id', 'idx_transactions_account');
            $table->index('category_id', 'idx_transactions_category');
            $table->index('status', 'idx_transactions_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
