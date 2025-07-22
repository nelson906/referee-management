<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TournamentType;
use Database\Seeders\Helpers\SeederHelper;

class TournamentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏆 Creando Tipologie Tornei Golf...');

        // Elimina tipologie esistenti per evitare duplicati
        TournamentType::truncate();

        // Crea categorie zonali
        $this->createZonalCategories();

        // Crea categorie nazionali
        $this->createNationalCategories();

        // Valida e mostra riassunto
        $this->validateTournamentTypes();
        $this->showTournamentTypeSummary();
    }

    /**
     * Crea categorie tornei zonali
     */
    private function createZonalCategories(): void
    {
        $zonalTypes = [
            [
                'name' => 'Gara Sociale',
                'code' => 'SOCIALE',
                'description' => 'Gare sociali del circolo - livello base',
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 2,
                'requires_approval' => false,
                'priority_level' => 1,
                'active' => true
            ],
            [
                'name' => 'Trofeo di Zona',
                'code' => 'TROFEO_ZONA',
                'description' => 'Trofei a carattere zonale - media importanza',
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 3,
                'requires_approval' => true,
                'priority_level' => 2,
                'active' => true
            ],
            [
                'name' => 'Campionato Zonale',
                'code' => 'CAMP_ZONALE',
                'description' => 'Campionati zonali ufficiali - alta importanza regionale',
                'is_national' => false,
                'min_referees' => 2,
                'max_referees' => 4,
                'requires_approval' => true,
                'priority_level' => 3,
                'active' => true
            ]
        ];

        foreach ($zonalTypes as $typeData) {
            $tournamentType = TournamentType::create($typeData);
            $this->command->info("✅ Categoria zonale creata: {$tournamentType->name} ({$tournamentType->code})");
        }
    }

    /**
     * Crea categorie tornei nazionali
     */
    private function createNationalCategories(): void
    {
        $nationalTypes = [
            [
                'name' => 'Open Nazionale',
                'code' => 'OPEN_NAZ',
                'description' => 'Open nazionali aperti - livello inter-zonale',
                'is_national' => true,
                'min_referees' => 2,
                'max_referees' => 4,
                'requires_approval' => true,
                'priority_level' => 4,
                'active' => true
            ],
            [
                'name' => 'Campionato Italiano',
                'code' => 'CAMP_ITA',
                'description' => 'Campionati Italiani ufficiali - massimo livello nazionale',
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 5,
                'requires_approval' => true,
                'priority_level' => 5,
                'active' => true
            ],
            [
                'name' => 'Major Italiano',
                'code' => 'MAJOR_ITA',
                'description' => 'Major italiani e tornei di élite - prestigio internazionale',
                'is_national' => true,
                'min_referees' => 4,
                'max_referees' => 6,
                'requires_approval' => true,
                'priority_level' => 6,
                'active' => true
            ]
        ];

        foreach ($nationalTypes as $typeData) {
            $tournamentType = TournamentType::create($typeData);
            $this->command->info("✅ Categoria nazionale creata: {$tournamentType->name} ({$tournamentType->code})");
        }
    }

    /**
     * Valida tipologie tornei create
     */
    private function validateTournamentTypes(): void
    {
        $this->command->info('🔍 Validando tipologie tornei...');

        // Verifica numero totale
        $totalTypes = TournamentType::count();
        if ($totalTypes !== 6) {
            $this->command->error("❌ Errore: dovrebbero esserci 6 tipologie, trovate {$totalTypes}");
            return;
        }

        // Verifica categorie zonali
        $zonalTypes = TournamentType::where('is_national', false)->count();
        if ($zonalTypes !== 3) {
            $this->command->error("❌ Errore: dovrebbero esserci 3 categorie zonali, trovate {$zonalTypes}");
            return;
        }

        // Verifica categorie nazionali
        $nationalTypes = TournamentType::where('is_national', true)->count();
        if ($nationalTypes !== 3) {
            $this->command->error("❌ Errore: dovrebbero esserci 3 categorie nazionali, trovate {$nationalTypes}");
            return;
        }

        // Verifica codici univoci
        $totalCodes = TournamentType::count();
        $uniqueCodes = TournamentType::distinct('code')->count();
        if ($totalCodes !== $uniqueCodes) {
            $this->command->error("❌ Errore: codici tipologia non univoci");
            return;
        }

        // Verifica vincoli min/max arbitri
        $invalidRefereeRanges = TournamentType::where('min_referees', '>', 'max_referees')->count();
        if ($invalidRefereeRanges > 0) {
            $this->command->error("❌ Errore: {$invalidRefereeRanges} tipologie con range arbitri invalido");
            return;
        }

        // Verifica che tutte le tipologie siano attive
        $inactiveTypes = TournamentType::where('active', false)->count();
        if ($inactiveTypes > 0) {
            $this->command->warn("⚠️ Attenzione: {$inactiveTypes} tipologie non attive");
        }

        // Verifica priorità uniche
        $priorities = TournamentType::pluck('priority_level')->toArray();
        if (count($priorities) !== count(array_unique($priorities))) {
            $this->command->warn("⚠️ Attenzione: priorità duplicate tra tipologie");
        }

        $this->command->info('✅ Validazione tipologie tornei completata con successo');
    }

    /**
     * Mostra riassunto tipologie create
     */
    private function showTournamentTypeSummary(): void
    {
        $this->command->info('');
        $this->command->info('🏆 RIASSUNTO TIPOLOGIE TORNEI:');
        $this->command->info('=====================================');

        // Categorie zonali
        $this->command->info('📍 CATEGORIE ZONALI:');
        $zonalTypes = TournamentType::where('is_national', false)
                                   ->orderBy('priority_level')
                                   ->get();

        foreach ($zonalTypes as $type) {
            $approval = $type->requires_approval ? '🔒 Approvazione' : '🔓 Libera';
            $status = $type->active ? '🟢' : '🔴';

            $this->command->info(sprintf(
                "  %s %-20s | Arbitri: %d-%d | Priorità: %d | %s",
                $status,
                $type->name,
                $type->min_referees,
                $type->max_referees,
                $type->priority_level,
                $approval
            ));
        }

        $this->command->info('');
        $this->command->info('🌍 CATEGORIE NAZIONALI:');
        $nationalTypes = TournamentType::where('is_national', true)
                                      ->orderBy('priority_level')
                                      ->get();

        foreach ($nationalTypes as $type) {
            $approval = $type->requires_approval ? '🔒 Approvazione' : '🔓 Libera';
            $status = $type->active ? '🟢' : '🔴';

            $this->command->info(sprintf(
                "  %s %-20s | Arbitri: %d-%d | Priorità: %d | %s",
                $status,
                $type->name,
                $type->min_referees,
                $type->max_referees,
                $type->priority_level,
                $approval
            ));
        }

        $this->command->info('');
        $this->command->info('📊 STATISTICHE:');
        $this->command->info("   Categorie Zonali: {$zonalTypes->count()}");
        $this->command->info("   Categorie Nazionali: {$nationalTypes->count()}");
        $this->command->info("   Totale Tipologie: " . TournamentType::count());

        $this->command->info('');
        $this->command->info('🎯 LIVELLI PRIORITÀ:');
        $this->command->info('   1-2: Gare locali e trofei zona');
        $this->command->info('   3-4: Campionati zonali e open nazionali');
        $this->command->info('   5-6: Campionati italiani e major');

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
