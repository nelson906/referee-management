<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class YearSelector
{
public function handle($request, Closure $next)
{
    $year = session('selected_year', date('Y'));

    // Aggiorna la VIEW per puntare all'anno corretto
    try {
        DB::statement("CREATE OR REPLACE VIEW tournaments AS SELECT * FROM gare_{$year}");
    } catch (\Exception $e) {
        // Log errore ma continua
    }

    view()->share('currentYear', $year);

    return $next($request);
}
}
