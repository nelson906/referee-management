<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Admin Calendar - Focus: Gestione amministrativa
     * - Chi manca?
     * - Scadenze
     * - Stato completamento
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Get tournaments for admin management
        $tournaments = Tournament::with(['tournamentCategory', 'zone', 'club', 'assignments', 'availabilities'])
            ->when(!$isNationalAdmin, function ($q) use ($user) {
                if ($user->zone_id) {
                    $q->where('zone_id', $user->zone_id);
                }
            })
            ->get();

        // Format for admin calendar - focus on management data
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name,
                    'start' => $tournament->start_date->format('Y-m-d'),
                    'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                    'color' => $this->getAdminEventColor($tournament),
                    'borderColor' => $this->getAdminBorderColor($tournament),
                    'extendedProps' => [
                        'club' => $tournament->club->name ?? 'N/A',
                        'zone' => $tournament->zone->name ?? 'N/A',
                        'category' => $tournament->tournamentCategory->name ?? 'N/A',
                        'status' => $tournament->status,
                        'availabilities_count' => $tournament->availabilities()->count(),
                        'assignments_count' => $tournament->assignments()->count(),
                        'required_referees' => $tournament->required_referees ?? 1,
                        'max_referees' => $tournament->max_referees ?? 4,
                        'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                        'days_until_deadline' => $tournament->days_until_deadline ?? 0,
                        'tournament_url' => route('admin.tournaments.show', $tournament),
                        'management_priority' => $this->getManagementPriority($tournament),
                    ],
                ];
            }),
            'zones' => $isNationalAdmin ? Zone::orderBy('name')->get() : collect(),
            'userType' => 'admin',
        ];

        return view('admin.calendar.index', compact('calendarData', 'isNationalAdmin'));
    }

    /**
     * Admin color logic - based on YOUR original tournament category colors
     * TODO: Replace with your actual category color logic
     */
    private function getAdminEventColor($tournament): string
    {
        // TODO: Implement YOUR original category-based colors
        // return $tournament->tournamentCategory->color ?? '#3B82F6';

        // Temporary - replace with your logic:
        return match($tournament->tournamentCategory->name ?? 'default') {
            'Categoria A' => '#FF6B6B',
            'Categoria B' => '#4ECDC4',
            'Categoria C' => '#45B7D1',
            'Categoria D' => '#96CEB4',
            default => '#3B82F6'
        };
    }

    /**
     * Admin border logic - based on management status
     */
    private function getAdminBorderColor($tournament): string
    {
        return match($tournament->status) {
            'published' => '#10B981',
            'draft' => '#F59E0B',
            'closed' => '#6B7280',
            'cancelled' => '#EF4444',
            default => '#6B7280'
        };
    }

    /**
     * Calculate management priority for admin focus
     */
    private function getManagementPriority($tournament): string
    {
        $availabilities = $tournament->availabilities()->count();
        $assignments = $tournament->assignments()->count();
        $required = $tournament->required_referees ?? 1;
        $daysUntilDeadline = $tournament->days_until_deadline ?? 999;

        if ($daysUntilDeadline < 0 || $assignments < $required) {
            return 'urgent';
        }

        if ($assignments >= $required) {
            return 'complete';
        }

        if ($availabilities > 0) {
            return 'in_progress';
        }

        return 'open';
    }
}
