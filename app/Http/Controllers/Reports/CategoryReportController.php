<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TournamentType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryReportController extends Controller
{
    /**
     * Display category reports listing.
     */
    public function index(): View
    {
        // ✅ FIXED: Using correct orderBy instead of ordered() method
        $types = TournamentType::withCount('tournaments')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('reports.categories.index', compact('types'));
    }

    /**
     * Show specific category report.
     */
    public function show(TournamentType $type): View // ✅ FIXED: Parameter name
    {
        return view('reports.categories.show', compact('type'));
    }

    /**
     * Export category report.
     */
    public function export(TournamentType $type)
    {
        return response()->json(['message' => 'Export in sviluppo']);
    }
}
