<?php

namespace Database\Factories;

use App\Models\Referee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefereeFactory extends Factory
{
    protected $model = Referee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // ✅ Solo campi essenziali che probabilmente esistono
            'address' => $this->faker->streetAddress(),
            'postal_code' => $this->faker->postcode(),
            'bio' => $this->faker->paragraph(),
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function forUser(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }

    public function incomplete(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'profile_completed_at' => null,
                'bio' => null,
            ];
        });
    }
}

