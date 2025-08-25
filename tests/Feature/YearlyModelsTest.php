<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\YearlyAssignment;
use App\Models\YearlyTournament;
use App\Services\YearService;

class YearlyModelsTest extends TestCase
{
    /** @test */
    public function yearly_assignment_trait_works()
    {
        $year = 2024;

        // Test che non ci siano errori di trait
        $assignment = new YearlyAssignment();
        $assignment->setYearlyTable($year);

        $this->assertEquals("assignments_{$year}", $assignment->getTable());
        $this->assertEquals($year, $assignment->getCurrentYear());
    }

    /** @test */
    public function yearly_tournament_trait_works()
    {
        $year = 2024;

        $tournament = new YearlyTournament();
        $tournament->setYearlyTable($year);

        $this->assertEquals("tournaments_{$year}", $tournament->getTable());
        $this->assertEquals($year, $tournament->getCurrentYear());
    }

    /** @test */
    public function forYear_method_works()
    {
        $assignment = YearlyAssignment::forYear(2024);
        $this->assertEquals('assignments_2024', $assignment->getTable());

        $tournament = YearlyTournament::forYear(2024);
        $this->assertEquals('tournaments_2024', $tournament->getTable());
    }
}
