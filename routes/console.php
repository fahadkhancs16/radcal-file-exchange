<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// exchanges:purge is invoked directly by a system cron entry at midnight
// (see CLAUDE.md / deployment notes) rather than through Laravel's own
// scheduler, so there is no Schedule::command() entry here.
