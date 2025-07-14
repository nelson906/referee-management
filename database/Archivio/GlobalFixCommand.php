<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GlobalFixCommand extends Command
{
    protected $signature = 'fix:tournament-category-references';
    protected $description = 'Fix tutti i riferimenti TournamentCategory → TournamentType';

    public function handle()
    {
        $this->info('🔧 GLOBAL FIX: TournamentCategory → TournamentType');
        $this->info('===========================================');

        $replacements = [
            // Model references
            'TournamentCategory::' => 'TournamentType::',
            'use App\Models\TournamentCategory;' => 'use App\Models\TournamentType;',
            '$tournamentCategory' => '$tournamentType',

            // Database column references
            'tournament_category_id' => 'tournament_type_id',
            'tournament_categories' => 'tournament_types',

            // Route references
            'tournament-categories' => 'tournament-types',
            'tournament_categories' => 'tournament_types',

            // View references
            "'tournamentCategory'" => "'tournamentType'",
            "'categories'" => "'types'",

            // Method and relationship references
            'tournamentCategory()' => 'tournamentType()',
            'tournamentCategory:' => 'tournamentType:',

            // Comment and string references
            'Tournament Category' => 'Tournament Type',
            'tournament category' => 'tournament type',
        ];

        $directories = [
            app_path('Http/Controllers'),
            app_path('Models'),
            app_path('Http/Requests'),
            resource_path('views'),
            database_path('seeders'),
        ];

        $totalFiles = 0;
        $modifiedFiles = 0;

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                continue;
            }

            $files = File::allFiles($directory);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' && $file->getExtension() !== 'blade') {
                    continue;
                }

                $totalFiles++;
                $content = File::get($file->getPathname());
                $originalContent = $content;

                foreach ($replacements as $search => $replace) {
                    $content = str_replace($search, $replace, $content);
                }

                if ($content !== $originalContent) {
                    File::put($file->getPathname(), $content);
                    $modifiedFiles++;
                    $this->line("✅ Updated: " . $file->getRelativePathname());
                }
            }
        }

        $this->info('');
        $this->info("📊 SUMMARY:");
        $this->info("Files scanned: {$totalFiles}");
        $this->info("Files modified: {$modifiedFiles}");
        $this->info('');

        // Special fixes for specific problematic files
        $this->handleSpecialCases();

        $this->info('✅ Global fix completed!');
        $this->warn('🔄 NEXT: Test login and basic functionality');

        return Command::SUCCESS;
    }

    private function handleSpecialCases()
    {
        $this->info('🎯 Handling special cases...');

        // Fix specific controller file that might have complex references
        $dashboardController = app_path('Http/Controllers/Referee/DashboardController.php');
        if (File::exists($dashboardController)) {
            $content = File::get($dashboardController);

            // Fix the specific problematic query
            $oldQuery = "->join('tournament_categories', 'tournaments.tournament_category_id', '=', 'tournament_categories.id')";
            $newQuery = "->join('tournament_types', 'tournaments.tournament_type_id', '=', 'tournament_types.id')";

            $content = str_replace($oldQuery, $newQuery, $content);

            // Fix the select statement
            $content = str_replace(
                "->select('tournament_categories.name', DB::raw('count(*) as total'))",
                "->select('tournament_types.name', DB::raw('count(*) as total'))",
                $content
            );

            // Fix group by
            $content = str_replace(
                "->groupBy('tournament_categories.name')",
                "->groupBy('tournament_types.name')",
                $content
            );

            File::put($dashboardController, $content);
            $this->line("✅ Fixed DashboardController special cases");
        }

        // Fix any remaining super admin controller references
        $superAdminController = app_path('Http/Controllers/SuperAdmin/TournamentCategoryController.php');
        if (File::exists($superAdminController)) {
            // Rename the controller file itself
            $newControllerPath = app_path('Http/Controllers/SuperAdmin/TournamentTypeController.php');
            File::move($superAdminController, $newControllerPath);

            // Update class name in the file
            $content = File::get($newControllerPath);
            $content = str_replace(
                'class TournamentCategoryController',
                'class TournamentTypeController',
                $content
            );
            File::put($newControllerPath, $content);

            $this->line("✅ Renamed SuperAdmin controller");
        }

        // Fix routes file
        $routesFile = base_path('routes/web.php');
        if (File::exists($routesFile)) {
            $content = File::get($routesFile);
            $content = str_replace(
                'SuperAdmin\TournamentCategoryController::class',
                'SuperAdmin\TournamentTypeController::class',
                $content
            );
            File::put($routesFile, $content);
            $this->line("✅ Fixed routes references");
        }
    }
}
