<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            // 最終学歴の詳細 (the level itself is education_level).
            $table->string('school_name', 200)->nullable()->after('education_level');
            $table->string('major', 200)->nullable()->after('school_name');
            $table->string('graduated_on', 7)->nullable()->after('major'); // YYYY-MM
            // 語学力, kept apart from skills: e.g. ["英語（TOEIC 850点）", "中国語（日常会話）"].
            $table->json('languages')->nullable()->after('skills');
            $table->string('desired_location', 200)->nullable()->after('expected_salary');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'school_name', 'major', 'graduated_on', 'languages', 'desired_location']);
        });
    }
};
