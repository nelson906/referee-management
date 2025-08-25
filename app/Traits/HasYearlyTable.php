<?php

namespace App\Traits;

use App\Services\YearService;

trait HasYearlyTable
{
    protected $currentYear;

    public function setYearlyTable(?int $year = null): self
    {
        $year = $year ?? YearService::getCurrentYear();
        $this->currentYear = $year;

        $baseTable = $this->getYearlyTableBaseName();
        $this->table = YearService::getTableName($baseTable, $year);

        return $this;
    }

    public function getCurrentYear(): int
    {
        return $this->currentYear ?? YearService::getCurrentYear();
    }

    public function newQuery()
    {
        if (!str_contains($this->getTable(), '_')) {
            $this->setYearlyTable();
        }
        return parent::newQuery();
    }

    // Metodo che deve essere implementato dai modelli
    abstract protected function getYearlyTableBaseName(): string;

    // Metodo statico corretto
    public static function forYear(int $year): self
    {
        $instance = new static();
        return $instance->setYearlyTable($year);
    }
}
