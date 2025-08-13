#!/bin/bash

# ============================================================================
# SCRIPT CORREZIONE NOMI DATABASE Sql1466239_4 
# Tabelle: gare_2015 a gare_2025
# Colonne: Comitato, TD, Arbitri, Osservatori
# ============================================================================

# Configurazione MAMP
DB_NAME="Sql1466239_4"
DB_USER="root"
DB_HOST="localhost"
DB_PORT="8889"
SOCKET_PATH="/Applications/MAMP/tmp/mysql/mysql.sock"
REFERENCE_DB="referee-management"
DATE=$(date +"%Y%m%d_%H%M%S")

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}CORREZIONE NOMI DATABASE: ${DB_NAME}${NC}"
echo -e "${BLUE}Colonne: Comitato, TD, Arbitri, Osservatori${NC}"
echo -e "${BLUE}Trasformazione: Cognome-Nome -> Nome, Cognome${NC}"
echo -e "${BLUE}============================================================================${NC}"

# Funzione per verificare connessione
check_connection() {
    echo -e "${BLUE}Verifica connessione al database ${DB_NAME}...${NC}"
    echo -e "${YELLOW}Inserisci la password di MySQL quando richiesta${NC}"
    if mysql --protocol=TCP -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p -e "SHOW TABLES LIKE 'gare_%';" "$DB_NAME" > /dev/null; then
        echo -e "${GREEN}✓ Connessione riuscita${NC}"
        return 0
    else
        echo -e "${RED}✗ Impossibile connettersi al database${NC}"
        echo -e "${YELLOW}Assicurati che MAMP sia avviato e MySQL attivo sulla porta $DB_PORT${NC}"
        echo -e "${YELLOW}Verifica che il database $DB_NAME esista e sia accessibile${NC}"
        return 1
    fi
}

