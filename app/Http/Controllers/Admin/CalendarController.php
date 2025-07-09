<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Zone;
use App\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Admin Calendar - Management focus
     */
    /**
     * Admin Calendar with comprehensive error handling
     */
    public function index(Request $request): View
    {
        try {
            $user = auth()->user();
            $isNationalAdmin = $user->user_type === 'national_admin';

            // Validate user permissions
            if (!in_array($user->user_type, ['admin', 'national_admin', 'super_admin'])) {
                \Log::warning('Unauthorized calendar access attempt', [
                    'user_id' => $user->id,
                    'user_type' => $user->user_type,
                    'ip' => $request->ip()
                ]);

                abort(403, 'Non hai i permessi per accedere al calendario amministrativo.');
            }

            // Validate zone for non-national admins
            if (!$isNationalAdmin && !$user->zone_id) {
                \Log::error('Admin user without zone_id trying to access calendar', [
                    'user_id' => $user->id,
                    'user_type' => $user->user_type
                ]);

                return view('admin.calendar', [
                    'calendarData' => $this->getEmptyCalendarData('error'),
                    'isNationalAdmin' => false,
                    'error' => 'Il tuo account non ha una zona assegnata. Contatta l\'amministratore di sistema.'
                ]);
            }

            // Get tournaments with error handling
            $tournaments = $this->getTournamentsWithErrorHandling($user, $isNationalAdmin);

            // Get filter data with error handling
            $zones = $this->getZonesWithErrorHandling($isNationalAdmin);
            $tournamentTypes = $this->getTournamentTypesWithErrorHandling();

            // Build calendar data
            $calendarData = $this->buildCalendarData($tournaments, $zones, $tournamentTypes, $user);

            // \Log successful access
            \Log::info('Admin calendar accessed successfully', [
                'user_id' => $user->id,
                'tournaments_count' => $tournaments->count(),
                'is_national_admin' => $isNationalAdmin
            ]);

            return view('admin.calendar', compact('calendarData', 'isNationalAdmin'));

        } catch (Exception $e) {
            // \Log the error
            \Log::error('Admin calendar error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return error state
            return view('admin.calendar', [
                'calendarData' => $this->getEmptyCalendarData('error'),
                'isNationalAdmin' => false,
                'error' => 'Si è verificato un errore nel caricamento del calendario. Riprova più tardi.'
            ]);
        }
    }

    /**
     * Get tournaments with comprehensive error handling
     */
    private function getTournamentsWithErrorHandling($user, $isNationalAdmin)
    {
        try {
            $query = Tournament::with(['tournamentCategory', 'zone', 'club', 'assignments', 'availabilities']);

            // Apply zone filter for non-national admins
            if (!$isNationalAdmin) {
                if (!$user->zone_id) {
                    throw new Exception('Admin user missing zone_id');
                }
                $query->where('zone_id', $user->zone_id);
            }

            $tournaments = $query->get();

            // Validate relationships
            foreach ($tournaments as $tournament) {
                if (!$tournament->tournamentCategory) {
                    \Log::warning('Tournament missing category', ['tournament_id' => $tournament->id]);
                }
                if (!$tournament->zone) {
                    \Log::warning('Tournament missing zone', ['tournament_id' => $tournament->id]);
                }
                if (!$tournament->club) {
                    \Log::warning('Tournament missing club', ['tournament_id' => $tournament->id]);
                }
            }

            return $tournaments;

        } catch (Exception $e) {
            \Log::error('Error fetching tournaments for admin calendar', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Return empty collection on error
            return collect();
        }
    }

    /**
     * Get zones with error handling
     */
    private function getZonesWithErrorHandling($isNationalAdmin)
    {
        try {
            return $isNationalAdmin ? Zone::orderBy('name')->get() : collect();
        } catch (Exception $e) {
            \Log::error('Error fetching zones for admin calendar', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get tournament types with error handling
     */
    private function getTournamentTypesWithErrorHandling()
    {
        try {
            return TournamentCategory::orderBy('name')->get();
        } catch (Exception $e) {
            \Log::error('Error fetching tournament types for admin calendar', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Build calendar data with validation
     */
    private function buildCalendarData($tournaments, $zones, $tournamentTypes, $user)
    {
        try {
            return [
                'tournaments' => $tournaments->map(function ($tournament) {
                    return $this->formatTournamentWithValidation($tournament);
                }),
                'userType' => 'admin',
                'userRoles' => [$user->user_type],
                'canModify' => true,
                'zones' => $zones,
                'types' => $tournamentTypes,
                'clubs' => collect(),
                'availabilities' => [],
                'assignments' => [],
                'totalTournaments' => $tournaments->count(),
                'lastUpdated' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            \Log::error('Error building calendar data', ['error' => $e->getMessage()]);
            return $this->getEmptyCalendarData('error');
        }
    }

    /**
     * Format tournament data with validation
     */
    private function formatTournamentWithValidation($tournament)
    {
        try {
            // Validate required fields
            $title = $tournament->name ?: "Torneo #{$tournament->id}";
            $startDate = $tournament->start_date ?: now();
            $endDate = $tournament->end_date ?: $startDate;

            // Safely get related data
            $club = $tournament->club ? $tournament->club->name : 'Club non specificato';
            $zone = $tournament->zone ? $tournament->zone->name : 'Zona non specificata';
            $category = $tournament->tournamentCategory ? $tournament->tournamentCategory->name : 'Categoria non specificata';

            return [
                'id' => $tournament->id,
                'title' => $title,
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->addDay()->format('Y-m-d'),
                'color' => $this->getEventColorSafe($tournament),
                'borderColor' => $this->getAdminBorderColorSafe($tournament),
                'extendedProps' => [
                    'club' => $club,
                    'zone' => $zone,
                    'zone_id' => $tournament->zone_id,
                    'category' => $category,
                    'status' => $tournament->status ?: 'unknown',
                    'tournament_url' => route('admin.tournaments.show', $tournament),
                    'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'Non specificata',
                    'days_until_deadline' => $tournament->days_until_deadline ?? 0,
                    'type_id' => $tournament->tournament_category_id,
                    'type' => $tournament->tournamentCategory,
                    'availabilities_count' => $tournament->availabilities()->count(),
                    'assignments_count' => $tournament->assignments()->count(),
                    'required_referees' => $tournament->required_referees ?? 1,
                    'max_referees' => $tournament->max_referees ?? 4,
                    'management_priority' => $this->getManagementPrioritySafe($tournament),
                ],
            ];

        } catch (Exception $e) {
            \Log::error('Error formatting tournament', [
                'tournament_id' => $tournament->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            // Return minimal safe tournament data
            return [
                'id' => $tournament->id ?? 0,
                'title' => 'Torneo (errore dati)',
                'start' => now()->format('Y-m-d'),
                'end' => now()->addDay()->format('Y-m-d'),
                'color' => '#6B7280',
                'borderColor' => '#EF4444',
                'extendedProps' => [
                    'club' => 'Errore dati',
                    'zone' => 'Errore dati',
                    'category' => 'Errore dati',
                    'status' => 'error',
                    'error' => 'Dati torneo incompleti'
                ],
            ];
        }
    }

    /**
     * Safe color methods with fallbacks
     */
    private function getEventColorSafe($tournament): string
    {
        try {
            return $this->getEventColor($tournament);
        } catch (Exception $e) {
            return '#6B7280'; // Default gray
        }
    }

    private function getAdminBorderColorSafe($tournament): string
    {
        try {
            return $this->getAdminBorderColor($tournament);
        } catch (Exception $e) {
            return '#EF4444'; // Default red for errors
        }
    }

    private function getManagementPrioritySafe($tournament): string
    {
        try {
            return $this->getManagementPriority($tournament);
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Get empty calendar data for error states
     */
    private function getEmptyCalendarData($reason = 'empty'): array
    {
        return [
            'tournaments' => collect(),
            'userType' => 'admin',
            'userRoles' => ['admin'],
            'canModify' => false,
            'zones' => collect(),
            'types' => collect(),
            'clubs' => collect(),
            'availabilities' => [],
            'assignments' => [],
            'totalTournaments' => 0,
            'lastUpdated' => now()->toISOString(),
            'error_state' => $reason,
        ];
    }


    /**
     * Get event color based on tournament category
     * TODO: Replace with your actual category color logic
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
     * Admin border color - based on tournament status
     */
    private function getAdminBorderColor($tournament): string
    {
        return match($tournament->status) {
            'published' => '#10B981',   // Green
            'draft' => '#F59E0B',       // Amber
            'closed' => '#6B7280',      // Gray
            'cancelled' => '#EF4444',   // Red
            default => '#6B7280'        // Gray default
        };
    }

    /**
     * Calculate management priority for admin focus
     */
    private function getManagementPriority($tournament): string
    {
        $availabilities = $tournament->availabilities()->count();
        $assignments = $tournament->assignments()->count();
        $required = $tournament->required_referees ?? $tournament->tournamentCategory->min_referees ?? 1;
        $daysUntilDeadline = $tournament->days_until_deadline ?? 999;

        // Urgent: Missing referees or overdue deadline
        if ($daysUntilDeadline < 0 || $assignments < $required) {
            return 'urgent';
        }

        // Complete: Fully staffed
        if ($assignments >= $required) {
            return 'complete';
        }

        // In progress: Has some availability/assignments but not complete
        if ($availabilities > 0 || $assignments > 0) {
            return 'in_progress';
        }

        // Open: Ready for availability submissions
        return 'open';
    }
}
