<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Comped accounts (staff, demos, early partners) are never billed
            // and never downgraded. isPaid() short-circuits to true on this.
            if (! Schema::hasColumn('subscriptions', 'comped_at')) {
                $table->timestamp('comped_at')->nullable()->after('cancelled_at');
            }

            // While a lapsed subscription sits inside its grace window it stays
            // past_due but KEEPS its paid features, so a failed payment does not
            // instantly break the tenant's live guest booking flow.
            if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                $table->timestamp('grace_ends_at')->nullable()->after('comped_at');
            }

            // Stamped the first time a tenant starts the free trial. Downgrading
            // clears trial_ends_at, so without this a tenant could upgrade →
            // downgrade → upgrade and farm unlimited trials.
            if (! Schema::hasColumn('subscriptions', 'trial_used_at')) {
                $table->timestamp('trial_used_at')->nullable()->after('grace_ends_at');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! $this->hasIndex('subscriptions_grace_ends_at_index')) {
                $table->index('grace_ends_at');
            }
        });

        // Grandfather every subscription that is already on the paid plan when
        // this migration runs. Until platform billing exists (Phase 2) nobody
        // can actually have paid us, so any existing paid row was granted by the
        // old self-serve upgrade button. Comping them here — in the same
        // transaction that tightens isPaid() — is what stops a live tenant
        // silently losing Pro the instant this deploys.
        DB::table('subscriptions')
            ->where('plan', 'paid')
            ->whereNull('comped_at')
            ->update([
                'comped_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if ($this->hasIndex('subscriptions_grace_ends_at_index')) {
                $table->dropIndex('subscriptions_grace_ends_at_index');
            }

            foreach (['comped_at', 'grace_ends_at', 'trial_used_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Schema::hasIndex() is not available across all supported drivers here, so
     * fall back to the schema builder's own introspection.
     */
    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('subscriptions') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
