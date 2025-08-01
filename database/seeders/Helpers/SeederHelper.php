<?php

namespace Database\Seeders\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;

class SeederHelper
{
    /**
     * Configurazione seeder
     */
    public static function getConfig(): array
    {
        return [
            'zones_count' => 7,
            'referees_per_zone' => 13,
            'clubs_per_zone' => 4,
            'tournaments_per_zone' => [
                'completed' => 5,
                'assigned' => 3,
                'open' => 2,
                'closed' => 1,
                'draft' => 3,
                'scheduled' => 2
            ],
            'availability_rate' => 0.7, // 70% arbitri dichiarano
            'assignment_rate' => 0.9,   // 90% assegnazioni confermate
        ];
    }

    /**
     * Zone italiane realistiche
     */
    public static function getZones(): array
    {
        return [
            [
                'name' => 'SZR1',
                'code' => 'SZR1',
                'description' => 'Piemonte e Valle d\'Aosta',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR2',
                'code' => 'SZR2',
                'description' => 'Lombardia',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR3',
                'code' => 'SZR3',
                'description' => 'Veneto e Trentino Alto Adige',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR4',
                'code' => 'SZR4',
                'description' => 'Emilia Romagna e Marche',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR5',
                'code' => 'SZR5',
                'description' => 'Toscana e Umbria e Sardegna',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR6 ',
                'code' => 'SZR6',
                'description' => 'Lazio, Abruzzo, Molise e Sardegna',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'SZR7',
                'code' => 'SZR7',
                'description' => 'Meridione e Sicilia',
                'is_active' => true,
                'is_national' => false
            ],
            [
                'name' => 'CRC',
                'code' => 'CRC',
                'description' => 'Comitato Regole e Campionati',
                'is_active' => true,
                'is_national' => true
            ]
        ];
    }

    /**
     * Genera nomi realistici per arbitri
     */
    public static function generateRefereeNames(): array
    {
        $firstNames = [
            'Mario', 'Giuseppe', 'Francesco', 'Antonio', 'Alessandro', 'Andrea', 'Marco', 'Matteo',
            'Roberto', 'Stefano', 'Paolo', 'Carlo', 'Federico', 'Luca', 'Davide', 'Giovanni',
            'Giulia', 'Sara', 'Francesca', 'Anna', 'Chiara', 'Elena', 'Laura', 'Martina',
            'Silvia', 'Valentina', 'Alessandra', 'Federica', 'Paola', 'Cristina'
        ];

        $lastNames = [
            'Rossi', 'Ferrari', 'Russo', 'Bianchi', 'Romano', 'Colombo', 'Ricci', 'Marino',
            'Greco', 'Bruno', 'Gallo', 'Conti', 'De Luca', 'Mancini', 'Costa', 'Giordano',
            'Rizzo', 'Lombardi', 'Moretti', 'Barbieri', 'Fontana', 'Santoro', 'Mariani',
            'Rinaldi', 'Caruso', 'Ferrara', 'Galli', 'Martini', 'Leone', 'Longo'
        ];

        return compact('firstNames', 'lastNames');
    }

