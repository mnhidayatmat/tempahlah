<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'booking_link_shared_at')) {
                // Stamped when the host clicks "Share your booking link" on the
                // first-run setup checklist. It's the final step of getting set
                // up: once the core steps are green and the host has shared
                // their link, the "Get set up" card dismisses itself. A real
                // booking also satisfies the step, so this is only needed for a
                // fully-configured host who hasn't taken a booking yet.
                $table->timestamp('booking_link_shared_at')->nullable()->after('manual_payment_instructions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'booking_link_shared_at')) {
                $table->dropColumn('booking_link_shared_at');
            }
        });
    }
};
