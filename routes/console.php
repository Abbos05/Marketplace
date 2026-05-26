<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:purge-deleted', function () {
    $cutoff = now()->subDays(30);
    $purged = 0;

    User::onlyTrashed()
        ->where('deleted_at', '<=', $cutoff)
        ->chunkById(100, function ($users) use (&$purged) {
            foreach ($users as $user) {
                $user->forceDelete();
                $purged++;
            }
        });

    $this->info("Purged {$purged} deleted users.");
})->purpose('Permanently delete users after 30 days in trash');

Schedule::command('users:purge-deleted')->daily();