    /**
     * Genera nomi club per zona
     */
    public static function generateClubNames(int $zoneId): array
    {
        $clubsByZone = [
            1 => [ // Piemonte-Valle d'Aosta
                ['name' => 'Golf Club Torino', 'city' => 'Torino', 'province' => 'TO'],
                ['name' => 'Royal Park I Roveri', 'city' => 'Fiano', 'province' => 'TO'],
                ['name' => 'Golf Club Biella', 'city' => 'Biella', 'province' => 'BI'],
                ['name' => 'Golf Cervinia', 'city' => 'Cervinia', 'province' => 'AO']
            ],
            2 => [ // Lombardia
                ['name' => 'Golf Club Milano', 'city' => 'Milano', 'province' => 'MI'],
                ['name' => 'Circolo Villa San Martino', 'city' => 'Monza', 'province' => 'MB'],
                ['name' => 'Golf Club Bergamo', 'city' => 'Bergamo', 'province' => 'BG'],
                ['name' => 'Franciacorta Golf Club', 'city' => 'Corte Franca', 'province' => 'BS']
            ],
            3 => [ // Veneto-Trentino
                ['name' => 'Golf Club Venezia', 'city' => 'Venezia', 'province' => 'VE'],
                ['name' => 'Golf Club Verona', 'city' => 'Verona', 'province' => 'VR'],
                ['name' => 'Golf Club Padova', 'city' => 'Padova', 'province' => 'PD'],
                ['name' => 'Golf Club Dolomiti', 'city' => 'Trento', 'province' => 'TN']
            ],
            4 => [ // Emilia Romagna-Marche
                ['name' => 'Golf Club Bologna', 'city' => 'Bologna', 'province' => 'BO'],
                ['name' => 'Modena Golf & Country Club', 'city' => 'Modena', 'province' => 'MO'],
                ['name' => 'Rimini Golf Club', 'city' => 'Rimini', 'province' => 'RN'],
                ['name' => 'Golf Club Conero', 'city' => 'Ancona', 'province' => 'AN']
            ],
            5 => [ // Toscana-Umbria
                ['name' => 'Golf Club Firenze', 'city' => 'Firenze', 'province' => 'FI'],
                ['name' => 'Circolo Pisa Golf', 'city' => 'Pisa', 'province' => 'PI'],
                ['name' => 'Argentario Golf Club', 'city' => 'Porto Ercole', 'province' => 'GR'],
                ['name' => 'Golf Club Perugia', 'city' => 'Perugia', 'province' => 'PG']
            ],
            6 => [ // Lazio-Abruzzo-Molise
                ['name' => 'Golf Club Roma', 'city' => 'Roma', 'province' => 'RM'],
                ['name' => 'Circolo Olgiata', 'city' => 'Roma', 'province' => 'RM'],
                ['name' => 'Country Club Castelgandolfo', 'city' => 'Castelgandolfo', 'province' => 'RM'],
                ['name' => 'Golf Parco de\' Medici', 'city' => 'Roma', 'province' => 'RM']
            ],
            7 => [ // Sud Italia-Sicilia-Sardegna
                ['name' => 'Golf Club Napoli', 'city' => 'Napoli', 'province' => 'NA'],
                ['name' => 'Golf Club Bari', 'city' => 'Bari', 'province' => 'BA'],
                ['name' => 'Verdura Golf Club', 'city' => 'Sciacca', 'province' => 'AG'],
                ['name' => 'Golf Club Cagliari', 'city' => 'Cagliari', 'province' => 'CA']
            ]
        ];

        return $clubsByZone[$zoneId] ?? [];
    }

    /**
     * Genera nomi tornei per zona e tipo
     */
    public static function generateTournamentNames(string $zoneCode, string $type): array
    {
        $baseNames = [
            'Gara Sociale' => [
                'Trofeo Primavera', 'Coppa Estate', 'Memorial Autunno', 'Gara Sociale Dicembre',
                'Torneo Sociale Maggio', 'Coppa Presidente', 'Trofeo del Direttore'
            ],
            'Trofeo di Zona' => [
                'Trofeo Zonale Primavera', 'Coppa di Zona Estate', 'Campionato Zonale Autunno',
                'Memorial Zonale', 'Trofeo Regionale'
            ],
            'Campionato Zonale' => [
                'Campionato Zonale Assoluto', 'Campionato Regionale Senior', 'Campionato Ladies',
                'Campionato Zonale Juniores'
            ],
            'Open Nazionale' => [
                'Open d\'Italia', 'Open Nazionale Primavera', 'Open Nazionale Estate',
                'Italian Open Championship'
            ],
            'Campionato Italiano' => [
                'Campionato Italiano Assoluto', 'Campionato Italiano Senior', 'Campionato Italiano Ladies',
                'Campionato Italiano Juniores'
            ],
            'Major Italiano' => [
                'Italian Masters', 'Coppa Italia', 'Trofeo Nazionale Elite',
                'Championship of Italy'
            ]
        ];

        return $baseNames[$type] ?? ['Torneo ' . $type];
    }

