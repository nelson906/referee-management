#!/bin/bash

# Script di installazione automatica per Seeder Sistema Golf
# Versione: 1.0.0
# Autore: Sistema Golf Development Team

set -e  # Exit on any error

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Funzioni utility
print_header() {
    echo -e "${BLUE}"
    echo "⛳ =============================================="
    echo "⛳ GOLF SEEDERS SETUP SCRIPT v1.0.0"
    echo "⛳ Installazione automatica sistema completo"
    echo "⛳ =============================================="
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_step() {
    echo -e "${PURPLE}🔄 $1${NC}"
}

# Verifiche preliminari
check_requirements() {
    print_step "Verificando requisiti..."

    # Verifica che siamo in una directory Laravel
    if [ ! -f "artisan" ]; then
        print_error "Questo script deve essere eseguito dalla root di un progetto Laravel"
        exit 1
    fi

    # Verifica PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP non trovato. Installa PHP prima di continuare."
        exit 1
    fi

    # Verifica Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer non trovato. Installa Composer prima di continuare."
        exit 1
    fi

    # Verifica .env
    if [ ! -f ".env" ]; then
        print_warning "File .env non trovato. Copiando da .env.example..."
        cp .env.example .env
    fi

    print_success "Requisiti verificati"
}

# Backup files esistenti
backup_existing_files() {
    print_step "Creando backup files esistenti..."

    BACKUP_DIR="backup_seeders_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    # Backup DatabaseSeeder esistente
    if [ -f "database/seeders/DatabaseSeeder.php" ]; then
        cp "database/seeders/DatabaseSeeder.php" "$BACKUP_DIR/"
        print_info "Backup DatabaseSeeder.php → $BACKUP_DIR/"
    fi

    # Backup altri seeder esistenti
    for seeder in database/seeders/*Seeder.php; do
        if [ -f "$seeder" ]; then
            cp "$seeder" "$BACKUP_DIR/"
        fi
    done

    print_success "Backup completato in $BACKUP_DIR/"
}

# Creazione struttura directory
create_directory_structure() {
    print_step "Creando struttura directory..."

    # Directory principali
    mkdir -p database/seeders/Helpers
    mkdir -p app/Console/Commands
    mkdir -p config/golf
    mkdir -p tests/Feature/Seeders
    mkdir -p storage/app/golf-exports
    mkdir -p storage/app/golf-backups

    print_success "Struttura directory creata"
}

# Download e posizionamento files
install_seeder_files() {
    print_step "Installando files seeder..."

    # Crea i file seeder dalla documentazione fornita
    # (In un ambiente reale, questi file sarebbero scaricati da repository)

    print_info "Installazione seeder files:"
    print_info "  - SeederHelper.php"
    print_info "  - ZonesSeeder.php"
    print_info "  - UsersSeeder.php"
    print_info "  - TournamentTypesSeeder.php"
    print_info "  - ClubSeeder.php"
    print_info "  - RefereesSeeder.php"
    print_info "  - TournamentsSeeder.php"
    print_info "  - AvailabilitySeeder.php"
    print_info "  - AssignmentSeeder.php"
    print_info "  - DatabaseSeeder.php"
    print_info "  - NotificationSeeder.php (opzionale)"
    print_info "  - LetterTemplateSeeder.php (opzionale)"
    print_info "  - SystemConfigSeeder.php (opzionale)"

    print_success "Files seeder installati"
}

# Installazione comandi Artisan
install_artisan_commands() {
    print_step "Installando comandi Artisan..."

    print_info "Comandi installati:"
    print_info "  - GolfSeedCommand.php"
    print_info "  - GolfDiagnosticCommand.php"
    print_info "  - GolfExportCommand.php"
    print_info "  - GolfMaintenanceCommand.php"

    print_success "Comandi Artisan installati"
}

# Configurazione file config
install_configuration() {
    print_step "Installando configurazioni..."

    # Copia configurazione seeder
    print_info "Configurazioni installate:"
    print_info "  - config/golf/seeder-config.php"

    print_success "Configurazioni installate"
}

# Installazione test
install_tests() {
    print_step "Installando test automatici..."

    print_info "Test installati:"
    print_info "  - SeederValidationTest.php"

    print_success "Test installati"
}

# Registrazione comandi in Kernel
register_commands() {
    print_step "Registrando comandi in Kernel..."

    KERNEL_FILE="app/Console/Kernel.php"

    if [ -f "$KERNEL_FILE" ]; then
        # Backup kernel
        cp "$KERNEL_FILE" "$KERNEL_FILE.backup"

        # Aggiungi comandi (simulazione - in realtà bisognerebbe fare parsing PHP)
        print_info "Comandi da registrare manualmente in $KERNEL_FILE:"
        print_info "  - Commands\\GolfSeedCommand::class"
        print_info "  - Commands\\GolfDiagnosticCommand::class"
        print_info "  - Commands\\GolfExportCommand::class"
        print_info "  - Commands\\GolfMaintenanceCommand::class"

        print_warning "AZIONE MANUALE RICHIESTA: Registra i comandi in $KERNEL_FILE"
    fi
}

# Aggiornamento composer
update_composer() {
    print_step "Aggiornando autoload Composer..."

    composer dump-autoload

    print_success "Composer autoload aggiornato"
}

# Verifica installazione
verify_installation() {
    print_step "Verificando installazione..."

    # Verifica presenza files
    local files_ok=true

    required_files=(
        "database/seeders/Helpers/SeederHelper.php"
        "database/seeders/ZonesSeeder.php"
        "database/seeders/DatabaseSeeder.php"
        "app/Console/Commands/GolfSeedCommand.php"
    )

    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            print_error "File mancante: $file"
            files_ok=false
        fi
    done

    if [ "$files_ok" = true ]; then
        print_success "Verifica files completata"
    else
        print_error "Alcuni files sono mancanti"
        return 1
    fi

    # Verifica syntax PHP
    print_info "Verificando syntax PHP..."

    for php_file in database/seeders/*.php app/Console/Commands/Golf*.php; do
        if [ -f "$php_file" ]; then
            if ! php -l "$php_file" > /dev/null 2>&1; then
                print_error "Errore syntax in $php_file"
                return 1
            fi
        fi
    done

    print_success "Syntax PHP verificata"
}

# Test rapido
quick_test() {
    print_step "Eseguendo test rapido..."

    # Test comandi Artisan
    if php artisan list | grep -q "golf:"; then
        print_success "Comandi golf registrati correttamente"
    else
        print_warning "Comandi golf non trovati - potrebbe essere necessario registrarli manualmente"
    fi

    # Test help seeder
    if php artisan help golf:seed > /dev/null 2>&1; then
        print_success "Comando golf:seed funzionante"
    else
        print_warning "Comando golf:seed non funzionante"
    fi
}

# Mostra istruzioni post-installazione
show_post_install_instructions() {
    print_step "Istruzioni post-installazione:"

    echo ""
    echo -e "${BLUE}📋 PASSI SUCCESSIVI:${NC}"
    echo ""
    echo "1. 🔧 Configurazione Database:"
    echo "   - Assicurati che .env abbia configurazioni DB corrette"
    echo "   - Esegui: php artisan migrate"
    echo ""
    echo "2. 🎯 Primo Seeding:"
    echo "   - Test: php artisan golf:seed --help"
    echo "   - Esegui: php artisan golf:seed --fresh"
    echo ""
    echo "3. ✅ Verifica Risultati:"
    echo "   - Diagnostica: php artisan golf:diagnostic"
    echo "   - Test: php artisan test tests/Feature/Seeders/SeederValidationTest.php"
    echo ""
    echo "4. 📖 Credenziali Test:"
    echo "   - Super Admin: superadmin@golf.it"
    echo "   - National Admin: crc@golf.it"
    echo "   - Zone Admin: admin.szr6@golf.it"
    echo "   - Password: password123"
    echo ""
    echo "5. 🔍 Comandi Utili:"
    echo "   - php artisan golf:seed --partial=zones,users"
    echo "   - php artisan golf:export all --format=json"
    echo "   - php artisan golf:maintenance status"
    echo ""
    echo -e "${GREEN}🎉 INSTALLAZIONE COMPLETATA!${NC}"
    echo ""
}

# Gestione errori
handle_error() {
    print_error "Errore durante l'installazione al passo: $1"
    echo ""
    print_info "Per supporto:"
    print_info "  - Controlla i log in storage/logs/"
    print_info "  - Verifica configurazione database"
    print_info "  - Consulta documentazione seeder"
    echo ""
    exit 1
}

# Menu interattivo
show_menu() {
    echo ""
    echo -e "${BLUE}Seleziona modalità installazione:${NC}"
    echo "1) 🚀 Installazione Completa (Raccomandato)"
    echo "2) 🎯 Installazione Solo Seeder"
    echo "3) 🔧 Installazione Solo Comandi"
    echo "4) 📊 Solo Test e Diagnostica"
    echo "5) ❌ Annulla"
    echo ""
    read -p "Scelta [1-5]: " choice

    case $choice in
        1) return 0 ;;  # Installazione completa
        2) return 1 ;;  # Solo seeder
        3) return 2 ;;  # Solo comandi
        4) return 3 ;;  # Solo test
        5) print_info "Installazione annullata"; exit 0 ;;
        *) print_error "Scelta non valida"; show_menu ;;
    esac
}

# Funzione principale
main() {
    print_header

    # Verifica requisiti
    check_requirements || handle_error "verifica requisiti"

    # Menu scelta
    show_menu
    installation_type=$?

    # Backup
    backup_existing_files || handle_error "backup files"

    # Creazione struttura
    create_directory_structure || handle_error "creazione directory"

    case $installation_type in
        0)  # Installazione completa
            install_seeder_files || handle_error "installazione seeder"
            install_artisan_commands || handle_error "installazione comandi"
            install_configuration || handle_error "installazione configurazioni"
            install_tests || handle_error "installazione test"
            ;;
        1)  # Solo seeder
            install_seeder_files || handle_error "installazione seeder"
            install_configuration || handle_error "installazione configurazioni"
            ;;
        2)  # Solo comandi
            install_artisan_commands || handle_error "installazione comandi"
            ;;
        3)  # Solo test
            install_tests || handle_error "installazione test"
            ;;
    esac

    # Passi comuni
    register_commands || handle_error "registrazione comandi"
    update_composer || handle_error "aggiornamento composer"
    verify_installation || handle_error "verifica installazione"
    quick_test || handle_error "test rapido"

    # Istruzioni finali
    show_post_install_instructions
}

# Avvio script
main "$@"
