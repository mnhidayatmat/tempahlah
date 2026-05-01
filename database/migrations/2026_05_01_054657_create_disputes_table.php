<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('opened_by');

            $table->string('reason');
            $table->text('description');
            $table->json('evidence_paths')->nullable();
            $table->decimal('amount_claimed', 12, 2)->nullable();

            $table->string('status')->default('open');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->decimal('resolution_amount', 12, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'assigned_admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
