<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\RunExperimentJob;
use App\Jobs\CalculateScoresJob;

// ── Scheduler (equivalente al Kernel en Laravel 11) ───────────────────────────

// Recalcular scores cada 6 horas
Schedule::job(new CalculateScoresJob(), 'scoring')
    ->everySixHours()
    ->withoutOverlapping()
    ->name('scoring:all');

// Re-correr experimentos cada domingo a las 3am
// (esto ya encadena scoring + pronóstico Chile automáticamente — ver RunExperimentJob)
foreach (array_keys(config('fashion.experiments', [])) as $key) {
    Schedule::job(new RunExperimentJob($key), 'experiments')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->withoutOverlapping()
        ->name("experiment:{$key}");
}

// Evaluar predicciones maduras contra la realidad — solo consultas a la BD,
// sin llamadas externas, así que corre a diario sin costo ni riesgo de bloqueo.
Schedule::command('predictions:evaluate')
    ->daily()
    ->at('04:00')
    ->withoutOverlapping()
    ->name('predictions:evaluate-daily');

// Refrescar el backtest histórico — cada semana hay una semana más de
// "futuro real" disponible para calibrar checkpoints que antes estaban
// demasiado cerca de "hoy". Corre los sábados, separado del experimento
// del domingo, para no competir por el mismo límite de Google Trends.
Schedule::command('trends:backtest')
    ->weekly()
    ->saturdays()
    ->at('03:00')
    ->withoutOverlapping()
    ->name('trends:backtest-weekly');
