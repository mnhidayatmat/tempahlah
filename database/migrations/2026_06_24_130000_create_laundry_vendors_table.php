<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight laundry-vendor (dobi) registry — name + optional phone/email —
 * so a tenant can assign a registered vendor to a laundry batch. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laundry_vendors')) {
            Schema::create('laundry_vendors', function (Blueprint $t) {
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

        if (Schema::hasTable('laundry_tasks') && ! Schema::hasColumn('laundry_tasks', 'vendor_id')) {
            Schema::table('laundry_tasks', function (Blueprint $t) {
                $t->foreignId('vendor_id')->nullable()->after('vendor_name')
                    ->constrained('laundry_vendors')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('laundry_tasks') && Schema::hasColumn('laundry_tasks', 'vendor_id')) {
            Schema::table('laundry_tasks', function (Blueprint $t) {
                $t->dropConstrainedForeignId('vendor_id');
            });
        }

        Schema::dropIfExists('laundry_vendors');
    }
};
