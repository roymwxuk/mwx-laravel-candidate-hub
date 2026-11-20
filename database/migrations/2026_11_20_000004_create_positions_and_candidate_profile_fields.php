<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // 希望年収 in 万円 (the unit Japanese resumes use).
            $table->unsignedInteger('expected_salary')->nullable()->after('skills');
            // Years of experience per skill, e.g. {"PHP": 5, "AWS": 1.5}.
            $table->json('skill_years')->nullable()->after('expected_salary');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn(['expected_salary', 'skill_years']);
        });

        Schema::dropIfExists('positions');
    }
};
