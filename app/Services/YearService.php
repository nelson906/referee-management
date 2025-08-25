<?php

/**
 * ===============================================
 * IMPLEMENTAZIONE DEFINITIVA - COPIA ESATTA
 * ===============================================
 * Questa è la versione finale da implementare
 */

// ===== FILE 1: app/Services/YearService.php =====
namespace App\Services;

use Illuminate\Support\Facades\Schema;

class YearService
{
    public static function getCurrentYear(): int
    {
        return session('selected_year', date('Y'));
    }

    public static function setYear(int $year): void
    {
        session(['selected_year' => $year]);
    }

    public static function getAvailableYears(): array
    {
        return range(2015, 2025);
    }

    public static function getTableName(string $baseTable, int $year): string
    {
        if (in_array($baseTable, ['tournaments', 'assignments'])) {
            return "{$baseTable}_{$year}";
        }
        return $baseTable;
    }

    public static function tableExists(string $baseTable, int $year): bool
    {
        $tableName = self::getTableName($baseTable, $year);
        return Schema::hasTable($tableName);
    }
}
