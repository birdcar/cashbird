<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('description');
            $table->json('data')->nullable();
            $table->string('severity', 20)->default('info');
            $table->string('status', 20)->default('active');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_insights_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};
