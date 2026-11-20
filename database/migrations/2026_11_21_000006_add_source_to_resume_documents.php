<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_documents', function (Blueprint $table) {
            // Who uploaded it: 'staff' (HR, reviewed in 履歴書取込) or 'application'
            // (an applicant auto-filling the public form, reviewed by the applicant).
            $table->string('source', 20)->default('staff')->after('candidate_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('resume_documents', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
