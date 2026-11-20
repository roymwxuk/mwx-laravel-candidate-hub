<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('summary')->nullable();
            $table->json('skills')->nullable();
            $table->string('stage')->default('document_screening')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('company');
            $table->string('title')->nullable();
            // Resumes are month-precision, so store "YYYY-MM" rather than a fake day.
            $table->string('started_on', 7)->nullable();
            $table->string('ended_on', 7)->nullable(); // null = 現在
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('candidate_interviewer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['candidate_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_interviewer');
        Schema::dropIfExists('work_histories');
        Schema::dropIfExists('candidates');
    }
};
