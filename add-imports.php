<?php

/**
 * add-imports.php
 * Script PHP per aggiungere automaticamente gli import Laravel Facades
 * Più affidabile di sed/awk
 */

$imports = [
    'DB' => [
        'import' => 'use Illuminate\Support\Facades\DB;',
        'files' => [
            'app/Console/Commands/MasterMigrationCommand.php',
            'app/Http/Controllers/Admin/AssignmentController.php',
            'app/Http/Controllers/SuperAdmin/SystemSettingsController.php'
        ]
    ],
    'Log' => [
        'import' => 'use Illuminate\Support\Facades\Log;',
        'files' => [
            'app/Helpers/RefereeLevelsHelper.php',
            'app/Http/Controllers/Admin/RefereeController.php',
            'app/Http/Controllers/Admin/TournamentController.php',
            'app/Http/Controllers/Referee/AvailabilityController.php',
            'app/Services/NotificationService.php',
            'app/Services/TournamentNotificationService.php'
        ]
    ],
    'Cache' => [
        'import' => 'use Illuminate\Support\Facades\Cache;',
        'files' => [
            'app/Console/Commands/QuickHealthCheckCommand.php'
        ]
    ],
    'Storage' => [
        'import' => 'use Illuminate\Support\Facades\Storage;',
        'files' => [
            'app/Http/Controllers/SuperAdmin/UserController.php'
        ]
    ],
    'Str' => [
        'import' => 'use Illuminate\Support\Str;',
        'files' => [
            'app/Http/Controllers/SuperAdmin/SystemController.php',
            'app/Http/Controllers/SuperAdmin/UserController.php'
        ]
    ]
];

echo "🚀 Aggiunta automatica import Laravel Facades\n";

$totalProcessed = 0;
$totalAdded = 0;

foreach ($imports as $facade => $config) {
    echo "\n📦 Processando facade: {$facade}\n";

    foreach ($config['files'] as $file) {
        $totalProcessed++;

        if (!file_exists($file)) {
            echo "⚠️  File non trovato: {$file}\n";
            continue;
        }

        $content = file_get_contents($file);

        // Verifica se import già presente
        if (strpos($content, $config['import']) !== false) {
            echo "ℹ️  Import già presente: {$file}\n";
            continue;
        }

        // Trova la posizione dopo la dichiarazione namespace
        $lines = explode("\n", $content);
        $newLines = [];
        $importAdded = false;

        foreach ($lines as $line) {
            $newLines[] = $line;

            // Se troviamo namespace e non abbiamo ancora aggiunto import
            if (!$importAdded && preg_match('/^namespace\s+/', trim($line))) {
                $newLines[] = '';  // Riga vuota
                $newLines[] = $config['import'];  // Import
                $importAdded = true;
                $totalAdded++;
                echo "✅ Import aggiunto: {$file}\n";
            }
        }

        // Se namespace non trovato, aggiungi dopo <?php
        if (!$importAdded) {
            $newLines = [];
            $phpTagFound = false;

            foreach ($lines as $line) {
                $newLines[] = $line;

                if (!$phpTagFound && trim($line) === '<?php') {
                    $newLines[] = '';
                    $newLines[] = $config['import'];
                    $phpTagFound = true;
                    $importAdded = true;
                    $totalAdded++;
                    echo "✅ Import aggiunto dopo <?php: {$file}\n";
                }
            }
        }

        if ($importAdded) {
            file_put_contents($file, implode("\n", $newLines));
        } else {
            echo "⚠️  Impossibile aggiungere import in: {$file}\n";
        }
    }
}

echo "\n📊 RIEPILOGO:\n";
echo "- File processati: {$totalProcessed}\n";
echo "- Import aggiunti: {$totalAdded}\n";

// Bonus: Fix case sensitivity per club -> Club
echo "\n🔧 Correzione case sensitivity...\n";

$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('app'),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$clubFixed = 0;
foreach ($phpFiles as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        // Correggi App\Models\club -> App\Models\Club
        $content = str_replace('App\\Models\\club', 'App\\Models\\Club', $content);

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "✅ Corretto case sensitivity: {$file->getPathname()}\n";
            $clubFixed++;
        }
    }
}

if ($clubFixed > 0) {
    echo "✅ Corretti {$clubFixed} file per case sensitivity\n";
} else {
    echo "ℹ️  Nessun problema di case sensitivity trovato\n";
}

echo "\n🎉 COMPLETATO!\n";
echo "Ora puoi eseguire: ./vendor/bin/phpstan analyse --memory-limit=2G\n";
