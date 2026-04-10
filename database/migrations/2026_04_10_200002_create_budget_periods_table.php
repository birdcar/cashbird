<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->bigInteger('total_income');
            $table->bigInteger('total_allocated')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['budget_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
