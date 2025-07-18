<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // ✅ Password semplice per test
            'user_type' => 'player',
            'referee_code' => 'USR' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT), // ✅ Sempre presente
            'remember_token' => Str::random(10),
            'phone' => $this->faker->optional()->phoneNumber(),
            'city' => $this->faker->optional()->city(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * State for creating a referee user
     */
    public function referee(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 'referee',
                'level' => $this->faker->randomElement(['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale']),
                'referee_code' => 'ARB' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                'certified_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
                'zone_id' => Zone::factory(),
                'phone' => $this->faker->phoneNumber(),
                'password' => bcrypt('password'), // ✅ Password semplice
            ];
        });
    }

    /**
     * State for creating an admin user
     */
    public function admin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 'admin',
                'referee_code' => 'ADM' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT), // ✅ Admin code
                'zone_id' => Zone::factory(),
                'phone' => $this->faker->phoneNumber(),
                'password' => bcrypt('password'), // ✅ Password semplice
            ];
        });
    }

    /**
     * State for creating a national admin user
     */
    public function nationalAdmin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 'national_admin',
                'referee_code' => 'NAD' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT), // ✅ National admin code
                'zone_id' => null, // National admin no specific zone
                'phone' => $this->faker->phoneNumber(),
                'password' => bcrypt('password'), // ✅ Password semplice
            ];
        });
    }

    /**
     * State for creating a super admin user
     */
    public function superAdmin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 'super_admin',
                'referee_code' => 'SUP' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT), // ✅ Super admin code
                'zone_id' => null,
                'phone' => $this->faker->phoneNumber(),
                'password' => bcrypt('password'), // ✅ Password semplice
            ];
        });
    }

    /**
     * State for creating a club manager user
     */
    public function clubManager(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 'club_manager',
                'referee_code' => 'CLB' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT), // ✅ Club manager code
                'zone_id' => Zone::factory(),
                'phone' => $this->faker->phoneNumber(),
                'password' => bcrypt('password'), // ✅ Password semplice
            ];
        });
    }

    /**
     * State for inactive user
     */
    public function inactive(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * State for unverified email
     */
    public function unverified(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    /**
     * State for test with simple password
     */
    public function withSimplePassword(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            ];
        });
    }
}
