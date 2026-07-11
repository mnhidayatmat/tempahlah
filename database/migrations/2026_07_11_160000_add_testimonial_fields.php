<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the (already-scaffolded) `reviews` table into a guest testimonial
 * feature:
 *   - reviews.guest_name — the display name the guest types on the public
 *     testimonial form. Falls back to the booking's lead-guest name when blank,
 *     but a guest may prefer to show "Ain from KL" instead of their full name.
 *   - bookings.review_requested_at — stamped when the "leave a testimonial"
 *     link is sent (auto on checkout, or manually re-sent). Guards the auto-send
 *     so a checkout doesn't fire the request twice, and drives the dashboard's
 *     "requested / received" status.
 *
 * The `reviews` table already carries is_published (moderation flag),
 * rating_overall, comment, booking_id and the polymorphic subject — no new
 * columns needed there beyond guest_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews') && ! Schema::hasColumn('reviews', 'guest_name')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->string('guest_name')->nullable()->after('comment');
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'review_requested_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('review_requested_at')->nullable()->after('checked_out_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reviews', 'guest_name')) {
            Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('guest_name'));
        }
        if (Schema::hasColumn('bookings', 'review_requested_at')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('review_requested_at'));
        }
    }
};
