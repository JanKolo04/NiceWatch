<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('nicewatch:mark-offline-hosts')->everyMinute()->withoutOverlapping();
