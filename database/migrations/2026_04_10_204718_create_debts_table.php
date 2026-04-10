<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 50);
            $table->string('lender')->nullable();
            $table->bigInteger('current_balance');
            $table->bigInteger('original_balance')->nullable();
            $table->decimal('apr', 6, 3);
            $table->bigInteger('minimum_payment');
            $table->integer('due_day')->nullable();
            $table->boolean('is_in_recovery')->default(false);
            $table->json('recovery_terms')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('paid_off_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_debts_user');
            $table->index(['apr'], 'idx_debts_apr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
