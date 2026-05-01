<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->after('id')->unique();
            $table->string('phone', 20)->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('locale', 5)->default('ms')->after('remember_token');
            $table->text('mykad_encrypted')->nullable()->after('locale');
            $table->string('avatar_path')->nullable()->after('mykad_encrypted');
            $table->json('fcm_tokens')->nullable()->after('avatar_path');
            $table->string('user_type')->default('tenant_user')->after('fcm_tokens');
            $table->timestamp('last_login_at')->nullable()->after('user_type');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes()->after('updated_at');

            $table->index('phone');
            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['phone']);
            $table->dropIndex(['user_type']);
            $table->dropColumn([
                'public_id', 'phone', 'phone_verified_at', 'locale',
                'mykad_encrypted', 'avatar_path', 'fcm_tokens',
                'user_type', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
