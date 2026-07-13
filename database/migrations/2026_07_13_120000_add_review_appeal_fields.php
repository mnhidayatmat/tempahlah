<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appeal workflow on guest testimonials: a tenant can request that the super
 * admin hide a testimonial (e.g. it's unfair/abusive/off-topic), giving a
 * reason. The admin reviews and either approves (hides it) or rejects it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'appeal_status')) {
                // null = no appeal; 'pending' | 'approved' | 'rejected'
                $table->string('appeal_status')->nullable()->after('is_published');
            }
            if (! Schema::hasColumn('reviews', 'appeal_reason')) {
                $table->text('appeal_reason')->nullable()->after('appeal_status');
            }
            if (! Schema::hasColumn('reviews', 'appealed_at')) {
                $table->timestamp('appealed_at')->nullable()->after('appeal_reason');
            }
            if (! Schema::hasColumn('reviews', 'appeal_reviewed_at')) {
                $table->timestamp('appeal_reviewed_at')->nullable()->after('appealed_at');
            }
            if (! Schema::hasColumn('reviews', 'appeal_admin_note')) {
                $table->text('appeal_admin_note')->nullable()->after('appeal_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            foreach (['appeal_status', 'appeal_reason', 'appealed_at', 'appeal_reviewed_at', 'appeal_admin_note'] as $col) {
                if (Schema::hasColumn('reviews', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
