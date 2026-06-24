<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight maintenance-person (handyman/contractor) registry — name +
 * optional phone/email — for the tenant's contact directory. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_persons')) {
            Schema::create('maintenance_persons', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $t->string('name');
                $t->string('phone')->nullable();
                $t->string('email')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_persons');
    }
};
