<?php

use App\Actions\ResetDemoData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reset {--force : Skip the confirmation prompt}', function (ResetDemoData $reset) {
    if (! $this->option('force') && ! $this->confirm('Delete all data and restore the demo seed?')) {
        return 1;
    }

    $reset->handle();
    $this->info('Demo data has been reset.');

    return 0;
})->purpose('Restore the demo data set (deletes everything)');
