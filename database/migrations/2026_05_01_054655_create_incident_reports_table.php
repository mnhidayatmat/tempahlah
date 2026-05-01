<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('category');
            $table->string('severity')->default('low');
            $table->text('description');
            $table->json('evidence_paths')->nullable();
            $table->decimal('damage_estimate', 10, 2)->nullable();
            $table->string('police_report_number')->nullable();

            $table->boolean('escalate_to_blacklist')->default(false);
            $table->foreignId('blacklist_entry_id')->nullable()->constrained('guest_blacklist_entries')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'severity']);
            $table->index(['guest_user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
