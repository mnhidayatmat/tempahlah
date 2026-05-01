<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('channel');
            $table->string('mode')->default('ical');
            $table->boolean('two_way')->default(false);

            $table->text('credentials_encrypted')->nullable();
            $table->string('ical_export_url')->nullable();
            $table->string('ical_import_url')->nullable();
            $table->string('external_account_id')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'room_id', 'channel']);
            $table->index(['channel', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_integrations');
    }
};
