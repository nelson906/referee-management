#!/bin/bash

# ============================================================================
# SCRIPT EXPORT ALTERNATIVO DATABASE MYSQL
# Per quando MySQL Workbench non funziona
# ============================================================================

# Configurazione (MODIFICARE QUESTI VALORI)
DB_NAME="Sql1466239_4"
DB_USER="root"
DB_PASSWORD=root
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
DB_HOST=127.0.0.1
DB_PORT="8889"
BACKUP_DIR="/Users/iMac/Sites/referee-management/database-backups"
DATE=$(date +"%Y%m%d_%H%M%S")

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}EXPORT ALTERNATIVO DATABASE: ${DB_NAME}${NC}"
echo -e "${BLUE}============================================================================${NC}"

# Verifica se la directory di backup esiste
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${YELLOW}Creazione directory backup: ${BACKUP_DIR}${NC}"
    mkdir -p "$BACKUP_DIR"
fi

# Funzione per verificare la connessione al database
check_connection() {
    echo -e "${BLUE}Verifica connessione al database...${NC}"
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p -e "USE $DB_NAME; SELECT 'Connessione OK' as status;" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Connessione al database riuscita${NC}"
        return 0
    else
        echo -e "${RED}✗ Impossibile connettersi al database${NC}"
        return 1
    fi
}

# Funzione per export completo
export_full() {
    echo -e "${BLUE}Export completo del database...${NC}"
    OUTPUT_FILE="${BACKUP_DIR}/${DB_NAME}_full_${DATE}.sql"

    mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p \
        --single-transaction \
        --routines \
        --triggers \
        --hex-blob \
        --default-character-set=utf8 \
        --add-drop-database \
        --create-options \
        "$DB_NAME" > "$OUTPUT_FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Export completo creato: ${OUTPUT_FILE}${NC}"
        FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
        echo -e "${GREEN}  Dimensione file: ${FILE_SIZE}${NC}"
    else
        echo -e "${RED}✗ Errore durante l'export completo${NC}"
    fi
}

# Funzione per export solo struttura
export_structure() {
    echo -e "${BLUE}Export struttura database...${NC}"
    OUTPUT_FILE="${BACKUP_DIR}/${DB_NAME}_structure_${DATE}.sql"

    mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p \
        --no-data \
        --routines \
        --triggers \
        --default-character-set=utf8 \
        "$DB_NAME" > "$OUTPUT_FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Export struttura creato: ${OUTPUT_FILE}${NC}"
    else
        echo -e "${RED}✗ Errore durante l'export struttura${NC}"
    fi
}

# Funzione per export solo dati
export_data() {
    echo -e "${BLUE}Export dati database...${NC}"
    OUTPUT_FILE="${BACKUP_DIR}/${DB_NAME}_data_${DATE}.sql"

    mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p \
        --no-create-info \
        --single-transaction \
        --hex-blob \
        --default-character-set=utf8 \
        --skip-triggers \
        "$DB_NAME" > "$OUTPUT_FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Export dati creato: ${OUTPUT_FILE}${NC}"
        FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
        echo -e "${GREEN}  Dimensione file: ${FILE_SIZE}${NC}"
    else
        echo -e "${RED}✗ Errore durante l'export dati${NC}"
    fi
}

# Funzione per export tabelle specifiche
export_specific_tables() {
    echo -e "${BLUE}Export tabelle specifiche...${NC}"

    # Lista delle tabelle più importanti (modificare secondo necessità)
    IMPORTANT_TABLES=("arbitri" "gare_2025" "gare_2024" "gare_2023" "gare_2022")

    for table in "${IMPORTANT_TABLES[@]}"; do
        echo -e "${YELLOW}Export tabella: ${table}${NC}"
        OUTPUT_FILE="${BACKUP_DIR}/${DB_NAME}_${table}_${DATE}.sql"

        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p \
            --single-transaction \
            --hex-blob \
            --default-character-set=utf8 \
            "$DB_NAME" "$table" > "$OUTPUT_FILE" 2>/dev/null

        if [ $? -eq 0 ] && [ -s "$OUTPUT_FILE" ]; then
            echo -e "${GREEN}  ✓ ${table} esportata${NC}"
        else
            echo -e "${YELLOW}  ⚠ Tabella ${table} non trovata o vuota${NC}"
            rm -f "$OUTPUT_FILE"
        fi
    done
}

# Funzione per comprimere i backup
compress_backups() {
    echo -e "${BLUE}Compressione backup...${NC}"

    cd "$BACKUP_DIR"

    # Comprimi tutti i file SQL creati oggi
    SQL_FILES=$(find . -name "*${DATE}*.sql" -type f)

    if [ -n "$SQL_FILES" ]; then
        ARCHIVE_NAME="${DB_NAME}_backup_${DATE}.tar.gz"
        tar -czf "$ARCHIVE_NAME" *${DATE}*.sql

        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Backup compresso: ${ARCHIVE_NAME}${NC}"

            # Rimuovi i file non compressi
            rm -f *${DATE}*.sql

            ARCHIVE_SIZE=$(du -h "$ARCHIVE_NAME" | cut -f1)
            echo -e "${GREEN}  Dimensione archivio: ${ARCHIVE_SIZE}${NC}"
        else
            echo -e "${RED}✗ Errore durante la compressione${NC}"
        fi
    fi
}

# Funzione per mostrare informazioni database
show_db_info() {
    echo -e "${BLUE}Informazioni database:${NC}"

    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p -e "
        USE $DB_NAME;
        SELECT
            COUNT(*) as 'Numero Tabelle'
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME';

        SELECT
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Dimensione (MB)'
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME';

        SELECT
            TABLE_NAME as 'Tabella',
            table_rows as 'Righe (stimate)',
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Dimensione (MB)'
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME'
        AND table_rows > 0
        ORDER BY (data_length + index_length) DESC
        LIMIT 10;
    "
}

# Menu principale
show_menu() {
    echo ""
    echo -e "${YELLOW}Seleziona il tipo di export:${NC}"
    echo "1) Export completo (struttura + dati)"
    echo "2) Solo struttura"
    echo "3) Solo dati"
    echo "4) Tabelle specifiche"
    echo "5) Tutti gli export"
    echo "6) Mostra informazioni database"
    echo "7) Esci"
    echo ""
}

# Verifica connessione prima di iniziare
if ! check_connection; then
    echo -e "${RED}Impossibile procedere senza connessione al database${NC}"
    exit 1
fi

# Loop menu principale
while true; do
    show_menu
    read -p "Scelta (1-7): " choice

    case $choice in
        1)
            export_full
            compress_backups
            ;;
        2)
            export_structure
            ;;
        3)
            export_data
            compress_backups
            ;;
        4)
            export_specific_tables
            ;;
        5)
            echo -e "${BLUE}Esecuzione di tutti gli export...${NC}"
            export_full
            export_structure
            export_data
            export_specific_tables
            compress_backups
            ;;
        6)
            show_db_info
            ;;
        7)
            echo -e "${GREEN}Uscita...${NC}"
            break
            ;;
        *)
            echo -e "${RED}Scelta non valida${NC}"
            ;;
    esac

    echo ""
    read -p "Premi ENTER per continuare..."
done

echo -e "${BLUE}============================================================================${NC}"
echo -e "${GREEN}Export completato! File salvati in: ${BACKUP_DIR}${NC}"
echo -e "${BLUE}============================================================================${NC}"
