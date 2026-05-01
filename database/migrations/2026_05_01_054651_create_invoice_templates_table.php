<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_type')->default('invoice');
            $table->string('locale_default', 5)->default('ms');
            $table->string('logo_path')->nullable();
            $table->string('color_primary', 7)->default('#0ea5e9');
            $table->string('number_prefix')->default('INV');
            $table->unsignedInteger('next_number')->default(1);
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->text('terms_text')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->boolean('show_sst')->default(true);
            $table->boolean('show_tourism_tax')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'document_type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
