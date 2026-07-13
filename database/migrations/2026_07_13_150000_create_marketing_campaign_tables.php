<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform email marketing (Platform Admin → Email marketing): campaigns the
 * platform sends to its HOSTS (e.g. free → Pro upgrade pitches). Deliberately
 * NOT tenant-scoped — this is Tempahlah mailing its own customers, so both
 * tables live outside the BelongsToTenant world, like subscription_invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_campaigns')) {
            Schema::create('marketing_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('subject', 200);
                $table->text('body_md');
                // Who receives it: free | pro | ultra | paid | all (see model).
                $table->string('audience', 20)->default('free');
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('recipients_total')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->timestamp('test_sent_at')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_campaign_recipients')) {
            Schema::create('marketing_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('email', 190);
                $table->string('name', 160)->nullable();
                // pending | sent | failed | skipped
                $table->string('status', 20)->default('pending');
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['campaign_id', 'tenant_id']);
                $table->index(['campaign_id', 'status']);
            });
        }

        // Marketing opt-out (PDPA): a host who unsubscribes never receives
        // another campaign. Transactional email is unaffected.
        if (! Schema::hasColumn('tenants', 'marketing_opt_out_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->timestamp('marketing_opt_out_at')->nullable()->after('booking_link_shared_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_recipients');
        Schema::dropIfExists('marketing_campaigns');

        if (Schema::hasColumn('tenants', 'marketing_opt_out_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('marketing_opt_out_at');
            });
        }
    }
};
