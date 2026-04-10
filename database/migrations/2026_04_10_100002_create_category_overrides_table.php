<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_name');
            $table->foreignUuid('category_id')->constrained();
            $table->timestamps();

            $table->unique(['user_id', 'merchant_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_overrides');
    }
};
