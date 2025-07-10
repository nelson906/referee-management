<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Zone;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\View\View;


class AvailabilityController extends Controller
{
    /**
     * Display the availability summary page - SENZA AVAILABILITY_DEADLINE
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Get filter parameters - SOLO PER ARBITRI NAZIONALI
        $zoneId = $isNationalReferee ? $request->get('zone_id') : null;
        $categoryId = $isNationalReferee ? $request->get('category_id') : null;
        $month = $isNationalReferee ? $request->get('month') : null;

        // Base query - SENZA AVAILABILITY_DEADLINE
        $query = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) {
                    // Tornei futuri (status aperto o altro)
                    $q2->where('start_date', '>=', Carbon::today());
                })->orWhere(function ($q2) use ($user) {
                    // Oppure tornei dove l'arbitro ha già dato disponibilità
                    $q2->whereHas('availabilities', function ($q3) use ($user) {
                        $q3->where('user_id', $user->id);
                    });
                });
            });

        // Apply zone filter logic
        if ($isNationalReferee) {
            // Arbitri nazionali: zona propria + gare nazionali
            $query->where(function ($q) use ($user, $zoneId) {
                if ($zoneId) {
                    // Se filtro zona specificato, mostra solo quella zona
                    $q->where('zone_id', $zoneId);
                } else {
                    // Altrimenti: zona propria + gare nazionali ovunque
                    $q->where('zone_id', $user->zone_id)
                        ->orWhereHas('tournamentCategory', function ($q2) {
                            $q2->where('is_national', true);
                        });
                }
            });
        } else {
            // Arbitri aspiranti/primo_livello/regionali: SOLO zona propria
            $query->where('zone_id', $user->zone_id);
        }

        // Apply category filter SOLO SE SPECIFICATO E ARBITRO NAZIONALE
        if ($categoryId && $isNationalReferee) {
            $query->where('tournament_category_id', $categoryId);
        }

        // Apply month filter SOLO SE SPECIFICATO E ARBITRO NAZIONALE
        if ($month && $isNationalReferee) {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });
        }

        // Order by start date
        $tournaments = $query->orderBy('start_date')->get();

        // Get user's current availabilities
        $userAvailabilities = $user->availabilities()
            ->pluck('tournament_id')
            ->toArray();

        // Get zones for filter (only for national referees)
        $zones = $isNationalReferee ? Zone::orderBy('name')->get() : collect();

        // Get categories visible to user (only for national referees)
        $categories = $isNationalReferee ? \App\Models\TournamentCategory::active()
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('is_national', false)
                        ->whereJsonContains('settings->visibility_zones', $user->zone_id);
                })->orWhere('is_national', true);
            })
            ->ordered()
            ->get() : collect();

        // Group tournaments by month for display
        $tournamentsByMonth = $tournaments->groupBy(function ($tournament) {
            return $tournament->start_date->format('Y-m');
        });

        return view('referee.availability.index', compact(
            'tournamentsByMonth',
            'userAvailabilities',
            'zones',
            'categories',
            'zoneId',
            'categoryId',
            'month',
            'isNationalReferee'
        ));
    }

    /**
     * Save referee availabilities - SENZA AVAILABILITY_DEADLINE
     */
    public function save(Request $request)
    {
        $request->validate([
            'availabilities' => 'array',
            'availabilities.*' => 'exists:tournaments,id',
            'notes' => 'array',
            'notes.*' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);
        $selectedTournaments = $request->input('availabilities', []);
        $notes = $request->input('notes', []);

        // Get tournaments user can access - SOLO TORNEI FUTURI
        $accessibleQuery = Tournament::where('start_date', '>=', Carbon::today());

        if ($isNationalReferee) {
            // Arbitri nazionali: zona propria + gare nazionali
            $accessibleQuery->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereHas('tournamentCategory', function ($q2) {
                        $q2->where('is_national', true);
                    });
            });
        } else {
            // Arbitri aspiranti/primo_livello/regionali: solo zona propria
            $accessibleQuery->where('zone_id', $user->zone_id);
        }

        $accessibleTournaments = $accessibleQuery->pluck('id')->toArray();

        // Filter only accessible tournaments
        $selectedTournaments = array_intersect($selectedTournaments, $accessibleTournaments);

        // Start transaction
        \DB::beginTransaction();

