<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TournamentController extends Controller
{
/**
 * Public Calendar - View only focus
 * Route: /tournaments/calendar
 */
/**
 * Public Calendar - View only focus
 * Route: /tournaments/calendar
 */
public function calendar(Request $request): View
{
    $user = auth()->user();

    try {
        // Get all published tournaments (public view)
        $tournaments = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->where('status', 'published') // Only published for public
            ->orderBy('start_date', 'asc')
            ->get();

        // Get filter data for public filtering
        $zones = \App\Models\Zone::orderBy('name')->get();
        $tournamentTypes = \App\Models\TournamentCategory::orderBy('name')->get();
        $clubs = \App\Models\Club::orderBy('name')->get();

        // === STANDARDIZED CALENDAR DATA ===
        $calendarData = [
            // Core tournament data
            'tournaments' => $tournaments->map(function ($tournament) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name,
                    'start' => $tournament->start_date->format('Y-m-d'),
                    'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                    'color' => $this->getEventColor($tournament),
                    'borderColor' => $this->getPublicBorderColor($tournament),
                    'extendedProps' => [
                        // Basic info
                        'club' => $tournament->club->name ?? 'N/A',
                        'zone' => $tournament->zone->name ?? 'N/A',
                        'zone_id' => $tournament->zone_id,
                        'category' => $tournament->tournamentCategory->name ?? 'N/A',
                        'status' => $tournament->status,
                        'tournament_url' => route('tournaments.show', $tournament),

                        // Dates & deadlines
                        'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                        'days_until_deadline' => $tournament->days_until_deadline ?? 0,

                        // Type info (important for public filtering)
                        'type_id' => $tournament->tournament_category_id,
                        'type' => $tournament->tournamentCategory,

                        // Admin-specific (not applicable for public)
                        'availabilities_count' => 0,
                        'assignments_count' => 0,
                        'required_referees' => 0,
                        'max_referees' => 0,
                        'management_priority' => 'none',

                        // Referee-specific (not applicable for public)
                        'is_available' => false,
                        'is_assigned' => false,
                        'can_apply' => false,
                        'personal_status' => 'none',
                    ],
                ];
            }),

            // Context data
            'userType' => 'public',
            'userRoles' => $user ? [$user->user_type] : ['guest'],
            'canModify' => false, // Public view is read-only

            // === FILTER DATA (important for public) ===
            'zones' => $zones,
            'types' => $tournamentTypes,
            'clubs' => $clubs,

            // User-specific data (not applicable)
            'availabilities' => [],
            'assignments' => [],

            // Metadata
            'totalTournaments' => $tournaments->count(),
            'lastUpdated' => now()->toISOString(),
        ];

        return view('tournaments.calendar', compact('calendarData'));

    } catch (\Exception $e) {
        \Log::error('Public calendar error', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);

        // Return error state
        return view('tournaments.calendar', [
            'calendarData' => [
                'tournaments' => collect(),
                'userType' => 'public',
                'userRoles' => ['guest'],
                'canModify' => false,
                'zones' => collect(),
                'types' => collect(),
                'clubs' => collect(),
                'availabilities' => [],
                'assignments' => [],
                'totalTournaments' => 0,
                'lastUpdated' => now()->toISOString(),
                'error_state' => 'error',
                'error' => 'Errore nel caricamento del calendario pubblico.'
            ]
        ]);
    }
}

/**
 * Get event color based on tournament category (same as admin/referee)
 */
private function getEventColor($tournament): string
{
    return match($tournament->tournamentCategory->name ?? 'default') {
        'Categoria A' => '#FF6B6B',
        'Categoria B' => '#4ECDC4',
        'Categoria C' => '#45B7D1',
        'Categoria D' => '#96CEB4',
        default => '#3B82F6'
    };
}

/**
 * Public border color - based on tournament status (like admin)
 */
private function getPublicBorderColor($tournament): string
{
    return match($tournament->status) {
        'published' => '#10B981',   // Green - Published
        'in_progress' => '#3B82F6', // Blue - In progress
        'completed' => '#6B7280',   // Gray - Completed
        'cancelled' => '#EF4444',   // Red - Cancelled
        default => '#10B981'        // Green default (published)
    };
}
}
