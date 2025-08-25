<?php

// app/Traits/YearAwareTables.php
namespace App\Traits;

trait YearAwareTables
{
    protected function getYearTables()
    {
        $year = session('selected_year', date('Y'));
        return [
            'tournaments' => "tournaments_{$year}",
            'assignments' => "assignments_{$year}",
            'availabilities' => "availabilities_{$year}",
            'year' => $year
        ];
    }

    protected function yearTable($type)
    {
        $tables = $this->getYearTables();
        return $tables[$type] ?? null;
    }
}
