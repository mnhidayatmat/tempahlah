<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_tickets', 'scheduled_at')) {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                // When the maintenance is planned to happen (host-entered).
                // Nullable — existing tickets fall back to created_at for display.
                $table->timestamp('scheduled_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('maintenance_tickets', 'scheduled_at')) {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->dropColumn('scheduled_at');
            });
        }
    }
};
