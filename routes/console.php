<?php

use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;

// Runs on every container start. Migrates, seeds an empty database, and on the public demo
// starts over from fresh data once it is older than demo_reset_hours, so visitors always
// find a usable state without a separate cron service.
Artisan::command('demo:prepare', function () {
    $this->call('migrate', ['--force' => true]);

    $first = Warehouse::query()->oldest('id')->first();
    if (! $first) {
        $this->call('db:seed', ['--force' => true]);
        $this->info('Seeded demo data.');

        return;
    }

    $hours = config('app.demo_reset_hours');
    if ($hours > 0 && $first->created_at->lt(now()->subHours($hours))) {
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->info("Demo data was older than {$hours} hours and has been reset.");
    }
})->purpose('Migrate, seed and reset the demo data when it is stale');
