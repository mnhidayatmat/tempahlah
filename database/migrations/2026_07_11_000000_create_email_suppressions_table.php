<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses that hard-bounced or filed a spam complaint via SES. Global, NOT
 * tenant-scoped: an email belongs to a person, and one guest can book across
 * several tenants — a dead mailbox is dead for all of them. The outbound mail
 * guard (HaltMailToSuppressed) reads this before every send so we never mail a
 * known-bad address again, which is what keeps our SES bounce/complaint rate
 * under AWS's thresholds.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_suppressions')) {
            return;
        }

        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();      // stored lowercased
            $table->string('reason', 20);                // bounce | complaint
            $table->string('subtype', 60)->nullable();   // Permanent | complaint feedback type | ...
            $table->text('diagnostic')->nullable();       // SES diagnosticCode / feedback detail
            $table->string('source', 20)->default('ses');
            $table->timestamp('suppressed_at');
            $table->timestamps();

            $table->index(['reason', 'suppressed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
