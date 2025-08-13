-- ============================================================================
-- SCRIPT CORREZIONE NOMI DATABASE Sql1466239_4
-- Tabelle: gare_2015, gare_2016, ..., gare_2025
-- Colonne: Comitato, TD, Arbitri, Osservatori
-- Trasformazione: "Cognome-Nome" -> "Nome-Cognome" (separati da virgola se ci sono spazi)
-- Verifica contro tabella users del database referee-management
-- ============================================================================

USE Sql1466239_4;

-- ============================================================================
-- PARTE 1: INVESTIGAZIONE E ANALISI
-- ============================================================================

-- 1.1 Verifica esistenza tabelle gare
SELECT 
    table_name,
    table_rows,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
FROM information_schema.tables 
WHERE table_schema = 'Sql1466239_4' 
AND table_name LIKE 'gare_%'
ORDER BY table_name;

-- 1.2 Verifica colonne nelle tabelle gare (esempio con gare_2025)
DESCRIBE gare_2025;

-- 1.3 Analisi nomi nelle colonne target (esempio con gare_2025)
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

-- 1.4 Esempi di nomi da correggere
SELECT 
    id,
    Comitato,
    CASE 
        WHEN Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %' THEN 
            CONCAT(
                TRIM(SUBSTRING_INDEX(Comitato, '-', -1)), 
                ', ', 
                TRIM(SUBSTRING_INDEX(Comitato, '-', 1))
            )
        ELSE Comitato
    END AS Comitato_corretto,
    TD,
    CASE 
        WHEN TD LIKE '%-%' AND TD NOT LIKE '%, %' THEN 
            CONCAT(
                TRIM(SUBSTRING_INDEX(TD, '-', -1)), 
                ', ', 
                TRIM(SUBSTRING_INDEX(TD, '-', 1))
            )
        ELSE TD
    END AS TD_corretto
