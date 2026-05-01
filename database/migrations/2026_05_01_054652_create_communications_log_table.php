<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related');
            $table->string('channel');
            $table->string('template_key')->nullable();
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'status']);
            $table->index('to_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications_log');
    }
};
