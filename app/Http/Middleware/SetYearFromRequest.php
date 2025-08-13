<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetYearFromRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Se c'è un parametro year nella request
        if ($request->has('year')) {
            session(['selected_year' => $request->year]);
        }

        // Se stiamo visualizzando un torneo specifico
        if ($request->route('tournament')) {
            $tournament = $request->route('tournament');
            $year = \Carbon\Carbon::parse($tournament->start_date)->year;
            session(['selected_year' => $year]);
        }

        return $next($request);
    }
}
