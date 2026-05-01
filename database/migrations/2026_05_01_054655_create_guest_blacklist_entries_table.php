<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_blacklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_by_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            $table->string('severity');
            $table->string('reason_code');
            $table->text('description');
            $table->json('evidence_paths')->nullable();

            $table->string('review_status')->default('pending');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->boolean('appealed')->default(false);
            $table->text('appeal_message')->nullable();
            $table->timestamp('appealed_at')->nullable();
            $table->string('appeal_outcome')->nullable();

            $table->timestamps();

            $table->index(['guest_user_id', 'severity']);
            $table->index(['review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_blacklist_entries');
    }
};
