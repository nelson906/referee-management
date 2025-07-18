<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\RefereeLevelsHelper;

class RefereeLevelsHelperTest extends TestCase
{
    /**
     * Test che i valori canonici corrispondano agli ENUM database
     */
    public function test_canonical_levels_match_database_enum()
    {
        $canonicalLevels = RefereeLevelsHelper::getSelectOptions();

        // ✅ Verifica che contenga tutti i valori ENUM del database
        $expectedEnumValues = ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale'];

        foreach ($expectedEnumValues as $enumValue) {
            $this->assertArrayHasKey($enumValue, $canonicalLevels, "Missing ENUM value: {$enumValue}");
        }

        // ✅ Verifica i label corretti
        $this->assertEquals('Aspirante', $canonicalLevels['Aspirante']);
        $this->assertEquals('Primo Livello', $canonicalLevels['1_livello']);
        $this->assertEquals('Regionale', $canonicalLevels['Regionale']);
        $this->assertEquals('Nazionale', $canonicalLevels['Nazionale']);
        $this->assertEquals('Internazionale', $canonicalLevels['Internazionale']);
    }

    /**
     * Test normalizzazione di tutti i casi comuni
     */
    public function test_normalization_covers_all_common_cases()
    {
        // ✅ Test case aspirante → Aspirante (ENUM)
        $this->assertEquals('Aspirante', RefereeLevelsHelper::normalize('aspirante'));
        $this->assertEquals('Aspirante', RefereeLevelsHelper::normalize('ASPIRANTE'));
        $this->assertEquals('Aspirante', RefereeLevelsHelper::normalize('Aspirante')); // Already ENUM value
        $this->assertEquals('Aspirante', RefereeLevelsHelper::normalize('asp'));

        // ✅ Test case primo livello → 1_livello (ENUM)
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('primo_livello'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('PRIMO_LIVELLO'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('Primo_Livello'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('primo livello'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('Primo Livello'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('first_level'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('prim'));
        $this->assertEquals('1_livello', RefereeLevelsHelper::normalize('1_livello')); // Already ENUM value

        // ✅ Test case regionale → Regionale (ENUM)
        $this->assertEquals('Regionale', RefereeLevelsHelper::normalize('regionale'));
        $this->assertEquals('Regionale', RefereeLevelsHelper::normalize('REGIONALE'));
        $this->assertEquals('Regionale', RefereeLevelsHelper::normalize('Regionale')); // Already ENUM value
        $this->assertEquals('Regionale', RefereeLevelsHelper::normalize('reg'));

        // ✅ Test case nazionale → Nazionale (ENUM)
        $this->assertEquals('Nazionale', RefereeLevelsHelper::normalize('nazionale'));
        $this->assertEquals('Nazionale', RefereeLevelsHelper::normalize('NAZIONALE'));
        $this->assertEquals('Nazionale', RefereeLevelsHelper::normalize('Nazionale')); // Already ENUM value
        $this->assertEquals('Nazionale', RefereeLevelsHelper::normalize('naz'));

        // ✅ Test case internazionale → Internazionale (ENUM)
        $this->assertEquals('Internazionale', RefereeLevelsHelper::normalize('internazionale'));
        $this->assertEquals('Internazionale', RefereeLevelsHelper::normalize('INTERNAZIONALE'));
        $this->assertEquals('Internazionale', RefereeLevelsHelper::normalize('Internazionale')); // Already ENUM value
        $this->assertEquals('Internazionale', RefereeLevelsHelper::normalize('int'));
    }

    /**
     * Test validazione livelli
     */
    public function test_level_validation()
    {
        // ✅ Validi (ENUM values diretti)
        $this->assertTrue(RefereeLevelsHelper::isValid('Aspirante'));
        $this->assertTrue(RefereeLevelsHelper::isValid('1_livello'));
        $this->assertTrue(RefereeLevelsHelper::isValid('Regionale'));
        $this->assertTrue(RefereeLevelsHelper::isValid('Nazionale'));
        $this->assertTrue(RefereeLevelsHelper::isValid('Internazionale'));
        $this->assertTrue(RefereeLevelsHelper::isValid('Archivio'));

        // ✅ Validi (variants che si normalizzano a valori ENUM)
        $this->assertTrue(RefereeLevelsHelper::isValid('primo_livello')); // → 1_livello
        $this->assertTrue(RefereeLevelsHelper::isValid('aspirante')); // → Aspirante
        $this->assertTrue(RefereeLevelsHelper::isValid('regionale')); // → Regionale
        $this->assertTrue(RefereeLevelsHelper::isValid('nazionale')); // → Nazionale
        $this->assertTrue(RefereeLevelsHelper::isValid('internazionale')); // → Internazionale

        // ✅ Non validi
        $this->assertFalse(RefereeLevelsHelper::isValid('invalid_level'));
        $this->assertFalse(RefereeLevelsHelper::isValid(''));
        $this->assertFalse(RefereeLevelsHelper::isValid(null));
    }

    /**
     * Test getLabel method
     */
    public function test_get_label_method()
    {
        // ✅ Test con valori ENUM diretti
        $this->assertEquals('Aspirante', RefereeLevelsHelper::getLabel('Aspirante'));
        $this->assertEquals('Primo Livello', RefereeLevelsHelper::getLabel('1_livello'));
        $this->assertEquals('Regionale', RefereeLevelsHelper::getLabel('Regionale'));
        $this->assertEquals('Nazionale', RefereeLevelsHelper::getLabel('Nazionale'));
        $this->assertEquals('Internazionale', RefereeLevelsHelper::getLabel('Internazionale'));

        // ✅ Test con valori che devono essere normalizzati
        $this->assertEquals('Primo Livello', RefereeLevelsHelper::getLabel('primo_livello')); // normalize to 1_livello → label
        $this->assertEquals('Aspirante', RefereeLevelsHelper::getLabel('aspirante')); // normalize to Aspirante → label
        $this->assertEquals('Regionale', RefereeLevelsHelper::getLabel('regionale')); // normalize to Regionale → label

        // ✅ Test casi edge
        $this->assertEquals('Non specificato', RefereeLevelsHelper::getLabel(''));
        $this->assertEquals('Non specificato', RefereeLevelsHelper::getLabel(null));
        $this->assertEquals('Invalid_level', RefereeLevelsHelper::getLabel('invalid_level')); // ✅ ucfirst fallback
    }

    /**
     * Test permessi tornei nazionali
     */
    public function test_national_tournament_access()
    {
        // ✅ Possono accedere a tornei nazionali (valori ENUM)
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('Nazionale'));
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('Internazionale'));

        // ✅ Possono accedere (valori che si normalizzano)
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('nazionale')); // → Nazionale
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('internazionale')); // → Internazionale

        // ✅ NON possono accedere a tornei nazionali (valori ENUM)
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('Aspirante'));
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('1_livello'));
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('Regionale'));

        // ✅ NON possono accedere (valori che si normalizzano)
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('aspirante')); // → Aspirante
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('primo_livello')); // → 1_livello
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('regionale')); // → Regionale
    }

    /**
     * Test debug method
     */
    public function test_debug_method()
    {
        $debug = RefereeLevelsHelper::debugLevel('primo_livello');

        $this->assertEquals('primo_livello', $debug['original']);
        $this->assertEquals('1_livello', $debug['normalized']); // Should normalize to ENUM value
        $this->assertEquals('Primo Livello', $debug['label']); // Should get user-friendly label
        $this->assertTrue($debug['is_valid']); // Should be valid because it normalizes to valid ENUM
        $this->assertFalse($debug['can_access_national']); // 1_livello can't access national
        $this->assertFalse($debug['found_in_enum']); // 'primo_livello' not in enum (only ENUM values are)
        $this->assertTrue($debug['found_in_variants']); // 'primo_livello' is in variants map
        $this->assertIsArray($debug['database_enum_values']);
        $this->assertContains('1_livello', $debug['database_enum_values']); // Should contain ENUM values
    }

    /**
     * Test che le funzioni helper globali funzionino
     */
    public function test_global_helper_functions()
    {
        // ✅ Test che le funzioni siano caricate
        $this->assertTrue(function_exists('referee_levels'), 'referee_levels() function not loaded');
        $this->assertTrue(function_exists('normalize_referee_level'), 'normalize_referee_level() function not loaded');
        $this->assertTrue(function_exists('referee_level_label'), 'referee_level_label() function not loaded');

        // ✅ Test funzionamento con valori ENUM corretti
        $levels = referee_levels();
        $this->assertIsArray($levels);
        $this->assertArrayHasKey('1_livello', $levels); // ENUM key
        $this->assertArrayHasKey('Aspirante', $levels); // ENUM key
        $this->assertEquals('Primo Livello', $levels['1_livello']); // User-friendly label

        $normalized = normalize_referee_level('primo_livello');
        $this->assertEquals('1_livello', $normalized); // Should normalize to ENUM value

        $label = referee_level_label('1_livello');
        $this->assertEquals('Primo Livello', $label); // Should get user-friendly label

        // ✅ Test con normalizzazione
        $labelFromNormalized = referee_level_label('primo_livello');
        $this->assertEquals('Primo Livello', $labelFromNormalized); // Should normalize first, then get label
    }

    /**
     * Test inclusione Archivio
     */
    public function test_archive_level_inclusion()
    {
        // ✅ Senza archivio (default)
        $levelsWithoutArchive = RefereeLevelsHelper::getSelectOptions(false);
        $this->assertArrayNotHasKey('Archivio', $levelsWithoutArchive); // Use ENUM key

        // ✅ Verifica che gli altri livelli ci siano
        $this->assertArrayHasKey('Aspirante', $levelsWithoutArchive);
        $this->assertArrayHasKey('1_livello', $levelsWithoutArchive);

        // ✅ Con archivio
        $levelsWithArchive = RefereeLevelsHelper::getSelectOptions(true);
        $this->assertArrayHasKey('Archivio', $levelsWithArchive); // Use ENUM key
        $this->assertEquals('Archivio', $levelsWithArchive['Archivio']);

        // ✅ Verifica che anche gli altri livelli ci siano
        $this->assertArrayHasKey('Aspirante', $levelsWithArchive);
        $this->assertArrayHasKey('1_livello', $levelsWithArchive);
    }
}
