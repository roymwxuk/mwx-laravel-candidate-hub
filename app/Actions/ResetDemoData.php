<?php

namespace App\Actions;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puts the public demo back into its seeded state: wipes candidates, uploaded
 * resumes, audit logs, users and queued jobs, then runs the demo seeder again.
 *
 * Only reachable when demo mode is enabled (config('app.demo_mode')).
 */
class ResetDemoData
{
    /**
     * Children before parents, so foreign keys never block a delete.
     */
    private const TABLES = [
        'audit_logs',
        'candidate_interviewer',
        'work_histories',
        'resume_documents',
        'candidates',
        'positions',
        'users',
        'jobs',
        'failed_jobs',
    ];

    public function handle(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }

        Storage::disk('local')->deleteDirectory('resumes');

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
    }
}
