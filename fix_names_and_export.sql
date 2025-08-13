-- ============================================================================
-- SCRIPT COMPLETO: INVESTIGAZIONE PROBLEMI EXPORT MYSQL + FIX NOMI
-- Database: Sql1466239_4
-- Creato: 2025-08-07
-- ============================================================================

-- PARTE 1: INVESTIGAZIONE PROBLEMI ESPORTAZIONE
-- ============================================================================

-- 1.1 Verificare l'esistenza del database
SHOW DATABASES LIKE 'Sql1466239_4';

-- 1.2 Usare il database
USE Sql1466239_4;

-- 1.3 Verificare le tabelle nel database
SHOW TABLES;

-- 1.4 Verificare i privilegi dell'utente corrente
SHOW GRANTS FOR CURRENT_USER();

-- 1.5 Verificare la dimensione del database
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
    COUNT(*) AS 'Tables_Count'
FROM information_schema.tables 
WHERE table_schema = 'Sql1466239_4'
GROUP BY table_schema;

-- 1.6 Verificare tabelle più grandi (possibili cause di timeout)
SELECT 
    TABLE_NAME,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
    table_rows AS 'Estimated_Rows'
FROM information_schema.tables 
WHERE table_schema = 'Sql1466239_4'
ORDER BY (data_length + index_length) DESC
LIMIT 10;

-- 1.7 Verificare vincoli e foreign keys che potrebbero causare problemi
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    REFERENCED_TABLE_NAME
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu 
    ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
    AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
WHERE tc.TABLE_SCHEMA = 'Sql1466239_4'
ORDER BY TABLE_NAME, CONSTRAINT_TYPE;

-- 1.8 Verificare la configurazione MySQL che potrebbe influire sull'esportazione
SHOW VARIABLES LIKE 'max_allowed_packet';
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
SHOW VARIABLES LIKE 'wait_timeout';
SHOW VARIABLES LIKE 'interactive_timeout';
SHOW VARIABLES LIKE 'max_connections';

-- 1.9 Verificare se ci sono processi bloccanti
SHOW PROCESSLIST;

-- 1.10 Verificare lo stato delle tabelle (possibili corruzioni)
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_ROWS,
    AVG_ROW_LENGTH,
    DATA_LENGTH,
    INDEX_LENGTH,
    TABLE_COLLATION
FROM information_schema.tables 
WHERE table_schema = 'Sql1466239_4'
ORDER BY TABLE_NAME;

-- ============================================================================
-- PARTE 2: ANALISI E FIX DEI NOMI
-- ============================================================================

-- 2.1 Prima di tutto, identificare le tabelle che contengono nomi
-- Cerchiamo colonne che potrebbero contenere nomi di persone
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'Sql1466239_4'
AND (
    COLUMN_NAME LIKE '%name%' OR
    COLUMN_NAME LIKE '%nome%' OR
    COLUMN_NAME LIKE '%cognome%' OR
    COLUMN_NAME LIKE '%first%' OR
    COLUMN_NAME LIKE '%last%' OR
    COLUMN_NAME LIKE '%full%'
)
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 2.2 Assumendo che ci sia una tabella 'users' con campo 'name'
-- e una tabella di riferimento (es. 'reference_names' o simile)
-- Vediamo esempi di nomi nella tabella principale

-- IMPORTANTE: Sostituire 'users' e 'name' con i nomi effettivi delle tabelle/colonne
SELECT 
    id,
    name,
    CASE 
        WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 
            CONCAT(
                TRIM(SUBSTRING_INDEX(name, '-', -1)), 
                ' ', 
                TRIM(SUBSTRING_INDEX(name, '-', 1))
            )
        ELSE name
    END AS nome_corretto,
    CASE 
        WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 'DA_CORREGGERE'
        ELSE 'OK'
    END AS stato
FROM users 
WHERE name IS NOT NULL
ORDER BY stato DESC, name
LIMIT 20;

-- 2.3 Contare quanti nomi potrebbero aver bisogno di correzione
SELECT 
    COUNT(*) as totale_record,
    SUM(CASE WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 1 ELSE 0 END) as da_correggere,
    SUM(CASE WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 0 ELSE 1 END) as gia_corretti
FROM users 
WHERE name IS NOT NULL;

-- ============================================================================
-- PARTE 3: SCRIPT DI CORREZIONE DEI NOMI
-- ============================================================================

-- 3.1 BACKUP PRIMA DELLA MODIFICA (IMPORTANTE!)
-- Creare una tabella di backup
CREATE TABLE IF NOT EXISTS users_backup_names AS 
SELECT id, name, created_at, updated_at 
FROM users;

-- 3.2 Script di correzione (DA ESEGUIRE CON ATTENZIONE!)
-- Questo script trasforma "Cognome-Nome" in "Nome Cognome"

