<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop FK on accounts.enrollment_id before dropping the referenced table
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
        });

        // 2. Drop the teller_enrollments table
        Schema::dropIfExists('teller_enrollments');

        // 3. Create provider-agnostic connections table
        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id')->unique();
            $table->string('status', 50)->default('active');
            $table->timestamp('connected_at');
            $table->timestamps();

            $table->unique(['user_id', 'institution_id']);
        });

        // 4. Rename teller_id → external_id on institutions
        Schema::table('institutions', function (Blueprint $table) {
            $table->renameColumn('teller_id', 'external_id');
        });

        // 5. Rename columns on accounts: teller_id → external_id, enrollment_id → connection_id
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('teller_id', 'external_id');
            $table->renameColumn('enrollment_id', 'connection_id');
        });

        // 6. Add new FK for accounts.connection_id → connections.id
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
        });

        // 7. Rename teller_id → external_id on transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('teller_id', 'external_id');
        });
    }

    public function down(): void
    {
        // Reverse: rename external_id back to teller_id
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('external_id', 'teller_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->renameColumn('external_id', 'teller_id');
            $table->renameColumn('connection_id', 'enrollment_id');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->renameColumn('external_id', 'teller_id');
        });

        Schema::dropIfExists('connections');

        // Recreate teller_enrollments
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

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('enrollment_id')->references('id')->on('teller_enrollments')->cascadeOnDelete();
        });
    }
};
