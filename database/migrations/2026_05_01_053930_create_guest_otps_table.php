<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_otps', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->string('channel')->default('email');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['identifier', 'channel']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_otps');
    }
};
