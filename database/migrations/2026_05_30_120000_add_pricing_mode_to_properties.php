<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // 'whole_house' = one flat price for the entire property regardless
            //                 of bedroom count (most Malaysian homestay listings)
            // 'per_room'   = each room books + prices independently (boutique,
            //                 hostel-style, or shared-house listings)
            $table->string('pricing_mode', 20)->default('whole_house')->after('toilets');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
