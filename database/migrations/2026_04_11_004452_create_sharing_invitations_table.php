<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharing_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_type', 50);
            $table->uuid('resource_id');
            $table->string('relation', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(
                ['from_user_id', 'to_user_id', 'resource_type', 'resource_id'],
                'idx_sharing_unique'
            );
            $table->index(['to_user_id', 'status'], 'idx_sharing_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharing_invitations');
    }
};