    /**
     * Livelli arbitri con distribuzione realistica
     */
    public static function getRefereeLevels(): array
    {
        return [
            'aspirante' => [
                'name' => 'Aspirante',
                'count_per_zone' => 3,
                'description' => 'Arbitro in formazione'
            ],
            'primo_livello' => [
                'name' => 'Primo Livello',
                'count_per_zone' => 4,
                'description' => 'Arbitro base regionale'
            ],
            'regionale' => [
                'name' => 'Regionale',
                'count_per_zone' => 3,
                'description' => 'Arbitro regionale qualificato'
            ],
            'nazionale' => [
                'name' => 'Nazionale',
                'count_per_zone' => 2,
                'description' => 'Arbitro inter-zonale'
            ],
            'internazionale' => [
                'name' => 'Internazionale',
                'count_per_zone' => 1,
                'description' => 'Arbitro di livello internazionale'
            ]
        ];
    }

    /**
     * Genera date passate realistiche
     */
    public static function getPastDates(int $count): array
    {
        $dates = [];
        $baseDate = Carbon::now()->subMonths(6);

        for ($i = 0; $i < $count; $i++) {
            $startDate = $baseDate->copy()->addWeeks($i * 2)->addDays(rand(0, 6));
            $endDate = $startDate->copy()->addDays(rand(1, 3));

            $dates[] = [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'availability_deadline' => $startDate->copy()->subDays(7)->format('Y-m-d H:i:s')
            ];
        }

        return $dates;
    }

    /**
     * Genera date future realistiche
     */
    public static function getFutureDates(int $count): array
    {
        $dates = [];
        $baseDate = Carbon::now()->addMonths(1);

        for ($i = 0; $i < $count; $i++) {
            $startDate = $baseDate->copy()->addWeeks($i * 2)->addDays(rand(0, 6));
            $endDate = $startDate->copy()->addDays(rand(1, 3));

            $dates[] = [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'availability_deadline' => $startDate->copy()->subDays(14)->format('Y-m-d H:i:s')
            ];
        }

        return $dates;
    }

    /**
     * Genera deadline disponibilità
     */
    public static function getAvailabilityDeadline(string $tournamentDate): string
    {
        return Carbon::parse($tournamentDate)->subDays(14)->format('Y-m-d H:i:s');
    }

    /**
     * Genera codice arbitro univoco
     */
    public static function generateRefereeCode(string $zoneCode, int $sequence): string
    {
        return sprintf('%s-REF-%03d', $zoneCode, $sequence);
    }

    /**
     * Genera codice club
     */
    public static function generateClubCode(string $zoneCode, int $sequence): string
    {
        return sprintf('%s-CLB-%03d', $zoneCode, $sequence);
    }

    /**
     * Valida consistenza zona
     */
    public static function validateZoneConsistency(array $data): bool
    {
        // Implementa validazioni per assicurare coerenza dei dati
        return true;
    }

    /**
     * Genera password hash per testing
     */
    public static function getTestPassword(): string
    {
        return bcrypt('password123');
    }

    /**
     * Genera indirizzi email realistici
     */
    public static function generateEmail(string $firstName, string $lastName, string $zoneCode = '', string $role = 'referee'): string
    {
        $firstName = Str::lower(str_replace(' ', '', $firstName));
        $lastName = Str::lower(str_replace(' ', '', $lastName));

        if ($role === 'admin' && $zoneCode) {
            return "admin.{$zoneCode}@golf.it";
        }

        $suffix = $zoneCode ? ".{$zoneCode}@golf.it" : '@golf.it';
        return "{$firstName}.{$lastName}{$suffix}";
    }

    /**
     * Stati tornei con logica
     */
    public static function getTournamentStatuses(): array
    {
        return [
            'draft' => 'Bozza - in preparazione',
            'scheduled' => 'Programmato - date confermate',
            'open' => 'Aperto - raccolta disponibilità',
            'closed' => 'Chiuso - assegnazioni in corso',
            'assigned' => 'Assegnato - arbitri confermati',
            'completed' => 'Completato - torneo concluso',
            'cancelled' => 'Annullato'
        ];
    }

    /**
     * Ruoli assegnazione arbitri
     */
    public static function getAssignmentRoles(): array
    {
        return [
            'Arbitro',
            'Direttore Torneo',
            'Osservatore',
            'Assistente',
            'Supervisore'
        ];
    }
}
