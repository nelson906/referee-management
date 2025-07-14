#!/bin/bash

# 🛠️ MIGRATION DIRECTORY CLEANUP SCRIPT
# This script will backup and clean the chaotic migration directory

echo "🚨 MIGRATION DIRECTORY CLEANUP STARTING..."
echo "⚠️  This will REMOVE problematic migration files!"
echo "📦 Creating backup first..."

# Create backup directory with timestamp
BACKUP_DIR="database/migrations_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup all current migrations
cp database/migrations/*.php "$BACKUP_DIR/"
echo "✅ Backup created in: $BACKUP_DIR"

echo ""
echo "🗑️  REMOVING PROBLEMATIC MIGRATIONS..."

# ❌ REMOVE DUPLICATE USERS TABLES
rm -f database/migrations/2025_07_04_160815_create_users_table_complete.php
echo "   Removed: create_users_table_complete.php (duplicate)"

# ❌ REMOVE DUPLICATE TOURNAMENTS TABLES
rm -f database/migrations/2025_07_04_160821_create_tournaments_table_corrected.php
echo "   Removed: create_tournaments_table_corrected.php (duplicate)"

# ❌ REMOVE EXTENSION-ONLY REFEREES (we'll replace with corrected version)
rm -f database/migrations/2025_07_04_160816_create_referees_table_extension_only.php
echo "   Removed: create_referees_table_extension_only.php (will be replaced)"

# ❌ REMOVE TOURNAMENT TYPES COMPLETE (we'll replace with corrected version)
rm -f database/migrations/2025_07_04_160820_create_tournament_types_table_complete.php
echo "   Removed: create_tournament_types_table_complete.php (will be replaced)"

# ❌ REMOVE ADD MIGRATIONS (will be incorporated)
rm -f database/migrations/2025_07_05_151722_add_missing_columns_to_users_table.php
echo "   Removed: add_missing_columns_to_users_table.php (will be incorporated)"

rm -f database/migrations/2025_07_05_150614_add_min_max_referees_to_tournament_categories.php
echo "   Removed: add_min_max_referees_to_tournament_categories.php (will be incorporated)"

# ❌ REMOVE MALFORMED FILES
rm -f database/migrations/2025_07_10_095211_remove_min_max_referees_constraints.php.php
echo "   Removed: remove_min_max_referees_constraints.php.php (malformed filename)"

# ❌ REMOVE NON-CONVENTIONAL FILES
rm -f database/migrations/mega_migration_fixed.php
echo "   Removed: mega_migration_fixed.php (non-conventional naming)"

# ❌ REMOVE CLEANUP DATA MIGRATION (we'll replace)
rm -f database/migrations/2025_07_06_000000_cleanup_and_migrate_existing_data.php
echo "   Removed: cleanup_and_migrate_existing_data.php (will be replaced)"

echo ""
echo "✅ CLEANUP COMPLETED!"
echo ""
echo "📋 REMAINING MIGRATIONS (should be clean):"
ls -la database/migrations/
echo ""
echo "🎯 NEXT STEPS:"
echo "   1. Verify remaining migrations are correct"
echo "   2. Add new clean migration set"
echo "   3. Run: php artisan migrate:fresh --seed"
echo ""
echo "📦 Backup location: $BACKUP_DIR"
