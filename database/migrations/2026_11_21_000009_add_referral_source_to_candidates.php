<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            // 応募経路（経由・紹介）, typed by HR: e.g. "LinkedIn", "doda", "社員紹介（山田）".
            $table->string('referral_source', 100)->nullable()->after('position_id');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('referral_source');
        });
    }
};
