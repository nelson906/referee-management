<?php

namespace App\Http\Middleware;

use Closure;

class YearSelector
{
    public function handle($request, Closure $next)
    {
        // Imposta anno di default se non presente
        if (!session()->has('selected_year')) {
            session(['selected_year' => date('Y')]);
        }

        // Rendi l'anno disponibile a tutte le view
        view()->share('currentYear', session('selected_year'));

        return $next($request);
    }
}
