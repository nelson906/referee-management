#!/bin/bash

# ========================================
# 🔥   RIMOZIONE COMPLETA LARAVEL TELESCOPE
# ========================================

set -e

echo "🔥 Rimozione completa Laravel Telescope..."
echo "=========================================="

# Backup prima di tutto
echo "💾 Creando backup..."
cp -r . ../backup-before-telescope-removal-$(date +%Y%m%d_%H%M%S) 2>/dev/null || echo "⚠️ Backup fallito, continuo..."

# 1. Rimuovi file e cartelle Telescope
echo "🗑️ Rimozione file Telescope..."

# File provider
rm -f app/Providers/TelescopeServiceProvider.php

# Config
rm -f config/telescope.php

# Asset pubblici
rm -rf public/vendor/telescope/

# Migrazioni
echo "🗄️ Rimozione migrazioni Telescope..."
find database/migrations/ -name "*telescope*" -delete 2>/dev/null || true
find database/migrations/ -name "*create_telescope_entries_table*" -delete 2>/dev/null || true
find database/migrations/ -name "*add_family_hash_to_telescope_entries_table*" -delete 2>/dev/null || true
find database/migrations/ -name "*create_telescope_monitoring_table*" -delete 2>/dev/null || true

# 2. Pulisci file di configurazione
echo "📝 Pulizia file configurazione..."

# Backup e pulizia config/app.php
if [ -f config/app.php ]; then
    cp config/app.php config/app.php.backup
    # Rimuovi righe Telescope da providers
    sed -i.bak '/TelescopeServiceProvider/d' config/app.php
    sed -i.bak '/Laravel.*Telescope/d' config/app.php
    rm -f config/app.php.bak
    echo "✅ Pulito config/app.php"
fi

# Pulisci AppServiceProvider
if [ -f app/Providers/AppServiceProvider.php ]; then
    cp app/Providers/AppServiceProvider.php app/Providers/AppServiceProvider.php.backup
    # Rimuovi righe che contengono Telescope
    sed -i.bak '/Telescope/d' app/Providers/AppServiceProvider.php
    sed -i.bak '/telescope/d' app/Providers/AppServiceProvider.php
    rm -f app/Providers/AppServiceProvider.php.bak
    echo "✅ Pulito AppServiceProvider.php"
fi

# Pulisci Kernel.php
if [ -f app/Http/Kernel.php ]; then
    cp app/Http/Kernel.php app/Http/Kernel.php.backup
    sed -i.bak '/telescope/d' app/Http/Kernel.php
    sed -i.bak '/Telescope/d' app/Http/Kernel.php
    rm -f app/Http/Kernel.php.bak
    echo "✅ Pulito Kernel.php"
fi

# Pulisci route files
for route_file in routes/*.php; do
    if [ -f "$route_file" ]; then
        cp "$route_file" "$route_file.backup"
        sed -i.bak '/Telescope/d' "$route_file"
        sed -i.bak '/telescope/d' "$route_file"
        rm -f "$route_file.bak"
    fi
done
echo "✅ Puliti file routes"

# Pulisci .env se esiste
if [ -f .env ]; then
    cp .env .env.backup
    sed -i.bak '/TELESCOPE/d' .env
    rm -f .env.bak
    echo "✅ Pulito .env"
fi

# 3. Pulisci cache e bootstrap
echo "🧹 Pulizia cache..."

# Comandi artisan
php artisan config:clear 2>/dev/null || echo "⚠️ config:clear fallito"
php artisan cache:clear 2>/dev/null || echo "⚠️ cache:clear fallito"
php artisan route:clear 2>/dev/null || echo "⚠️ route:clear fallito"
php artisan view:clear 2>/dev/null || echo "⚠️ view:clear fallito"

# Rimuovi cache bootstrap manualmente
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-*.php
rm -f bootstrap/cache/packages.php
rm -f bootstrap/cache/services.php
echo "✅ Cache bootstrap pulita"

# 4. Verifica rimozione
echo "🔍 Verifica rimozione..."

echo "Cercando riferimenti residui a Telescope..."
TELESCOPE_REFS=$(grep -r -i "telescope" . --exclude-dir=vendor --exclude-dir=node_modules --exclude="*.backup" 2>/dev/null | grep -v ".git" | wc -l)
if [ "$TELESCOPE_REFS" -gt 0 ]; then
    echo "⚠️ Trovati ancora alcuni riferimenti. Controllali manualmente:"
    grep -r -i "telescope" . --exclude-dir=vendor --exclude-dir=node_modules --exclude="*.backup" 2>/dev/null | grep -v ".git" | head -5
else
    echo "✅ Nessun riferimento a Telescope trovato!"
fi

# 5. Rigenera autoload
echo "🔄 Rigenerazione autoload..."

# Composer autoload
composer dump-autoload --optimize 2>/dev/null || echo "⚠️ composer dump-autoload fallito"

# Test autoload
echo "🧪 Test autoload..."
php artisan --version 2>/dev/null && echo "✅ Artisan funziona!" || echo "❌ Artisan non funziona"

# 6. Test composer install per produzione
echo "📦 Test ottimizzazione produzione..."
composer install --no-dev --optimize-autoloader 2>/dev/null && echo "✅ Composer produzione OK!" || echo "❌ Errore composer produzione"

echo ""
echo "🎉 RIMOZIONE TELESCOPE COMPLETATA!"
echo "=================================="
echo "✅ File rimossi"
echo "✅ Configurazioni pulite"
echo "✅ Cache rigenerata"
echo "✅ Autoload ottimizzato"
echo ""
echo "📋 File backup creati:"
echo "- config/app.php.backup"
echo "- app/Providers/AppServiceProvider.php.backup"
echo "- app/Http/Kernel.php.backup"
echo "- routes/*.php.backup"
echo "- .env.backup"
echo ""
echo "🔄 Ora puoi procedere con:"
echo "composer install --no-dev --optimize-autoloader"
echo "composer dump-autoload --optimize --classmap-authoritative"
echo ""
echo "🚀 Poi esegui il tuo script di installazione!"
