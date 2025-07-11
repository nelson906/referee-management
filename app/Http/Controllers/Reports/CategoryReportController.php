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
        $categories = TournamentType::withCount('tournaments')
            ->ordered()
            ->get();

        return view('reports.categories.index', compact('types'));
    }

    /**
     * Show specific category report.
     */
    public function show(TournamentCategory $category): View
    {
        return view('reports.categories.show', compact('category'));
    }

    /**
     * Export category report.
     */
    public function export(TournamentCategory $category)
    {
        return response()->json(['message' => 'Export in sviluppo']);
    }
}
