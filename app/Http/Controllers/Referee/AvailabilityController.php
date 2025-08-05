<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Zone;
use App\Models\TournamentType;
use App\Helpers\RefereeLevelsHelper;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Mail\BatchAvailabilityNotification;
use App\Mail\BatchAvailabilityAdminNotification;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class AvailabilityController extends Controller
{
    /**
     * Display the availability summary page
     * SEMPLIFICATO: Solo logica essenziale
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        // Get filter parameters
        $zoneId = $request->get('zone_id');
        $typeId = $request->get('type_id');
        $month = $request->get('month');

        // ✅ SEMPLIFICATO: Base query - TUTTE le gare dell'anno
        $query = Tournament::with(['tournamentType', 'zone', 'club'])
            ->whereYear('start_date', Carbon::now()->year); // Solo gare dell'anno corrente
        // ✅ CORRETTO: Logica zone precisa
if ($isNationalReferee) {
    if ($zoneId) {
        $query->where('zone_id', $zoneId);
    } else {
        $query->where(function ($q) use ($user) {
            $q->where('zone_id', $user->zone_id) // Propria zona
              ->orWhereHas('tournamentType', function ($q2) {
                  $q2->where('is_national', true); // Tutte le gare nazionali
              });
        });
    }
} else {
    $query->where('zone_id', $user->zone_id);
}

        // Apply filters
        if ($typeId) {
            $query->where('tournament_type_id', $typeId);
        }

        if ($month) {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $query->whereBetween('start_date', [$startOfMonth, $endOfMonth]);
        }

        // Order by start date
        $query->orderBy('start_date');

        // ✅ CORRETTO: Paginazione semplice che punta ai tornei prossimi
        $currentPage = $request->get('page', null);

        // Se non è specificata una pagina, trova quella con tornei prossimi a oggi
if (!$currentPage) {
    $tournamentsBeforeToday = (clone $query)->where('start_date', '<', Carbon::today())->count();
    if ($tournamentsBeforeToday > 0) {
        $targetPage = ceil($tournamentsBeforeToday / 10);
        if ($targetPage > 1) {
            return redirect()->route('referee.availability.index', array_merge(
                $request->only(['zone_id', 'type_id', 'month']),
                ['page' => $targetPage]
            ));
        }
    }
}

        // Paginazione
        $tournaments = $query->paginate(10, ['*'], 'page', $currentPage)->appends($request->query());

        // Get user's availabilities
        $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();

        // ✅ SEMPLIFICATO: Aggiungi solo info essenziali
        $tournaments->getCollection()->transform(function ($tournament) use ($userAvailabilities) {
            $tournament->user_has_availability = in_array($tournament->id, $userAvailabilities);
            $tournament->days_until_deadline = $this->getDaysUntilDeadline($tournament);
            return $tournament;
        });

        // Get filter options
        $zones = $this->getAccessibleZones($user, $isNationalReferee);
        $types = TournamentType::select('id', 'name', 'short_name')->orderBy('name')->get();

        return view('referee.availability.index', compact(
            'tournaments',
            'userAvailabilities',
            'zones',
            'types',
            'zoneId',
            'typeId',
            'month'
        ));
    }

    /**
     * Get days until deadline (can be negative)
     */
    private function getDaysUntilDeadline($tournament): ?int
    {
        if (!$tournament->availability_deadline) {
            return null;
        }

        return Carbon::today()->diffInDays(Carbon::parse($tournament->availability_deadline), false);
    }

    /**
     * Get accessible zones for filters
     */
    private function getAccessibleZones($user, $isNationalReferee)
    {
        if ($isNationalReferee) {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Save referee availabilities - SEMPLIFICATO
     */
public function save(Request $request)
{
    $request->validate([
        'availabilities' => 'array',
        'availabilities.*' => 'exists:tournaments,id',
    ]);

    $user = auth()->user();
    $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);
    $selectedTournaments = $request->input('availabilities', []);

    // Get accessible tournaments
    $accessibleQuery = Tournament::whereYear('start_date', Carbon::now()->year);

    if ($isNationalReferee) {
        $accessibleQuery->where(function ($q) use ($user) {
            $q->where('zone_id', $user->zone_id)
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('zone_id', '!=', $user->zone_id)
                     ->whereHas('tournamentType', function ($q3) {
                         $q3->where('is_national', true);
                     });
              });
        });
    } else {
        $accessibleQuery->where('zone_id', $user->zone_id);
    }

    $accessibleTournaments = $accessibleQuery->pluck('id')->toArray();
    $selectedTournaments = array_intersect($selectedTournaments, $accessibleTournaments);

    // SALVA LE VECCHIE PRIMA DI CANCELLARE
    $oldAvailabilities = Availability::where('user_id', $user->id)
        ->whereIn('tournament_id', $accessibleTournaments)
        ->pluck('tournament_id')
        ->toArray();

    DB::beginTransaction();

    try {
        // Remove old availabilities
        Availability::where('user_id', $user->id)
            ->whereIn('tournament_id', $accessibleTournaments)
            ->delete();

        // Add new availabilities
        foreach ($selectedTournaments as $tournamentId) {
            Availability::create([
                'user_id' => $user->id,
                'tournament_id' => $tournamentId,
                'submitted_at' => Carbon::now(),
            ]);
        }

        DB::commit();

// SOLO QUESTA PARTE È NUOVA - INVIA NOTIFICHE
$added = array_diff($selectedTournaments, $oldAvailabilities);
$removed = array_diff($oldAvailabilities, $selectedTournaments);

\Log::info('DEBUG Notifiche disponibilità', [
    'user_email' => $user->email,
    'added' => $added,
    'removed' => $removed,
    'has_changes' => count($added) > 0 || count($removed) > 0
]);

if (count($added) > 0 || count($removed) > 0) {
    try {
        $addedTournaments = Tournament::whereIn('id', $added)->get();
        $removedTournaments = Tournament::whereIn('id', $removed)->get();

        \Log::info('Tornei recuperati', [
            'added_count' => $addedTournaments->count(),
            'removed_count' => $removedTournaments->count()
        ]);

        // Notifica al referee
        \Log::info('Tentativo invio email referee a: ' . $user->email);
        Mail::to($user->email)->send(new BatchAvailabilityNotification(
            $user,
            $addedTournaments,
            $removedTournaments
        ));
        \Log::info('Email referee inviata OK');

        // Notifica admin
        $adminEmails = ["szr{$user->zone_id}@federgolf.it"];
        \Log::info('Tentativo invio email admin a:', $adminEmails);
        Mail::to($adminEmails)->send(new BatchAvailabilityAdminNotification(
            $user,
            $addedTournaments,
            $removedTournaments
        ));
        \Log::info('Email admin inviata OK');

    } catch (\Exception $e) {
        \Log::error('ERRORE INVIO EMAIL: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
    }
}
        return redirect()->route('referee.availability.index')
            ->with('success', 'Disponibilità aggiornate con successo!');

    } catch (\Exception $e) {
        DB::rollback();

        return redirect()->back()
            ->withErrors(['error' => 'Errore durante il salvataggio. Riprova.']);
    }
}
    /**
     * Referee Calendar - Focus: Disponibilità personali
     * - Sono disponibile?
     * - Sono assegnato?
     * - Posso candidarmi?
     */

/**
 * Show calendar view for referee
 */
public function calendar(Request $request): View
{
    $user = auth()->user();

    try {
        // Get tournaments relevant to referee
        $tournaments = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->where('zone_id', $user->zone_id)
            ->get();

        // Get referee's availabilities and assignments
        $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
        $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();

        // Format for referee calendar
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments) {
                $isAvailable = in_array($tournament->id, $userAvailabilities);
                $isAssigned = in_array($tournament->id, $userAssignments);

                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name ?? 'Torneo #' . $tournament->id,
                    'start' => $tournament->start_date ? $tournament->start_date->format('Y-m-d') : now()->format('Y-m-d'),
                    'end' => $tournament->end_date ? $tournament->end_date->addDay()->format('Y-m-d') : now()->addDay()->format('Y-m-d'),
                    'color' => $this->getRefereeEventColor($tournament),
                    'borderColor' => $this->getRefereeBorderColor($isAvailable, $isAssigned),
                    'extendedProps' => [
                        'club' => $tournament->club->name ?? 'N/A',
                        'category' => $tournament->tournamentCategory->name ?? 'N/A',
                        'status' => $tournament->status ?? 'unknown',
                        'is_available' => $isAvailable,
                        'is_assigned' => $isAssigned,
                        'personal_status' => $this->getPersonalStatus($isAvailable, $isAssigned, $tournament),
                    ],
                ];
            }),
            'userType' => 'referee',
        ];

        return view('referee.availability.calendar', compact('calendarData'));

    } catch (\Exception $e) {
        $calendarData = [
            'tournaments' => collect(),
            'userType' => 'referee',
            'error' => $e->getMessage()
        ];

        return view('referee.availability.calendar', compact('calendarData'));
    }
}

private function getRefereeEventColor($tournament): string
{
    $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7'];
    return $colors[($tournament->id ?? 1) % count($colors)];
}

private function getRefereeBorderColor($isAvailable, $isAssigned): string
{
    if ($isAssigned) return '#10B981';
    if ($isAvailable) return '#F59E0B';
    return '#6B7280';
}

private function getPersonalStatus($isAvailable, $isAssigned, $tournament): string
{
    if ($isAssigned) return 'assigned';
    if ($isAvailable) return 'available';
    return 'can_apply';
}


}
