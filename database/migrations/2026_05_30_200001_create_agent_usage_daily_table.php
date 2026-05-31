<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant per-day rolling counter of agent activity.
 *
 * Drives the "X / 200 replies today" meter in settings, the daily cap
 * enforcement, and (eventually) super-admin cost reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('day');

            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();

            $table->unsignedInteger('inbound_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->unsignedInteger('tool_calls')->default(0);
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_usage_daily');
    }
};