-- Versione SAFE - Solo visualizzazione delle modifiche
SELECT 
    id,
    name AS nome_originale,
    CASE 
        WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 
            CONCAT(
                TRIM(SUBSTRING_INDEX(name, '-', -1)), 
                ' ', 
                TRIM(SUBSTRING_INDEX(name, '-', 1))
            )
        ELSE name
    END AS nome_nuovo,
    'UPDATE users SET name = "' + 
    CASE 
        WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 
            CONCAT(
                TRIM(SUBSTRING_INDEX(name, '-', -1)), 
                ' ', 
                TRIM(SUBSTRING_INDEX(name, '-', 1))
            )
        ELSE name
    END + '" WHERE id = ' + CAST(id AS CHAR) + ';' AS query_update
FROM users 
WHERE name LIKE '%-%' AND name NOT LIKE '% %'
ORDER BY name;

-- 3.3 ESECUZIONE EFFETTIVA (DECOMMENTARE SOLO DOPO VERIFICA!)
/*
UPDATE users 
SET name = CONCAT(
    TRIM(SUBSTRING_INDEX(name, '-', -1)), 
    ' ', 
    TRIM(SUBSTRING_INDEX(name, '-', 1))
),
updated_at = CURRENT_TIMESTAMP
WHERE name LIKE '%-%' 
AND name NOT LIKE '% %'
AND LENGTH(TRIM(SUBSTRING_INDEX(name, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(name, '-', 1))) > 0;
*/

-- 3.4 Verifica dopo la modifica
SELECT 
    COUNT(*) as totale_dopo_modifica,
    SUM(CASE WHEN name LIKE '%-%' AND name NOT LIKE '% %' THEN 1 ELSE 0 END) as ancora_da_correggere,
    SUM(CASE WHEN name LIKE '% %' THEN 1 ELSE 0 END) as nomi_con_spazio
FROM users 
WHERE name IS NOT NULL;

-- ============================================================================
-- PARTE 4: SCRIPT AVANZATO CON TABELLA DI RIFERIMENTO
-- ============================================================================

-- 4.1 Se esiste una tabella di riferimento per confrontare i nomi
-- (sostituire 'reference_table' con il nome effettivo)
/*
SELECT 
    u.id,
    u.name as nome_utente,
    r.correct_name as nome_riferimento,
    CASE 
        WHEN u.name != r.correct_name THEN 'DIFFERENTE'
        ELSE 'UGUALE'
    END as confronto
FROM users u
LEFT JOIN reference_table r ON (
    -- Condizione di join da personalizzare in base alla logica
    u.id = r.user_id 
    OR UPPER(TRIM(u.name)) = UPPER(TRIM(r.correct_name))
    OR UPPER(REPLACE(u.name, '-', ' ')) = UPPER(TRIM(r.correct_name))
)
WHERE u.name IS NOT NULL
ORDER BY confronto DESC;
*/

-- ============================================================================
-- PARTE 5: EXPORT SICURO ALTERNATIVO
-- ============================================================================

-- 5.1 Script per export manuale in caso di problemi con MySQL Workbench
-- Questo può essere eseguito da command line

-- Per esportare solo la struttura:
-- mysqldump -u username -p --no-data Sql1466239_4 > structure_only.sql

-- Per esportare solo i dati:
-- mysqldump -u username -p --no-create-info Sql1466239_4 > data_only.sql

-- Per esportare tutto con opzioni specifiche:
-- mysqldump -u username -p --single-transaction --routines --triggers Sql1466239_4 > full_backup.sql

-- 5.2 Per tabelle molto grandi, export tabella per tabella:
-- mysqldump -u username -p Sql1466239_4 table_name > table_name_backup.sql

-- ============================================================================
-- PARTE 6: PULIZIA E MONITORAGGIO
-- ============================================================================

-- 6.1 Verificare l'integrità del database dopo le modifiche
CHECK TABLE users;

-- 6.2 Ottimizzare le tabelle se necessario
-- OPTIMIZE TABLE users;

-- 6.3 Log delle modifiche effettuate
CREATE TABLE IF NOT EXISTS name_corrections_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    old_name VARCHAR(255),
    new_name VARCHAR(255),
    correction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- NOTE IMPORTANTI:
-- ============================================================================
-- 1. ESEGUIRE SEMPRE UN BACKUP COMPLETO PRIMA DI QUALSIASI MODIFICA
-- 2. Testare prima su una copia del database
-- 3. Verificare attentamente i risultati prima dell'esecuzione effettiva
-- 4. Per problemi di export, verificare versioni MySQL Workbench e Server
-- 5. Considerare export via command line se MySQL Workbench non funziona
-- ============================================================================
