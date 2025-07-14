<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateModelsControllersCommand extends Command
{
    protected $signature = 'consolidation:update-code {--dry-run : Solo mostra cosa verrebbe modificato}';
    protected $description = 'Aggiorna Model e Controller dopo mega consolidation migration';

    private $changes = [];
    private $isDryRun = false;

    public function handle()
    {
        $this->isDryRun = $this->option('dry-run');

        $this->info('🔧 AGGIORNAMENTO MODEL/CONTROLLER POST-MIGRATION');
        $this->info('=====================================================');

        if ($this->isDryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Solo preview delle modifiche');
        }

        // 1. Rinomina TournamentCategory → TournamentType
        $this->renameTournamentCategoryModel();

        // 2. Aggiorna relationships nei model
        $this->updateModelRelationships();

        // 3. Rinomina TournamentCategoryController → TournamentTypeController
        $this->renameTournamentCategoryController();

        // 4. Aggiorna imports e references nei controller
        $this->updateControllerReferences();

        // 5. Aggiorna User model per essere primary source
        $this->updateUserModel();

        // 6. Aggiorna Referee model per essere extension only
        $this->updateRefereeModel();

        // Summary
        $this->showSummary();

        if (!$this->isDryRun && $this->confirm('Procedere con le modifiche?')) {
            $this->applyChanges();
            $this->info('✅ Aggiornamenti applicati con successo!');

            $this->warn('🔄 PROSSIMI STEP MANUALI:');
            $this->warn('1. Aggiorna le routes in web.php');
            $this->warn('2. Rinomina directory views/super-admin/tournament-categories');
            $this->warn('3. Aggiorna route calls nelle views');
            $this->warn('4. Testa funzionalità dopo modifiche');
        }

        return Command::SUCCESS;
    }

    private function renameTournamentCategoryModel(): void
    {
        $this->info('1. 📋 Rename TournamentCategory → TournamentType Model');

        $oldPath = app_path('Models/TournamentCategory.php');
        $newPath = app_path('Models/TournamentType.php');

        if (!File::exists($oldPath)) {
            $this->warn("   ⚠️  File {$oldPath} non trovato");
            return;
        }

        $content = File::get($oldPath);

        // Update class name e references
        $newContent = str_replace([
            'class TournamentCategory',
            'TournamentCategory::',
            '@property.*tournaments_count',
        ], [
            'class TournamentType',
            'TournamentType::',
            '@property int $tournaments_count',
        ], $content);

        // Update comments
        $newContent = str_replace([
            'Tournament Categories',
            'tournament categories',
            'Tournament Category',
            'tournament category',
        ], [
            'Tournament Types',
            'tournament types',
            'Tournament Type',
            'tournament type',
        ], $newContent);

        $this->addChange('rename_model', [
            'from' => $oldPath,
            'to' => $newPath,
            'content' => $newContent
        ]);
    }

    private function updateModelRelationships(): void
    {
        $this->info('2. 🔗 Update Model Relationships');

        // Update Tournament model
        $this->updateTournamentModel();
    }

    private function updateTournamentModel(): void
    {
        $tournamentPath = app_path('Models/Tournament.php');

        if (!File::exists($tournamentPath)) {
            $this->warn("   ⚠️  Tournament model non trovato");
            return;
        }

        $content = File::get($tournamentPath);

        // Update relationship method
        $oldRelationship = 'public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class);
    }';

        $newRelationship = 'public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
    }

    /**
     * Alias per backward compatibility
     */
    public function tournamentCategory()
    {
        return $this->tournamentType();
    }';

        $newContent = str_replace($oldRelationship, $newRelationship, $content);

        // Update imports
        $newContent = str_replace(
            'use App\Models\TournamentCategory;',
            'use App\Models\TournamentType;',
            $newContent
        );

        // Update fillable array
        $newContent = str_replace(
            "'tournament_category_id'",
            "'tournament_type_id'",
            $newContent
        );

        // Update comments and docblocks
        $newContent = str_replace([
            'tournament category',
            'Tournament Category',
            'tournament_category_id',
        ], [
            'tournament type',
            'Tournament Type',
            'tournament_type_id',
        ], $newContent);

        $this->addChange('update_tournament_model', [
            'file' => $tournamentPath,
            'content' => $newContent
        ]);
    }

    private function renameTournamentCategoryController(): void
    {
        $this->info('3. 🎮 Rename TournamentCategoryController → TournamentTypeController');

        $oldPath = app_path('Http/Controllers/SuperAdmin/TournamentCategoryController.php');
        $newPath = app_path('Http/Controllers/SuperAdmin/TournamentTypeController.php');

        if (!File::exists($oldPath)) {
            $this->warn("   ⚠️  Controller {$oldPath} non trovato");
            return;
        }

        $content = File::get($oldPath);

        // Update class name
        $newContent = str_replace(
            'class TournamentCategoryController',
            'class TournamentTypeController',
            $content
        );

        // Update model imports
        $newContent = str_replace(
            'use App\Models\TournamentCategory;',
            'use App\Models\TournamentType;',
            $newContent
        );

        // Update model references
        $newContent = str_replace([
            'TournamentCategory::',
            '$tournamentCategory',
            'TournamentCategoryRequest',
        ], [
            'TournamentType::',
            '$tournamentType',
            'TournamentTypeRequest',
        ], $newContent);

        // Update route references
        $newContent = str_replace([
            'tournament-categories',
            'tournament_categories',
        ], [
            'tournament-types',
            'tournament_types',
        ], $newContent);

        // Update view references
        $newContent = str_replace([
            'super-admin.tournament-categories.',
            "'categories'",
            'tournament category',
            'Tournament Category',
        ], [
            'super-admin.tournament-types.',
            "'types'",
            'tournament type',
            'Tournament Type',
        ], $newContent);

        $this->addChange('rename_controller', [
            'from' => $oldPath,
            'to' => $newPath,
            'content' => $newContent
        ]);
    }

    private function updateControllerReferences(): void
    {
        $this->info('4. 📝 Update Controller References');

        $controllers = [
            'Admin/TournamentController.php',
            'Admin/AssignmentController.php',
            'Reports/AssignmentReportController.php',
        ];

        foreach ($controllers as $controllerFile) {
            $this->updateControllerFile($controllerFile);
        }
    }

    private function updateControllerFile(string $controllerFile): void
    {
        $path = app_path("Http/Controllers/{$controllerFile}");

        if (!File::exists($path)) {
            $this->warn("   ⚠️  Controller {$controllerFile} non trovato");
            return;
        }

        $content = File::get($path);
        $originalContent = $content;

        // Update imports
        $content = str_replace(
            'use App\Models\TournamentCategory;',
            'use App\Models\TournamentType;',
            $content
        );

        // Update model references
        $content = str_replace([
            'TournamentCategory::',
            '$categories = TournamentCategory',
            'tournamentCategory',
            'tournament_category_id',
        ], [
            'TournamentType::',
            '$types = TournamentType',
            'tournamentType',
            'tournament_type_id',
        ], $content);

        // Update variable names nel compact()
        $content = str_replace(
            "compact('tournaments', 'zones', 'categories'",
            "compact('tournaments', 'zones', 'types'",
            $content
        );

        if ($content !== $originalContent) {
            $this->addChange('update_controller', [
                'file' => $path,
                'content' => $content
            ]);
        }
    }

    private function updateUserModel(): void
    {
        $this->info('5. 👤 Update User Model (Primary Source)');

        $userPath = app_path('Models/User.php');

        if (!File::exists($userPath)) {
            $this->warn("   ⚠️  User model non trovato");
            return;
        }

        $content = File::get($userPath);

        // Ensure all referee fields are in fillable
        $fillablePattern = '/protected \$fillable = \[(.*?)\];/s';

        if (preg_match($fillablePattern, $content, $matches)) {
            $currentFillable = $matches[1];

            $requiredFields = [
                "'referee_code'", "'level'", "'category'", "'certified_date'",
                "'zone_id'", "'phone'", "'notes'", "'is_active'", "'user_type'"
            ];

            $newFillable = $currentFillable;
            foreach ($requiredFields as $field) {
                if (strpos($newFillable, $field) === false) {
                    $newFillable .= ",\n        {$field}";
                }
            }

            $newContent = str_replace(
                "protected \$fillable = [{$currentFillable}];",
                "protected \$fillable = [{$newFillable}];",
                $content
            );
        } else {
            $newContent = $content;
        }

        // Add helper methods se non esistono
        $helperMethods = '
    /**
     * Check if user has completed their profile (consolidated version)
     */
    public function hasCompletedProfile(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isReferee()) {
            return !empty($this->name) &&
                !empty($this->email) &&
                !empty($this->referee_code) &&
                !empty($this->level) &&
                !empty($this->zone_id);
        }

        return !empty($this->name) && !empty($this->email);
    }

    /**
     * Get referee level label (consolidated version)
     */
    public function getLevelLabelAttribute(): string
    {
        return self::REFEREE_LEVELS[$this->level] ?? ucfirst($this->level ?? "");
    }

    /**
     * Get full display name with referee code
     */
    public function getFullNameAttribute(): string
    {
        if ($this->referee_code && $this->isReferee()) {
            return "{$this->name} ({$this->referee_code})";
        }
        return $this->name;
    }';

        // Add methods if not exist
        if (strpos($newContent, 'hasCompletedProfile') === false) {
            $newContent = str_replace(
                'class User extends Authenticatable',
                'class User extends Authenticatable' . $helperMethods,
                $newContent
            );
        }

        $this->addChange('update_user_model', [
            'file' => $userPath,
            'content' => $newContent
        ]);
    }

    private function updateRefereeModel(): void
    {
        $this->info('6. 🏌️ Update Referee Model (Extension Only)');

        $refereePath = app_path('Models/Referee.php');

        if (!File::exists($refereePath)) {
            $this->warn("   ⚠️  Referee model non trovato");
            return;
        }

        $content = File::get($refereePath);

        // Remove duplicate fields from fillable
        $duplicateFields = [
            "'referee_code'", "'level'", "'category'", "'certified_date'",
            "'zone_id'", "'phone'", "'is_active'"
        ];

        $fillablePattern = '/protected \$fillable = \[(.*?)\];/s';

        if (preg_match($fillablePattern, $content, $matches)) {
            $currentFillable = $matches[1];
            $newFillable = $currentFillable;

            foreach ($duplicateFields as $field) {
                $newFillable = preg_replace('/,?\s*' . preg_quote($field, '/') . '\s*,?/', '', $newFillable);
            }

            // Clean up extra commas
            $newFillable = preg_replace('/,\s*,/', ',', $newFillable);
            $newFillable = trim($newFillable, " ,\n");

            $newContent = str_replace(
                "protected \$fillable = [{$currentFillable}];",
                "protected \$fillable = [\n        {$newFillable}\n    ];",
                $content
            );
        } else {
            $newContent = $content;
        }

        // Add comment about consolidated approach
        $comment = '/**
 * Referee Model - Extension Only
 *
 * NOTE: Core referee data (referee_code, level, category, certified_date, zone_id, phone, is_active)
 * is now stored in Users table. This model contains only additional referee-specific fields.
 */';

        $newContent = str_replace(
            'class Referee extends Model',
            $comment . "\nclass Referee extends Model",
            $newContent
        );

        $this->addChange('update_referee_model', [
            'file' => $refereePath,
            'content' => $newContent
        ]);
    }

    private function addChange(string $type, array $data): void
    {
        $this->changes[] = ['type' => $type, 'data' => $data];

        if ($this->isDryRun) {
            $this->showDryRunChange($type, $data);
        }
    }

    private function showDryRunChange(string $type, array $data): void
    {
        switch ($type) {
            case 'rename_model':
                $this->line("   🔄 RENAME: {$data['from']} → {$data['to']}");
                break;
            case 'rename_controller':
                $this->line("   🔄 RENAME: {$data['from']} → {$data['to']}");
                break;
            case 'update_tournament_model':
            case 'update_controller':
            case 'update_user_model':
            case 'update_referee_model':
                $this->line("   ✏️  UPDATE: {$data['file']}");
                break;
        }
    }

    private function applyChanges(): void
    {
        foreach ($this->changes as $change) {
            $type = $change['type'];
            $data = $change['data'];

            try {
                switch ($type) {
                    case 'rename_model':
                    case 'rename_controller':
                        File::delete($data['from']);
                        File::put($data['to'], $data['content']);
                        $this->line("✅ Renamed {$data['from']} → {$data['to']}");
                        break;

                    case 'update_tournament_model':
                    case 'update_controller':
                    case 'update_user_model':
                    case 'update_referee_model':
                        File::put($data['file'], $data['content']);
                        $this->line("✅ Updated {$data['file']}");
                        break;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error processing {$type}: " . $e->getMessage());
            }
        }
    }

    private function showSummary(): void
    {
        $this->info('');
        $this->info('📋 SUMMARY MODIFICHE:');
        $this->info('=====================');

        $stats = [
            'rename_model' => 0,
            'rename_controller' => 0,
            'update_tournament_model' => 0,
            'update_controller' => 0,
            'update_user_model' => 0,
            'update_referee_model' => 0,
        ];

        foreach ($this->changes as $change) {
            $stats[$change['type']]++;
        }

        $this->line("Model rinominati: {$stats['rename_model']}");
        $this->line("Controller rinominati: {$stats['rename_controller']}");
        $this->line("Tournament model aggiornati: {$stats['update_tournament_model']}");
        $this->line("Controller aggiornati: {$stats['update_controller']}");
        $this->line("User model aggiornati: {$stats['update_user_model']}");
        $this->line("Referee model aggiornati: {$stats['update_referee_model']}");
        $this->line("TOTALE: " . count($this->changes) . " modifiche");
    }
}
