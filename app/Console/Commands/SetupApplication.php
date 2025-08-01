<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupApplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup application with all necessary seeders and admin creation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting application setup...');

        // Array dei comandi da eseguire
        $commands = [
            ['db:seed', ['--class' => 'ZoneSeeder']],
            ['db:seed', ['--class' => 'TournamentTypeSeeder']],
            ['db:seed', ['--class' => 'LetterheadSeeder']],
            ['db:seed', ['--class' => 'LetterTemplateSeeder']],
            ['golf:create-admin', []],
            ['db:seed', ['--class' => 'MasterMigrationSeeder']],
        ];

        foreach ($commands as $index => $commandData) {
            $commandName = $commandData[0];
            $arguments = $commandData[1];

            $this->info("Step " . ($index + 1) . ": Running {$commandName}...");

            $result = $this->call($commandName, $arguments);

            if ($result !== 0) {
                $this->error("Failed to execute: {$commandName}");
                return 1;
            }

            $this->info("✓ {$commandName} completed successfully");
        }

        $this->info('🎉 Application setup completed successfully!');
        return 0;
    }
}
