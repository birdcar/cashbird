<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('proposed_by', 20)->default('ai');
            $table->json('changes');
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_proposals');
    }
};
