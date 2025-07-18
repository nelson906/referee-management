#!/bin/bash
# Script COMPLETO per eliminare $levels e usare referee_levels() ovunque

echo "🚀 AVVIO FIX COMPLETO per referee levels..."

# Backup completo
echo "📦 Backup completo del progetto..."
timestamp=$(date +%Y%m%d_%H%M%S)
tar -czf "referee_fix_backup_$timestamp.tar.gz" app/Http/Controllers/ resources/views/ config/ 2>/dev/null

# ================================================================
# STEP 1: Fix delle VIEW BLADE
# ================================================================
echo "🎨 STEP 1: Fixing view blade..."

# Sostituisci $levels con referee_levels() nelle view
find resources/views/ -name "*.blade.php" -type f -exec sed -i.bak \
    -e 's/@foreach($levels as $key => $label)/@foreach(referee_levels() as $key => $label)/g' \
    -e 's/@foreach( *$levels as $key => $label)/@foreach(referee_levels() as $key => $label)/g' \
    -e 's/@foreach($levels as \$key => \$label)/@foreach(referee_levels() as $key => $label)/g' \
    {} \;

# Sostituisci confronti di selected con normalize
find resources/views/ -name "*.blade.php" -type f -exec sed -i.bak2 \
    -e 's/{{ $referee->level === $key ? '\''selected'\'' : '\'''\'' }}/{{ normalize_referee_level($referee->level) === $key ? '\''selected'\'' : '\'''\'' }}/g' \
    -e 's/{{ $user->level === $key ? '\''selected'\'' : '\'''\'' }}/{{ normalize_referee_level($user->level) === $key ? '\''selected'\'' : '\'''\'' }}/g' \
    -e 's/{{ old('\''level'\'') === $key ? '\''selected'\'' : '\'''\'' }}/{{ old('\''level'\'') === $key ? '\''selected'\'' : '\'''\'' }}/g' \
    {} \;

# ================================================================
# STEP 2: Fix dei CONTROLLER
# ================================================================
echo "🎮 STEP 2: Fixing controller..."

# Rimuovi righe $levels = RefereeLevelsHelper::getSelectOptions();
find app/Http/Controllers/ -name "*.php" -type f -exec sed -i.bak \
    -e '/\$levels = RefereeLevelsHelper::getSelectOptions();/d' \
    -e '/\$levels = RefereeLevelsHelper::getSelectOptions()/d' \
    {} \;

# Rimuovi 'levels' da compact() mantenendo il resto
find app/Http/Controllers/ -name "*.php" -type f -exec sed -i.bak2 \
    -e "s/compact('levels', 'zones')/compact('zones')/g" \
    -e "s/compact('referee', 'levels', 'zones')/compact('referee', 'zones')/g" \
    -e "s/compact('user', 'zones', 'levels')/compact('user', 'zones')/g" \
    -e "s/compact('levels')/null/g" \
    {} \;

# ================================================================
# STEP 3: Verifica USE STATEMENTS
# ================================================================
echo "📝 STEP 3: Verificando use statements..."

# Trova file che usano RefereeLevelsHelper senza use statement
find app/Http/Controllers/ -name "*.php" -exec grep -l "RefereeLevelsHelper" {} \; | while read file; do
    if ! grep -q "use App\\Helpers\\RefereeLevelsHelper;" "$file"; then
        echo "⚠️  Aggiungendo use statement in: $file"
        # Aggiungi use statement dopo namespace
        sed -i.bak3 '/^namespace/a\
use App\\Helpers\\RefereeLevelsHelper;' "$file"
    fi
done

# ================================================================
# STEP 4: PULIZIA
# ================================================================
echo "🧹 STEP 4: Pulizia file temporanei..."

# Rimuovi tutti i file .bak
find . -name "*.bak" -delete 2>/dev/null
find . -name "*.bak2" -delete 2>/dev/null
find . -name "*.bak3" -delete 2>/dev/null

# ================================================================
# STEP 5: VERIFICA RISULTATI
# ================================================================
echo "✅ STEP 5: Verifica risultati..."

echo ""
echo "📊 RISULTATI:"
echo "=============="

# Controlla occorrenze rimanenti di $levels
levels_count=$(grep -r "\$levels" resources/views/ app/Http/Controllers/ --include="*.php" --include="*.blade.php" 2>/dev/null | wc -l)
echo "🔍 Occorrenze \$levels rimanenti: $levels_count"

# Controlla che referee_levels() sia usato
referee_levels_count=$(grep -r "referee_levels()" resources/views/ --include="*.blade.php" 2>/dev/null | wc -l)
echo "✅ Occorrenze referee_levels() aggiunte: $referee_levels_count"

# Controlla use statements
use_count=$(grep -r "use App\\Helpers\\RefereeLevelsHelper;" app/Http/Controllers/ --include="*.php" 2>/dev/null | wc -l)
echo "📝 Controller con use statement: $use_count"

echo ""
echo "🔍 VERIFICA MANUALE - Controlla questi file:"
echo "=============================================="

# Mostra file che potrebbero ancora avere problemi
echo "📁 View con possibili problemi:"
grep -r "\$levels" resources/views/ --include="*.blade.php" 2>/dev/null | head -3 || echo "  ✅ Nessun problema trovato"

echo ""
echo "📁 Controller con possibili problemi:"
grep -r "\$levels" app/Http/Controllers/ --include="*.php" 2>/dev/null | head -3 || echo "  ✅ Nessun problema trovato"

echo ""
echo "🎯 PROSSIMI PASSI:"
echo "=================="
echo "1. Testa le pagine admin/referees/create"
echo "2. Testa le pagine admin/referees/edit"
echo "3. Testa referee/profile/edit"
echo "4. Se tutto funziona, elimina backup: rm referee_fix_backup_$timestamp.tar.gz"
echo ""
echo "🚨 Se qualcosa non funziona:"
echo "  tar -xzf referee_fix_backup_$timestamp.tar.gz"
echo ""
echo "✅ Fix completato!"
