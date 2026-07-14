<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Private statement link for affiliates — an unguessable token addressing a
 * read-only earnings page (tempahlah.com/affiliate/{token}). This is how an
 * EXTERNAL affiliate (no homestay login) sees their own clicks, referrals and
 * commissions, and submits their payout bank details. Same trust model as the
 * per-room iCal export token.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'statement_token')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('statement_token', 64)->nullable()->unique()->after('code');
            });
        }

        // Backfill a token for every existing affiliate so their link works.
        foreach (DB::table('affiliates')->whereNull('statement_token')->pluck('id') as $id) {
            DB::table('affiliates')->where('id', $id)->update([
                'statement_token' => (string) Str::ulid().Str::lower(Str::random(12)),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliates', 'statement_token')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->dropColumn('statement_token');
            });
        }
    }
};
