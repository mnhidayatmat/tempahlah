<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_integrations.config was created as JSON but the model casts it as
 * `encrypted:array` — Laravel writes an opaque encrypted string, MySQL's
 * JSON column refuses non-JSON values with error 3140. Convert to TEXT.
 *
 * Safe — column has been effectively unwritable since launch, so any
 * existing rows are empty/null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->json('config')->nullable()->change();
        });
    }
};
