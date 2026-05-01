<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();

            $table->string('document_type')->default('invoice');
            $table->string('invoice_number');
            $table->string('locale', 5)->default('ms');

            $table->json('billed_to');
            $table->json('line_items');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('sst_amount', 12, 2)->default(0);
            $table->decimal('tourism_tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('MYR');

            $table->string('status')->default('draft');
            $table->string('pdf_path')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
