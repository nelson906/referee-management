<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\User;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->referee(),
            'tournament_id' => Tournament::factory(),
            'is_confirmed' => $this->faker->boolean(80),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function forReferee(User $referee): Factory
    {
        return $this->state(function (array $attributes) use ($referee) {
            return [
                'user_id' => $referee->id,
            ];
        });
    }

    public function forTournament(Tournament $tournament): Factory
    {
        return $this->state(function (array $attributes) use ($tournament) {
            return [
                'tournament_id' => $tournament->id,
            ];
        });
    }
}
