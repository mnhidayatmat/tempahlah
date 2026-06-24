<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight cleaner registry (no login accounts) — name + optional phone/
 * email — so a tenant can assign a named cleaner to a cleaning task. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cleaners')) {
            Schema::create('cleaners', function (Blueprint $t) {
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

        if (Schema::hasTable('cleaning_tasks') && ! Schema::hasColumn('cleaning_tasks', 'cleaner_id')) {
            Schema::table('cleaning_tasks', function (Blueprint $t) {
                $t->foreignId('cleaner_id')->nullable()->after('assigned_to_user_id')
                    ->constrained('cleaners')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cleaning_tasks') && Schema::hasColumn('cleaning_tasks', 'cleaner_id')) {
            Schema::table('cleaning_tasks', function (Blueprint $t) {
                $t->dropConstrainedForeignId('cleaner_id');
            });
        }

        Schema::dropIfExists('cleaners');
    }
};
