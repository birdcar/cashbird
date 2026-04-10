<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_name');
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->bigInteger('average_amount');
            $table->string('frequency', 20);
            $table->decimal('confidence', 3, 2);
            $table->date('last_seen_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'merchant_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_charges');
    }
};