FROM gare_2025 
WHERE (Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %')
   OR (TD LIKE '%-%' AND TD NOT LIKE '%, %')
LIMIT 10;

-- ============================================================================
-- PARTE 2: FUNZIONE PER TRASFORMARE NOMI
-- ============================================================================

DELIMITER //

-- Funzione per trasformare "Cognome-Nome" in "Nome, Cognome"
CREATE FUNCTION IF NOT EXISTS transform_name(input_name VARCHAR(500))
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
    'Rossi-Mario' AS originale,
    transform_name('Rossi-Mario') AS trasformato
UNION ALL
SELECT 
    'Mario, Rossi' AS originale,
    transform_name('Mario, Rossi') AS trasformato
UNION ALL
SELECT 
    'Mario Rossi' AS originale,
    transform_name('Mario Rossi') AS trasformato;

-- ============================================================================
-- PARTE 3: BACKUP DELLE TABELLE
-- ============================================================================

-- Creare backup per ogni anno (esempio 2025)
CREATE TABLE IF NOT EXISTS gare_2025_backup AS 
SELECT id, Comitato, TD, Arbitri, Osservatori, created_at, updated_at 
FROM gare_2025;

-- ============================================================================
-- PARTE 4: VERIFICA CONTRO UTENTI REALI
-- ============================================================================

-- Funzione per verificare se un nome esiste nella tabella users del database referee-management
DELIMITER //

CREATE FUNCTION IF NOT EXISTS verify_user_exists(full_name VARCHAR(500))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE user_count INT DEFAULT 0;
    
    -- Se il nome è vuoto, ritorna false
    IF full_name IS NULL OR TRIM(full_name) = '' THEN
        RETURN FALSE;
    END IF;
    
    -- Verifica se esiste un utente con quel nome nel database referee-management
    -- NOTA: Sostituire con la connessione corretta al database referee-management
    SELECT COUNT(*) INTO user_count
    FROM `referee-management`.users
    WHERE UPPER(TRIM(name)) = UPPER(TRIM(full_name))
       OR UPPER(TRIM(name)) = UPPER(TRIM(REPLACE(full_name, ', ', ' ')));
    
    RETURN user_count > 0;
END//

DELIMITER ;

-- ============================================================================
-- PARTE 5: SCRIPT DI CORREZIONE CON VERIFICA
-- ============================================================================

-- 5.1 Analisi con verifica utenti (esempio gare_2025)
SELECT 
    id,
    'Comitato' as tipo_campo,
    Comitato as valore_originale,
    transform_name(Comitato) as valore_trasformato,
    verify_user_exists(transform_name(Comitato)) as utente_esiste
FROM gare_2025 
WHERE Comitato IS NOT NULL 
AND Comitato != ''
AND Comitato LIKE '%-%' 
AND Comitato NOT LIKE '%, %'

UNION ALL

SELECT 
    id,
    'TD' as tipo_campo,
    TD as valore_originale,
    transform_name(TD) as valore_trasformato,
    verify_user_exists(transform_name(TD)) as utente_esiste
FROM gare_2025 
WHERE TD IS NOT NULL 
AND TD != ''
AND TD LIKE '%-%' 
AND TD NOT LIKE '%, %'

UNION ALL

SELECT 
    id,
    'Arbitri' as tipo_campo,
    Arbitri as valore_originale,
    transform_name(Arbitri) as valore_trasformato,
    verify_user_exists(transform_name(Arbitri)) as utente_esiste
FROM gare_2025 
WHERE Arbitri IS NOT NULL 
AND Arbitri != ''
AND Arbitri LIKE '%-%' 
AND Arbitri NOT LIKE '%, %'

UNION ALL

SELECT 
    id,
    'Osservatori' as tipo_campo,
    Osservatori as valore_originale,
    transform_name(Osservatori) as valore_trasformato,
    verify_user_exists(transform_name(Osservatori)) as utente_esiste
FROM gare_2025 
WHERE Osservatori IS NOT NULL 
AND Osservatori != ''
AND Osservatori LIKE '%-%' 
AND Osservatori NOT LIKE '%, %'

ORDER BY id, tipo_campo
LIMIT 50;

-- ============================================================================
-- PARTE 6: ESECUZIONE CORREZIONI (DECOMMENTARE SOLO DOPO VERIFICA!)
-- ============================================================================

/*
-- ATTENZIONE: ESEGUIRE SOLO DOPO AVER VERIFICATO I RISULTATI!

-- 6.1 Correzione gare_2025
UPDATE gare_2025 
SET 
    Comitato = transform_name(Comitato),
    updated_at = CURRENT_TIMESTAMP
WHERE Comitato IS NOT NULL 
AND Comitato LIKE '%-%' 
AND Comitato NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, '-', 1))) > 0;

UPDATE gare_2025 
SET 
    TD = transform_name(TD),
    updated_at = CURRENT_TIMESTAMP
WHERE TD IS NOT NULL 
AND TD LIKE '%-%' 
AND TD NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(TD, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(TD, '-', 1))) > 0;

UPDATE gare_2025 
SET 
    Arbitri = transform_name(Arbitri),
    updated_at = CURRENT_TIMESTAMP
WHERE Arbitri IS NOT NULL 
AND Arbitri LIKE '%-%' 
AND Arbitri NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, '-', 1))) > 0;

UPDATE gare_2025 
SET 
    Osservatori = transform_name(Osservatori),
    updated_at = CURRENT_TIMESTAMP
WHERE Osservatori IS NOT NULL 
AND Osservatori LIKE '%-%' 
AND Osservatori NOT LIKE '%, %'
AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, '-', -1))) > 0
AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, '-', 1))) > 0;
*/

-- ============================================================================
-- PARTE 7: SCRIPT COMPLETO PER TUTTI GLI ANNI
-- ============================================================================

-- Procedura per applicare le correzioni a tutti gli anni
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS fix_all_gare_names()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE table_name VARCHAR(64);
    DECLARE sql_stmt TEXT;
    
    -- Cursor per tutte le tabelle gare_YYYY
    DECLARE table_cursor CURSOR FOR 
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'Sql1466239_4' 
        AND table_name LIKE 'gare_%' 
        AND table_name REGEXP 'gare_[0-9]{4}$'
        ORDER BY table_name;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN table_cursor;
    
    table_loop: LOOP
        FETCH table_cursor INTO table_name;
        IF done THEN
            LEAVE table_loop;
        END IF;
        
        -- Backup
        SET sql_stmt = CONCAT('CREATE TABLE IF NOT EXISTS ', table_name, '_backup AS SELECT id, Comitato, TD, Arbitri, Osservatori, created_at, updated_at FROM ', table_name);
        SET @sql = sql_stmt;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Correzione Comitato
        SET sql_stmt = CONCAT('UPDATE ', table_name, ' SET Comitato = transform_name(Comitato), updated_at = CURRENT_TIMESTAMP WHERE Comitato IS NOT NULL AND Comitato LIKE "%-%" AND Comitato NOT LIKE "%, %" AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, "-", -1))) > 0 AND LENGTH(TRIM(SUBSTRING_INDEX(Comitato, "-", 1))) > 0');
        SET @sql = sql_stmt;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Correzione TD
        SET sql_stmt = CONCAT('UPDATE ', table_name, ' SET TD = transform_name(TD), updated_at = CURRENT_TIMESTAMP WHERE TD IS NOT NULL AND TD LIKE "%-%" AND TD NOT LIKE "%, %" AND LENGTH(TRIM(SUBSTRING_INDEX(TD, "-", -1))) > 0 AND LENGTH(TRIM(SUBSTRING_INDEX(TD, "-", 1))) > 0');
        SET @sql = sql_stmt;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Correzione Arbitri
        SET sql_stmt = CONCAT('UPDATE ', table_name, ' SET Arbitri = transform_name(Arbitri), updated_at = CURRENT_TIMESTAMP WHERE Arbitri IS NOT NULL AND Arbitri LIKE "%-%" AND Arbitri NOT LIKE "%, %" AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, "-", -1))) > 0 AND LENGTH(TRIM(SUBSTRING_INDEX(Arbitri, "-", 1))) > 0');
        SET @sql = sql_stmt;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Correzione Osservatori
        SET sql_stmt = CONCAT('UPDATE ', table_name, ' SET Osservatori = transform_name(Osservatori), updated_at = CURRENT_TIMESTAMP WHERE Osservatori IS NOT NULL AND Osservatori LIKE "%-%" AND Osservatori NOT LIKE "%, %" AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, "-", -1))) > 0 AND LENGTH(TRIM(SUBSTRING_INDEX(Osservatori, "-", 1))) > 0');
        SET @sql = sql_stmt;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        SELECT CONCAT('✅ Completato: ', table_name) as status;
        
    END LOOP;
    
    CLOSE table_cursor;
    
    SELECT '🎉 Correzione completata per tutte le tabelle gare!' as final_status;
END//

DELIMITER ;

-- ============================================================================
-- PARTE 8: VERIFICA FINALE
-- ============================================================================

-- Conta i risultati dopo la correzione
SELECT 
    table_name,
    SUM(CASE WHEN Comitato LIKE '%-%' AND Comitato NOT LIKE '%, %' THEN 1 ELSE 0 END) +
    SUM(CASE WHEN TD LIKE '%-%' AND TD NOT LIKE '%, %' THEN 1 ELSE 0 END) +
    SUM(CASE WHEN Arbitri LIKE '%-%' AND Arbitri NOT LIKE '%, %' THEN 1 ELSE 0 END) +
    SUM(CASE WHEN Osservatori LIKE '%-%' AND Osservatori NOT LIKE '%, %' THEN 1 ELSE 0 END) AS ancora_da_correggere
FROM information_schema.tables t
WHERE t.table_schema = 'Sql1466239_4' 
AND t.table_name LIKE 'gare_%'
ORDER BY table_name;

-- ============================================================================
-- ISTRUZIONI D'USO:
-- ============================================================================
-- 1. Eseguire PARTE 1 per analizzare i dati
-- 2. Eseguire PARTE 2 per creare le funzioni
-- 3. Eseguire PARTE 3 per creare i backup
-- 4. Eseguire PARTE 5 per verificare i risultati
-- 5. Se tutto è OK, decommentare e eseguire PARTE 6 per correggere
-- 6. Oppure usare la procedura PARTE 7: CALL fix_all_gare_names();
-- 7. Eseguire PARTE 8 per verificare i risultati finali
-- ============================================================================