        try {
            // Remove old availabilities for accessible tournaments
            Availability::where('user_id', $user->id)
                ->whereIn('tournament_id', $accessibleTournaments)
                ->delete();

            // Add new availabilities
            foreach ($selectedTournaments as $tournamentId) {
                Availability::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournamentId,
                    'notes' => $notes[$tournamentId] ?? null,
                    'submitted_at' => Carbon::now(),
                ]);
            }

            \DB::commit();

            return redirect()->route('referee.availability.index')
                ->with('success', 'Disponibilità aggiornate con successo!');
        } catch (\Exception $e) {
            \DB::rollback();

            return redirect()->route('referee.availability.index')
                ->with('error', 'Errore durante il salvataggio delle disponibilità. Riprova.');
        }
    }

    /**
     * Toggle single availability via AJAX - ORIGINALE
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'available' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $tournament = Tournament::findOrFail($request->tournament_id);
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Check if user can access this tournament
        $canAccess = false;
        if ($isNationalReferee) {
            // Arbitri nazionali: zona propria + gare nazionali
            $canAccess = $tournament->zone_id == $user->zone_id ||
                $tournament->tournamentCategory->is_national;
        } else {
            // Arbitri non nazionali: solo zona propria
            $canAccess = $tournament->zone_id == $user->zone_id;
        }

        if (!$canAccess) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Check if tournament allows availability changes - SENZA DEADLINE
        if ($tournament->start_date <= Carbon::today()) {
            return response()->json(['error' => 'Torneo già iniziato'], 400);
        }

        if ($request->available) {
            // Add availability
            Availability::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                ],
                [
                    'notes' => $request->notes,
                    'submitted_at' => Carbon::now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Disponibilità confermata',
            ]);
        } else {
            // Remove availability
            Availability::where('user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Disponibilità rimossa',
            ]);
        }
    }

    /**
     * Referee Calendar - Personal focus - SOLO GARE DI COMPETENZA
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level ?? '', ['nazionale', 'internazionale']);

        // Get tournaments for calendar - LOGICA AGGIORNATA
        $tournamentsQuery = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->whereIn('status', ['draft', 'open', 'closed', 'assigned']);

        if ($isNationalReferee) {
            // Arbitri nazionali: zona propria + gare nazionali ovunque
            $tournamentsQuery->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereHas('tournamentCategory', function ($q2) {
                        $q2->where('is_national', true);
                    });
            });
        } else {
            // Arbitri aspiranti/primo_livello/regionali: solo zona propria (incluse gare nazionali nella loro zona)
            $tournamentsQuery->where('zone_id', $user->zone_id);
        }

        $tournaments = $tournamentsQuery->get();

        // Get user's availabilities and assignments
        $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
        $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();

        // Format tournaments for calendar - CON CODICE CIRCOLO NEL TITOLO
        $calendarTournaments = $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments) {
            $isAvailable = in_array($tournament->id, $userAvailabilities);
            $isAssigned = in_array($tournament->id, $userAssignments);

            return [
                'id' => $tournament->id,
                'title' => $tournament->name . ($tournament->club->code ? ' (' . $tournament->club->code . ')' : ''),
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $isAssigned ? '#10B981' : ($isAvailable ? '#3B82F6' : '#E5E7EB'),
                'borderColor' => $isAssigned ? '#10B981' : ($isAvailable ? '#F59E0B' : '#9CA3AF'),
                'textColor' => $isAssigned ? '#FFFFFF' : ($isAvailable ? '#FFFFFF' : '#374151'), // Testo nero per eventi non selezionati
                'extendedProps' => [
                    'club' => $tournament->club->name ?? 'N/A',
                    'club_code' => $tournament->club->code ?? '',
                    'zone' => $tournament->zone->name ?? 'N/A',
                    'category' => $tournament->tournamentCategory->name ?? 'N/A',
                    'status' => $tournament->status,
                    'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                    'days_until_deadline' => $tournament->availability_deadline
                        ? Carbon::today()->diffInDays($tournament->availability_deadline, false)
                        : null,
                    'is_available' => $isAvailable,
                    'is_assigned' => $isAssigned,
                    'can_apply' => $tournament->start_date > Carbon::today() &&
                        ($tournament->availability_deadline ? $tournament->availability_deadline >= Carbon::today() : true),
                    'personal_status' => $isAssigned ? 'assigned' : ($isAvailable ? 'available' : 'can_apply'),
                    'tournament_url' => route('tournaments.show', $tournament),
                    'is_national' => $tournament->tournamentCategory->is_national ?? false,
                ],
            ];
        });

        // STRUTTURA DATI per RefereeCalendar.jsx
        $calendarData = [
            'tournaments' => $calendarTournaments,
            'userType' => 'referee',
            'userRoles' => ['referee'],
            'canModify' => true,
            'zones' => collect(),
            'types' => collect(),
            'clubs' => collect(),
            'availabilities' => $userAvailabilities,
            'assignments' => $userAssignments,
            'totalTournaments' => $tournaments->count(),
            'lastUpdated' => now()->toISOString(),
            'userLevel' => $user->level,
            'isNationalReferee' => $isNationalReferee,
        ];

        return view('referee.availability.calendar', compact('calendarData'));
    }


    /**
     * NUOVO METODO: Check if user can apply to tournament
     */
    private function userCanApplyToTournament($user, $tournament): bool
    {
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // National referees: own zone + national tournaments
        if ($isNationalReferee) {
            return $tournament->zone_id == $user->zone_id
                || $tournament->tournamentCategory->is_national;
        }

        // Zone referees: only own zone (including national tournaments in own zone)
        return $tournament->zone_id == $user->zone_id;
    }

    /**
     * Get event color based on tournament category (same as admin) - PRESERVATO
     */
    private function getEventColor($tournament, $userAvailabilities, $userAssignments): string
    {
        if (in_array($tournament->id, $userAssignments)) {
            return '#10B981'; // Green - Assigned
        }

        if (in_array($tournament->id, $userAvailabilities)) {
            return '#3B82F6'; // Blue - Available
        }

        if ($tournament->isOpenForAvailability()) {
            return '#F59E0B'; // Amber - Open
        }

        return '#6B7280'; // Gray - Closed/Other
    }

    /**
     * Referee border color - based on personal status - PRESERVATO
     */
    private function getRefereeBorderColor($isAvailable, $isAssigned): string
    {
        if ($isAssigned) {
            return '#10B981'; // Green - Assigned
        }

        if ($isAvailable) {
            return '#F59E0B'; // Amber - Available but not assigned
        }

        return '#6B7280'; // Gray - Not available
    }

    /**
     * Get personal status for referee
     */
    private function getPersonalStatus($isAvailable, $isAssigned, $tournament): string
    {
        if ($isAssigned) {
            return 'assigned';
        }

        if ($isAvailable) {
            return 'available';
        }

        // Can apply if tournament is open for availability
        return 'can_apply';
    }
}
