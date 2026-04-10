<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->bigInteger('principal')->nullable();
            $table->bigInteger('interest')->nullable();
            $table->bigInteger('balance_after');
            $table->date('payment_date');
            $table->string('source', 50)->default('detected');
            $table->foreignUuid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['debt_id', 'payment_date'], 'idx_debt_payments_debt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