# Funzione per analizzare i dati
analyze_data() {
    echo -e "${BLUE}Analisi dati nelle tabelle gare...${NC}"
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" << 'EOF'
-- Mostra tutte le tabelle gare
SELECT 
    table_name,
    table_rows,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
FROM information_schema.tables 
WHERE table_schema = 'Sql1466239_4' 
AND table_name LIKE 'gare_%'
ORDER BY table_name;

-- Analisi esempio su gare_2025
SELECT 
    'Comitato' as colonna,
    COUNT(*) as totale_record,
    SUM(CASE WHEN Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %' THEN 1 ELSE 0 END) as da_correggere,
    SUM(CASE WHEN Comitato LIKE '%, %' THEN 1 ELSE 0 END) as gia_corretti
FROM gare_2025 
WHERE Comitato IS NOT NULL AND Comitato != ''

UNION ALL

SELECT 
    'TD' as colonna,
    COUNT(*) as totale_record,
    SUM(CASE WHEN TD LIKE '%-%' AND TD NOT LIKE '%, %' THEN 1 ELSE 0 END) as da_correggere,
    SUM(CASE WHEN TD LIKE '%, %' THEN 1 ELSE 0 END) as gia_corretti
FROM gare_2025 
WHERE TD IS NOT NULL AND TD != ''

UNION ALL

SELECT 
    'Arbitri' as colonna,
    COUNT(*) as totale_record,
    SUM(CASE WHEN Arbitri LIKE '%-%' AND Arbitri NOT LIKE '%, %' THEN 1 ELSE 0 END) as da_correggere,
    SUM(CASE WHEN Arbitri LIKE '%, %' THEN 1 ELSE 0 END) as gia_corretti
FROM gare_2025 
WHERE Arbitri IS NOT NULL AND Arbitri != ''

UNION ALL

SELECT 
    'Osservatori' as colonna,
    COUNT(*) as totale_record,
    SUM(CASE WHEN Osservatori LIKE '%-%' AND Osservatori NOT LIKE '%, %' THEN 1 ELSE 0 END) as da_correggere,
    SUM(CASE WHEN Osservatori LIKE '%, %' THEN 1 ELSE 0 END) as gia_corretti
FROM gare_2025 
WHERE Osservatori IS NOT NULL AND Osservatori != '';

-- Esempi di nomi da correggere
SELECT 
    'ESEMPI DA CORREGGERE' as info,
    '' as id,
    '' as originale, 
    '' as corretto;

SELECT 
    'Comitato' as info,
    id,
    Comitato as originale,
    CASE 
        WHEN Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %' THEN 
            CONCAT(TRIM(SUBSTRING_INDEX(Comitato, '-', -1)), ', ', TRIM(SUBSTRING_INDEX(Comitato, '-', 1)))
        ELSE Comitato
    END AS corretto
FROM gare_2025 
WHERE Comitato IS NOT NULL 
AND Comitato LIKE '%-%' 
AND Comitato NOT LIKE '%, %'
LIMIT 5;

EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Analisi completata${NC}"
    else
        echo -e "${RED}✗ Errore durante l'analisi${NC}"
    fi
}

# Funzione per creare funzioni MySQL
create_functions() {
    echo -e "${BLUE}Creazione funzioni MySQL...${NC}"
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" << 'EOF'
DELIMITER //

-- Funzione per trasformare "Cognome-Nome" in "Nome, Cognome"
DROP FUNCTION IF EXISTS transform_name//
CREATE FUNCTION transform_name(input_name VARCHAR(500))
RETURNS VARCHAR(500)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE result VARCHAR(500);
    DECLARE nome VARCHAR(250);
    DECLARE cognome VARCHAR(250);
    
    -- Se il nome è vuoto o null, ritorna com'è
    IF input_name IS NULL OR TRIM(input_name) = '' THEN
        RETURN input_name;
    END IF;
    
    -- Se il nome è già nel formato corretto (contiene virgola), ritorna com'è
    IF input_name LIKE '%, %' THEN
        RETURN input_name;
    END IF;
    
    -- Se il nome contiene trattino e non contiene virgola, trasformalo
    IF input_name LIKE '%-%' AND input_name NOT LIKE '%, %' THEN
        SET cognome = TRIM(SUBSTRING_INDEX(input_name, '-', 1));
        SET nome = TRIM(SUBSTRING_INDEX(input_name, '-', -1));
        
        -- Verifica che entrambe le parti non siano vuote
        IF LENGTH(nome) > 0 AND LENGTH(cognome) > 0 THEN
            SET result = CONCAT(nome, ', ', cognome);
        ELSE
            SET result = input_name;
        END IF;
    ELSE
        SET result = input_name;
    END IF;
    
    RETURN result;
END//

DELIMITER ;

-- Test della funzione
SELECT 
    'TEST FUNZIONE' as tipo,
    'Rossi-Mario' AS input_name,
    transform_name('Rossi-Mario') AS output_name
UNION ALL
SELECT 
    'TEST FUNZIONE' as tipo,
    'Mario, Rossi' AS input_name,
    transform_name('Mario, Rossi') AS output_name
UNION ALL
SELECT 
    'TEST FUNZIONE' as tipo,
    'Mario Rossi' AS input_name,
    transform_name('Mario Rossi') AS output_name;

EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Funzioni create con successo${NC}"
    else
        echo -e "${RED}✗ Errore nella creazione delle funzioni${NC}"
        return 1
    fi
}

# Funzione per creare backup
create_backup() {
    echo -e "${BLUE}Creazione backup tabelle gare...${NC}"
    
    # Ottieni lista tabelle gare
    TABLES=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p -sN "$DB_NAME" -e "
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' 
        AND table_name LIKE 'gare_%' 
        AND table_name REGEXP 'gare_[0-9]{4}$'
        ORDER BY table_name;
    ")
    
    for table in $TABLES; do
        echo -e "${YELLOW}Backup $table...${NC}"
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" -e "
            CREATE TABLE IF NOT EXISTS ${table}_backup_${DATE} AS 
            SELECT id, Comitato, TD, Arbitri, Osservatori, 
                   created_at, updated_at 
            FROM $table;
        "
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}  ✓ Backup $table completato${NC}"
        else
            echo -e "${RED}  ✗ Errore backup $table${NC}"
        fi
    done
}

# Funzione per mostrare anteprima modifiche
preview_changes() {
    echo -e "${BLUE}Anteprima modifiche (gare_2025)...${NC}"
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" << 'EOF'
SELECT 
    'ANTEPRIMA MODIFICHE - PRIMI 20 RECORD' as info,
    '' as id,
    '' as campo,
    '' as originale,
    '' as trasformato;

SELECT 
    id,
    'Comitato' as campo,
    Comitato as originale,
    transform_name(Comitato) as trasformato
FROM gare_2025 
WHERE Comitato IS NOT NULL 
AND Comitato LIKE '%-%' 
AND Comitato NOT LIKE '%, %'
LIMIT 10

UNION ALL

SELECT 
    id,
    'TD' as campo,
    TD as originale,
    transform_name(TD) as trasformato
FROM gare_2025 
WHERE TD IS NOT NULL 
AND TD LIKE '%-%' 
AND TD NOT LIKE '%, %'
LIMIT 10

ORDER BY id, campo;

EOF

    echo -e "${YELLOW}Continuare con le modifiche? [y/N]${NC}"
    read -r confirm
    if [[ ! $confirm =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Operazione annullata${NC}"
        return 1
    fi
}

# Funzione per applicare correzioni a una tabella specifica
fix_table() {
    local table_name="$1"
    echo -e "${YELLOW}Correzione $table_name...${NC}"
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" << EOF
-- Correzione Comitato
UPDATE $table_name 
SET Comitato = transform_name(Comitato),
    updated_at = CURRENT_TIMESTAMP
WHERE Comitato IS NOT NULL 
AND Comitato LIKE '%-%' 
AND Comitato NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, '-', 1))) > 0;

