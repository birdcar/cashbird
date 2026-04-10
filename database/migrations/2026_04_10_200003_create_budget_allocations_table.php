<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_period_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained();
            $table->bigInteger('allocated_amount');
            $table->bigInteger('spent_amount')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_fixed')->default(false);
            $table->string('lock_reason')->nullable();
            $table->timestamps();

            $table->unique(['budget_period_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
    }
};
