<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::getConnection() instanceof PostgresConnection) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });

            return;
        }

        Schema::ensureVectorExtensionExists();

        Schema::table('transactions', function (Blueprint $table) {
            $table->vector('embedding', dimensions: 768)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
