<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone expense ledger — capital spend (renovation, pool upgrade,
 * furniture) and consumables (soap, detergent, toilet items) that aren't tied
 * to a single cleaning/laundry/maintenance task. Host-entered `incurred_at`
 * date (mirrors the maintenance-ticket date pattern). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
                $t->string('category')->default('other'); // renovation|upgrade|supplies|toilet|furniture|utility|repair|other
                $t->string('title');
                $t->text('description')->nullable();
                $t->decimal('amount', 10, 2)->default(0);
                $t->string('paid_to')->nullable(); // vendor / shop, free text
                $t->date('incurred_at');
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'incurred_at']);
                $t->index(['tenant_id', 'category']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
