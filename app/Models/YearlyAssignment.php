<?php

namespace App\Models;

use App\Traits\HasYearlyTable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearlyAssignment extends Assignment
{
    use HasYearlyTable;

    protected function getYearlyTableBaseName(): string
    {
        return 'assignments';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(YearlyTournament::class, 'tournament_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}
