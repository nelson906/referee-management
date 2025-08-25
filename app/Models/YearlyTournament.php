<?php

namespace App\Models;

use App\Traits\HasYearlyTable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YearlyTournament extends Tournament
{
    use HasYearlyTable;

    protected function getYearlyTableBaseName(): string
    {
        return 'tournaments';
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(YearlyAssignment::class, 'tournament_id');
    }
}
