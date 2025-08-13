<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class YearHelper
{
    /**
     * Verifica quali anni hanno dati
     */
    public static function getAvailableYears()
    {
        $years = [];

        for ($year = 2015; $year <= date('Y'); $year++) {
            if (Schema::hasTable("tournaments_{$year}")) {
                $count = DB::table("tournaments_{$year}")->count();
                if ($count > 0) {
                    $years[] = [
                        'year' => $year,
                        'tournaments' => $count,
                        'assignments' => Schema::hasTable("assignments_{$year}")
                            ? DB::table("assignments_{$year}")->count() : 0,
                        'availabilities' => Schema::hasTable("availabilities_{$year}")
                            ? DB::table("availabilities_{$year}")->count() : 0,
                    ];
                }
            }
        }

        return $years;
    }

    /**
     * Cambia anno in sessione
     */
    public static function setYear($year)
    {
        session(['selected_year' => $year]);
    }

    /**
     * Ottieni anno corrente dalla sessione
     */
    public static function getCurrentYear()
    {
        return session('selected_year', date('Y'));
    }
}
