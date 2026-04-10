<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('teller_enrollments')->cascadeOnDelete();
            $table->string('teller_id')->unique();
            $table->foreignUuid('institution_id')->constrained();
            $table->string('name');
            $table->string('type', 50);
            $table->string('subtype', 50)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('balance_current')->nullable();
            $table->bigInteger('balance_available')->nullable();
            $table->bigInteger('balance_limit')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_accounts_user');
            $table->index('type', 'idx_accounts_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
