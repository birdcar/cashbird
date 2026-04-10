<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->string('status', 50)->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamps();

            $table->unique(['user_id', 'institution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_enrollments');
    }
};