-- Correzione TD
UPDATE $table_name 
SET TD = transform_name(TD),
    updated_at = CURRENT_TIMESTAMP
WHERE TD IS NOT NULL 
AND TD LIKE '%-%' 
AND TD NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(TD, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(TD, '-', 1))) > 0;

-- Correzione Arbitri
UPDATE $table_name 
SET Arbitri = transform_name(Arbitri),
    updated_at = CURRENT_TIMESTAMP
WHERE Arbitri IS NOT NULL 
AND Arbitri LIKE '%-%' 
AND Arbitri NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, '-', 1))) > 0;

-- Correzione Osservatori
UPDATE $table_name 
SET Osservatori = transform_name(Osservatori),
    updated_at = CURRENT_TIMESTAMP
WHERE Osservatori IS NOT NULL 
AND Osservatori LIKE '%-%' 
AND Osservatori NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, '-', 1))) > 0;

SELECT ROW_COUNT() as records_affected;

EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}  ✓ $table_name corretta${NC}"
    else
        echo -e "${RED}  ✗ Errore nella correzione di $table_name${NC}"
    fi
}

# Funzione per applicare correzioni a tutte le tabelle
fix_all_tables() {
    echo -e "${BLUE}Applicazione correzioni a tutte le tabelle gare...${NC}"
    
    # Ottieni lista tabelle gare
    TABLES=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p -sN "$DB_NAME" -e "
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' 
        AND table_name LIKE 'gare_%' 
        AND table_name REGEXP 'gare_[0-9]{4}$'
        ORDER BY table_name;
    ")
    
    for table in $TABLES; do
        fix_table "$table"
    done
    
    echo -e "${GREEN}✓ Correzioni applicate a tutte le tabelle${NC}"
}

# Funzione per verificare risultati
verify_results() {
    echo -e "${BLUE}Verifica risultati finali...${NC}"
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p "$DB_NAME" << 'EOF'
-- Conta i nomi ancora da correggere per ogni tabella
SELECT 
    table_name,
    'Records ancora da correggere:' as info,
    (SELECT COUNT(*) FROM information_schema.tables t2 WHERE t2.table_name = t.table_name) as dummy
FROM information_schema.tables t
WHERE t.table_schema = 'Sql1466239_4' 
AND t.table_name LIKE 'gare_%'
AND t.table_name REGEXP 'gare_[0-9]{4}$'
ORDER BY table_name;

-- Esempio di verifica su gare_2025
SELECT 
    'VERIFICA FINALE - gare_2025' as info,
    COUNT(*) as totale_nomi,
    SUM(CASE WHEN 
        (Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %') OR
        (TD LIKE '%-%' AND TD NOT LIKE '%, %') OR
        (Arbitri LIKE '%-%' AND Arbitri NOT LIKE '%, %') OR
        (Osservatori LIKE '%-%' AND Osservatori NOT LIKE '%, %')
        THEN 1 ELSE 0 END) as ancora_da_correggere
FROM gare_2025;

-- Mostra alcuni esempi corretti
SELECT 
    'ESEMPI CORRETTI' as info,
    id,
    Comitato,
    TD
FROM gare_2025
WHERE (Comitato LIKE '%, %' OR TD LIKE '%, %')
LIMIT 5;

EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Verifica completata${NC}"
    else
        echo -e "${RED}✗ Errore durante la verifica${NC}"
    fi
}

# Menu principale
show_menu() {
    echo ""
    echo -e "${YELLOW}Seleziona un'operazione:${NC}"
    echo "1) Analizza i dati"
    echo "2) Crea funzioni MySQL"
    echo "3) Crea backup delle tabelle"
    echo "4) Mostra anteprima modifiche"
    echo "5) Applica correzioni a tutte le tabelle"
    echo "6) Verifica risultati"
    echo "7) Processo completo (backup + correzioni + verifica)"
    echo "8) Esci"
    echo ""
}

# Verifica connessione iniziale
if ! check_connection; then
    echo -e "${RED}Impossibile procedere senza connessione al database${NC}"
    exit 1
fi

# Loop menu principale
while true; do
    show_menu
    read -p "Scelta (1-8): " choice
    
    case $choice in
        1)
            analyze_data
            ;;
        2)
            create_functions
            ;;
        3)
            create_backup
            ;;
        4)
            create_functions
            preview_changes
            ;;
        5)
            if create_functions; then
                fix_all_tables
            fi
            ;;
        6)
            verify_results
            ;;
        7)
            echo -e "${BLUE}Processo completo...${NC}"
            if create_functions; then
                create_backup
                if preview_changes; then
                    fix_all_tables
                    verify_results
                fi
            fi
            ;;
        8)
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
echo -e "${GREEN}Operazioni completate!${NC}"
echo -e "${BLUE}============================================================================${NC}"
