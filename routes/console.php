<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertas programados na agenda: saem sozinhos no horário marcado.
// Precisa do agendador ligado no servidor (php artisan schedule:work, ou o
// cron do cPanel chamando schedule:run a cada minuto). Ver RAILWAY.md.
Schedule::command('agenda:enviar-alertas')->everyMinute()->withoutOverlapping();
