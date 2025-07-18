<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $endDate = clone $startDate;
        $endDate->modify('+' . $this->faker->numberBetween(1, 7) . ' days');

        return [
            'name' => $this->faker->sentence(3) . ' Tournament',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'zone_id' => Zone::factory(),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function assigned(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'assigned',
            ];
        });
    }
}

