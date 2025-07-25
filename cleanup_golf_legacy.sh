#!/bin/bash

echo "🧹 GOLF SYSTEM CLEANUP - Rimozione file legacy"
echo "================================================"

# Chiedi conferma prima di procedere
echo "⚠️  ATTENZIONE: Questa operazione rimuoverà file legacy dal sistema."
echo "📋 FILE DA RIMUOVERE:"
echo "   ❌ SimpleMigrationCommand.php"
echo "   ❌ RecoveryDataCommand.php"
echo "   ❌ DataMigrationSeeder.php + TestDataMigrationSeeder.php"
echo "   ❌ DataImprovementSeeder.php + TestDataImprovement.php"
echo "   ❌ 8 debug commands (FixTournament*, Check*, etc.)"
echo ""
echo "✅ FILE MANTENUTI:"
echo "   ✅ MasterMigrationSeeder.php (VERSIONE FINALE)"
echo "   ✅ MasterMigrationCommand.php (COMANDO ATTIVO)"
echo "   ✅ Tutti i Development Seeders (Golf System)"
echo "   ✅ Tutti i Golf Commands (GolfSeed, GolfDiagnostic, etc.)"
echo ""

read -p "🤔 Procedere con backup + pulizia? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Operazione annullata."
    exit 1
fi

# Crea backup prima della pulizia
echo "📦 Creando backup completo..."
mkdir -p .cleanup_backup/$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=".cleanup_backup/$(date +%Y%m%d_%H%M%S)"

# Backup COMPLETO di tutti i file legacy
echo "💾 Backup seeder legacy..."
cp database/seeders/DataMigrationSeeder.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ DataMigrationSeeder.php (42KB - Jul 23 22:48)"
cp database/seeders/DataImprovementSeeder.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ DataImprovementSeeder.php (36KB - Jul 24 20:45)"
cp database/seeders/TestDataMigrationSeeder.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ TestDataMigrationSeeder.php (5KB - Jul 23 18:35)"

echo "💾 Backup commands legacy..."
cp app/Console/Commands/SimpleMigrationCommand.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ SimpleMigrationCommand.php (12KB - Jul 20)"
cp app/Console/Commands/RecoveryDataCommand.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ RecoveryDataCommand.php (15KB - Jul 20)"
cp app/Console/Commands/TestDataImprovement.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ TestDataImprovement.php (1KB - Jul 24)"

echo "💾 Backup debug commands (sessione Jul 20)..."
cp app/Console/Commands/CheckMappingFixed.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ CheckMappingFixed.php"
cp app/Console/Commands/CheckTournamentMapping.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ CheckTournamentMapping.php"
cp app/Console/Commands/CompleteFeatures.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ CompleteFeatures.php"
cp app/Console/Commands/DebugTournaments.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ DebugTournaments.php"
cp app/Console/Commands/FixTournamentAlignment.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ FixTournamentAlignment.php"
cp app/Console/Commands/FixTournamentNames.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ FixTournamentNames.php"
cp app/Console/Commands/FixedAlignmentCommand.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ FixedAlignmentCommand.php"
cp app/Console/Commands/NotificationMaintenanceCommand.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ NotificationMaintenanceCommand.php"
cp app/Console/Commands/QuickFixAlignment.php "$BACKUP_DIR/" 2>/dev/null && echo "  ✅ QuickFixAlignment.php"

echo "💾 Backup file di sistema..."
cp database/seeders/DatabaseSeeder.php "$BACKUP_DIR/DatabaseSeeder_before_cleanup.php" 2>/dev/null && echo "  ✅ DatabaseSeeder.php (backup)"

# Crea file README nel backup
cat > "$BACKUP_DIR/README.md" << EOF
# Golf System Cleanup Backup
Data: $(date)

## File Backup
Questo backup contiene tutti i file legacy rimossi durante la pulizia.

### Seeder Legacy:
- DataMigrationSeeder.php (42KB) - Prima versione migrazione
- DataImprovementSeeder.php (36KB) - Versione migliorata
- TestDataMigrationSeeder.php (5KB) - Test helper

### Commands Legacy:
- SimpleMigrationCommand.php (12KB) - Comando migrazione semplice
- RecoveryDataCommand.php (15KB) - Recovery da golf_referee_new
- TestDataImprovement.php (1KB) - Test command

### Debug Commands (Sessione Jul 20):
- CheckMappingFixed.php, CheckTournamentMapping.php
- CompleteFeatures.php, DebugTournaments.php
- FixTournament*.php, QuickFixAlignment.php
- NotificationMaintenanceCommand.php

## Note:
La versione finale è MasterMigrationSeeder.php (44KB - Jul 25)
Il comando attivo è MasterMigrationCommand.php (6KB - Jul 24)
EOF

echo ""
echo "🗑️  RIMOZIONE FILE LEGACY"
echo "========================"

