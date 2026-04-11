<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');
            $table->string('title');
            $table->text('content');
            $table->text('summary')->nullable();
            $table->json('data');
            $table->timestamps();

            $table->unique(['user_id', 'period_month'], 'idx_reports_user_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
