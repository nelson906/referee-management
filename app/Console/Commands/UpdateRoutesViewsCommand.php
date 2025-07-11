<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateRoutesViewsCommand extends Command
{
    protected $signature = 'consolidation:update-routes-views {--dry-run : Solo mostra cosa verrebbe modificato}';
    protected $description = 'Aggiorna Routes e Views dopo mega consolidation migration';

    private $changes = [];
    private $isDryRun = false;

    public function handle()
    {
        $this->isDryRun = $this->option('dry-run');

        $this->info('🛣️  AGGIORNAMENTO ROUTES/VIEWS POST-MIGRATION');
        $this->info('===============================================');

        if ($this->isDryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Solo preview delle modifiche');
        }

        // 1. Aggiorna routes/web.php
        $this->updateWebRoutes();

        // 2. Rinomina directory views
        $this->renameViewsDirectory();

        // 3. Aggiorna contenuto views
        $this->updateViewsContent();

        // 4. Cerca altre references nei blade files
        $this->updateOtherBladeReferences();

        // 5. Aggiorna form requests se esistono
        $this->updateFormRequests();

        // Summary
        $this->showSummary();

        if (!$this->isDryRun && $this->confirm('Procedere con le modifiche?')) {
            $this->applyChanges();
            $this->info('✅ Aggiornamenti Routes/Views applicati con successo!');

            $this->warn('🔄 PROSSIMI STEP MANUALI:');
            $this->warn('1. Controlla che tutte le route funzionino');
            $this->warn('2. Verifica che non ci siano blade con vecchie references');
            $this->warn('3. Aggiorna eventuali link nei menu/navigation');
            $this->warn('4. Testa completamente le views super-admin');
        }

        return Command::SUCCESS;
    }

    private function updateWebRoutes(): void
    {
        $this->info('1. 🛣️  Update routes/web.php');

        $routesPath = base_path('routes/web.php');

        if (!File::exists($routesPath)) {
            $this->warn("   ⚠️  routes/web.php non trovato");
            return;
        }

        $content = File::get($routesPath);
        $originalContent = $content;

        // Update tournament-categories → tournament-types
        $content = str_replace([
            "Route::resource('tournament-categories', SuperAdmin\\TournamentCategoryController::class);",
            "Route::post('tournament-categories/update-order'",
            "Route::post('tournament-categories/{tournamentCategory}/toggle-active'",
            "Route::post('tournament-categories/{tournamentCategory}/duplicate'",
            "'tournament-categories.update-order'",
            "'tournament-categories.toggle-active'",
            "'tournament-categories.duplicate'",
            'TournamentCategoryController',
        ], [
            "Route::resource('tournament-types', SuperAdmin\\TournamentTypeController::class);",
            "Route::post('tournament-types/update-order'",
            "Route::post('tournament-types/{tournamentType}/toggle-active'",
            "Route::post('tournament-types/{tournamentType}/duplicate'",
            "'tournament-types.update-order'",
            "'tournament-types.toggle-active'",
            "'tournament-types.duplicate'",
            'TournamentTypeController',
        ], $content);

        // Update route parameter names
        $content = str_replace([
            '{tournamentCategory}',
            'tournamentCategory',
        ], [
            '{tournamentType}',
            'tournamentType',
        ], $content);

        // Update route names in comments
        $content = str_replace([
            'tournament-categories.',
            'Tournament Categories Management',
            'tournament categories',
        ], [
            'tournament-types.',
            'Tournament Types Management',
            'tournament types',
        ], $content);

        if ($content !== $originalContent) {
            $this->addChange('update_routes', [
                'file' => $routesPath,
                'content' => $content
            ]);
        }
    }

    private function renameViewsDirectory(): void
    {
        $this->info('2. 📁 Rename Views Directory');

        $oldDir = resource_path('views/super-admin/tournament-categories');
        $newDir = resource_path('views/super-admin/tournament-types');

        if (!File::isDirectory($oldDir)) {
            $this->warn("   ⚠️  Directory {$oldDir} non trovata");
            return;
        }

        $this->addChange('rename_views_directory', [
            'from' => $oldDir,
            'to' => $newDir
        ]);
    }

    private function updateViewsContent(): void
    {
        $this->info('3. 📝 Update Views Content');

        $viewsDir = resource_path('views/super-admin/tournament-categories');
        $newViewsDir = resource_path('views/super-admin/tournament-types');

        // Se la directory non esiste, skip
        if (!File::isDirectory($viewsDir)) {
            $this->warn("   ⚠️  Views directory non trovata");
            return;
        }

        $bladeFiles = File::allFiles($viewsDir);

        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->updateBladeFile($file->getPathname(), $newViewsDir);
            }
        }
    }

    private function updateBladeFile(string $filePath, string $newBaseDir): void
    {
        $content = File::get($filePath);
        $originalContent = $content;

        // Update route references
        $content = str_replace([
            "route('super-admin.tournament-categories.",
            'super-admin.tournament-categories.',
            'tournament-categories.',
        ], [
            "route('super-admin.tournament-types.",
            'super-admin.tournament-types.',
            'tournament-types.',
        ], $content);

        // Update variable names
        $content = str_replace([
            '$tournamentCategory',
            '@foreach($categories as $category)',
            'compact(\'categories\')',
            'compact(\'tournamentCategory\'',
            'compact(\'recentTournaments\', \'tournamentCategory\')',
        ], [
            '$tournamentType',
            '@foreach($types as $type)',
            'compact(\'types\')',
            'compact(\'tournamentType\'',
            'compact(\'recentTournaments\', \'tournamentType\')',
        ], $content);

        // Update form field names
        $content = str_replace([
            'name="tournament_category_id"',
            'tournament_category_id',
        ], [
            'name="tournament_type_id"',
            'tournament_type_id',
        ], $content);

        // Update labels and text
        $content = str_replace([
            'Categorie Torneo',
            'Categoria Torneo',
            'categoria torneo',
            'Categoria:',
            'Categorie:',
            'della categoria:',
            'Nuova Categoria',
            'Modifica Categoria',
            'Elimina Categoria',
        ], [
            'Tipi Gara',
            'Tipo Gara',
            'tipo gara',
            'Tipo:',
            'Tipi:',
            'del tipo:',
            'Nuovo Tipo',
            'Modifica Tipo',
            'Elimina Tipo',
        ], $content);

        // Update titles and headers
        $content = str_replace([
            '<h1 class="text-3xl font-bold text-gray-900">Categorie Torneo</h1>',
            '<h1 class="text-3xl font-bold text-gray-900">Nuova Categoria Torneo</h1>',
            '<h1 class="text-3xl font-bold text-gray-900">Modifica Categoria Torneo</h1>',
            '@section(\'title\', \'Categorie Torneo\')',
            '@section(\'title\', \'Nuova Categoria\')',
            '@section(\'title\', \'Modifica Categoria: \'',
        ], [
            '<h1 class="text-3xl font-bold text-gray-900">Tipi Gara</h1>',
            '<h1 class="text-3xl font-bold text-gray-900">Nuovo Tipo Gara</h1>',
            '<h1 class="text-3xl font-bold text-gray-900">Modifica Tipo Gara</h1>',
            '@section(\'title\', \'Tipi Gara\')',
            '@section(\'title\', \'Nuovo Tipo\')',
            '@section(\'title\', \'Modifica Tipo: \'',
        ], $content);

        // Update model references in blade
        $content = str_replace([
            '{{ $tournamentCategory->name }}',
            '{{ $category->name }}',
            '$tournamentCategory->',
            '$category->',
        ], [
            '{{ $tournamentType->name }}',
            '{{ $type->name }}',
            '$tournamentType->',
            '$type->',
        ], $content);

        if ($content !== $originalContent) {
            // Calculate new file path
            $relativePath = str_replace(resource_path('views/super-admin/tournament-categories'), '', $filePath);
            $newFilePath = $newBaseDir . $relativePath;

            $this->addChange('update_blade_file', [
                'from' => $filePath,
                'to' => $newFilePath,
                'content' => $content
            ]);
        }
    }

    private function updateOtherBladeReferences(): void
    {
        $this->info('4. 🔍 Update Other Blade References');

        // Cerca in tutte le views per riferimenti a tournament-categories
        $viewDirs = [
            resource_path('views/admin'),
            resource_path('views/layouts'),
            resource_path('views/reports'),
        ];

        foreach ($viewDirs as $dir) {
            if (File::isDirectory($dir)) {
                $this->searchAndUpdateBladeReferences($dir);
            }
        }
    }

    private function searchAndUpdateBladeReferences(string $directory): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->updateGeneralBladeFile($file->getPathname());
            }
        }
    }

    private function updateGeneralBladeFile(string $filePath): void
    {
        $content = File::get($filePath);
        $originalContent = $content;

        // Update tournament_category_id references
        $content = str_replace([
            'tournament_category_id',
            'tournamentCategory',
            'tournament-categories',
        ], [
            'tournament_type_id',
            'tournamentType',
            'tournament-types',
        ], $content);

        // Update form selections
        $content = str_replace([
            'name="category_id"',
            'id="category_id"',
            'old(\'category_id\')',
            'request->category_id',
            '$categories as $category',
            '$category->id',
            '$category->name',
        ], [
            'name="type_id"',
            'id="type_id"',
            'old(\'type_id\')',
            'request->type_id',
            '$types as $type',
            '$type->id',
            '$type->name',
        ], $content);

        // Update compact() calls
        $content = str_replace([
            'compact(\'tournaments\', \'zones\', \'categories\'',
            'compact(\'categories\'',
            ', \'categories\'',
        ], [
            'compact(\'tournaments\', \'zones\', \'types\'',
            'compact(\'types\'',
            ', \'types\'',
        ], $content);

        if ($content !== $originalContent) {
            $this->addChange('update_general_blade', [
                'file' => $filePath,
                'content' => $content
            ]);
        }
    }

    private function updateFormRequests(): void
    {
        $this->info('5. 📋 Update Form Requests');

        $formRequestPath = app_path('Http/Requests/TournamentCategoryRequest.php');
        $newFormRequestPath = app_path('Http/Requests/TournamentTypeRequest.php');

        if (File::exists($formRequestPath)) {
            $content = File::get($formRequestPath);

            // Update class name
            $content = str_replace([
                'class TournamentCategoryRequest',
                'TournamentCategory',
                'tournament_category',
                'Tournament Category',
            ], [
                'class TournamentTypeRequest',
                'TournamentType',
                'tournament_type',
                'Tournament Type',
            ], $content);

            $this->addChange('rename_form_request', [
                'from' => $formRequestPath,
                'to' => $newFormRequestPath,
                'content' => $content
            ]);
        }
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
            case 'update_routes':
                $this->line("   📝 UPDATE: {$data['file']}");
                break;
            case 'rename_views_directory':
                $this->line("   📁 RENAME DIR: {$data['from']} → {$data['to']}");
                break;
            case 'update_blade_file':
                $this->line("   🔄 MOVE+UPDATE: {$data['from']} → {$data['to']}");
                break;
            case 'update_general_blade':
                $this->line("   ✏️  UPDATE: {$data['file']}");
                break;
            case 'rename_form_request':
                $this->line("   🔄 RENAME: {$data['from']} → {$data['to']}");
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
                    case 'update_routes':
                    case 'update_general_blade':
                        File::put($data['file'], $data['content']);
                        $this->line("✅ Updated {$data['file']}");
                        break;

                    case 'rename_views_directory':
                        if (File::isDirectory($data['from'])) {
                            File::moveDirectory($data['from'], $data['to']);
                            $this->line("✅ Renamed directory {$data['from']} → {$data['to']}");
                        }
                        break;

                    case 'update_blade_file':
                        // Ensure directory exists
                        $dir = dirname($data['to']);
                        if (!File::isDirectory($dir)) {
                            File::makeDirectory($dir, 0755, true);
                        }
                        File::put($data['to'], $data['content']);
                        $this->line("✅ Updated blade {$data['to']}");
                        break;

                    case 'rename_form_request':
                        File::delete($data['from']);
                        File::put($data['to'], $data['content']);
                        $this->line("✅ Renamed FormRequest {$data['from']} → {$data['to']}");
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
            'update_routes' => 0,
            'rename_views_directory' => 0,
            'update_blade_file' => 0,
            'update_general_blade' => 0,
            'rename_form_request' => 0,
        ];

        foreach ($this->changes as $change) {
            if (isset($stats[$change['type']])) {
                $stats[$change['type']]++;
            }
        }

        $this->line("Routes aggiornate: {$stats['update_routes']}");
        $this->line("Directory rinominate: {$stats['rename_views_directory']}");
        $this->line("Blade files super-admin: {$stats['update_blade_file']}");
        $this->line("Altri blade files: {$stats['update_general_blade']}");
        $this->line("Form requests: {$stats['rename_form_request']}");
        $this->line("TOTALE: " . count($this->changes) . " modifiche");
    }
}