# Rimuovi catena DataMigrationSeeder
echo "🗂️ Rimozione catena DataMigrationSeeder..."
rm -f database/seeders/DataMigrationSeeder.php && echo "  ❌ Rimosso DataMigrationSeeder.php (42KB)"
rm -f database/seeders/TestDataMigrationSeeder.php && echo "  ❌ Rimosso TestDataMigrationSeeder.php (dipendente)"

# Rimuovi catena DataImprovementSeeder
echo "🗂️ Rimozione catena DataImprovementSeeder..."
rm -f database/seeders/DataImprovementSeeder.php && echo "  ❌ Rimosso DataImprovementSeeder.php (36KB)"
rm -f app/Console/Commands/TestDataImprovement.php && echo "  ❌ Rimosso TestDataImprovement.php (dipendente)"

# Rimuovi commands legacy indipendenti
echo "🎛️ Rimozione commands legacy..."
rm -f app/Console/Commands/SimpleMigrationCommand.php && echo "  ❌ Rimosso SimpleMigrationCommand.php (12KB)"
rm -f app/Console/Commands/RecoveryDataCommand.php && echo "  ❌ Rimosso RecoveryDataCommand.php (15KB)"

# Rimuovi debug commands (sessione Jul 20)
echo "🐛 Rimozione debug commands..."
rm -f app/Console/Commands/CheckMappingFixed.php && echo "  ❌ Rimosso CheckMappingFixed.php"
rm -f app/Console/Commands/CheckTournamentMapping.php && echo "  ❌ Rimosso CheckTournamentMapping.php"
rm -f app/Console/Commands/CompleteFeatures.php && echo "  ❌ Rimosso CompleteFeatures.php"
rm -f app/Console/Commands/DebugTournaments.php && echo "  ❌ Rimosso DebugTournaments.php"
rm -f app/Console/Commands/FixTournamentAlignment.php && echo "  ❌ Rimosso FixTournamentAlignment.php"
rm -f app/Console/Commands/FixTournamentNames.php && echo "  ❌ Rimosso FixTournamentNames.php"
rm -f app/Console/Commands/FixedAlignmentCommand.php && echo "  ❌ Rimosso FixedAlignmentCommand.php"
rm -f app/Console/Commands/NotificationMaintenanceCommand.php && echo "  ❌ Rimosso NotificationMaintenanceCommand.php"
rm -f app/Console/Commands/QuickFixAlignment.php && echo "  ❌ Rimosso QuickFixAlignment.php"

echo ""
echo "✅ CLEANUP COMPLETATO!"
echo "====================="

echo "📦 File di backup salvati in: $BACKUP_DIR"
echo "📝 Consulta: $BACKUP_DIR/README.md per dettagli"
echo ""

echo "🏆 SISTEMA PULITO - Architettura finale:"
echo ""
echo "📁 SEEDER FINALI:"
echo "  ✅ MasterMigrationSeeder.php (44KB - VERSIONE FINALE per dati reali)"
echo "  ✅ DatabaseSeeder.php (Orchestratore)"
echo "  ✅ Development Seeders completi (ZoneSeeder, UserSeeder, etc.)"
echo ""
echo "📁 COMMANDS ATTIVI:"
echo "  ✅ MasterMigrationCommand.php (6KB - Comando per MasterMigrationSeeder)"
echo "  ✅ GolfSeedCommand.php (Seeding development)"
echo "  ✅ GolfDiagnosticCommand.php (Diagnostica sistema)"
echo "  ✅ GolfExportCommand.php, GolfMaintenanceCommand.php"
echo "  ✅ GolfBackupSystem.php, GolfMonitoringSystem.php"
echo ""

echo "🔍 MANTIENI DA VERIFICARE:"
echo "  🤔 AnalyzeDatabaseCommand.php (potrebbe essere utile)"
echo "  🤔 RealDatabaseAnalyzerCommand.php (potrebbe essere utile)"
echo ""

echo "📊 STATISTICHE PULIZIA:"
REMOVED_COUNT=13
BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "  ❌ File rimossi: $REMOVED_COUNT"
echo "  💾 Backup size: $BACKUP_SIZE"
echo "  🏗️  Architettura: SEMPLIFICATA"
echo ""

echo "🧪 TEST POST-PULIZIA:"
echo "1. Test seeder development:"
echo "   php artisan golf:seed --fresh"
echo ""
echo "2. Test sistema diagnostica:"
echo "   php artisan golf:diagnostic"
echo ""
echo "3. Test comando master (se hai dati reali):"
echo "   php artisan master:migration --dry-run"
echo ""

echo "🗑️  Se tutto funziona, rimuovi backup:"
echo "   rm -rf .cleanup_backup"
echo ""

# Mostra comandi disponibili
echo "📋 COMANDI GOLF DISPONIBILI DOPO PULIZIA:"
php artisan list | grep -E "(golf|master)" || echo "   (Esegui 'php artisan list' per vedere tutti i comandi)"

echo ""
echo "🎉 PRONTO PER PRODUCTION DASHBOARD! 🎯"
