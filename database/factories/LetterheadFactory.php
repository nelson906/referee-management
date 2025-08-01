<?php

namespace Database\Factories;

use App\Models\Letterhead;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Letterhead>
 */
class LetterheadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Letterhead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true) . ' Letterhead',
            'description' => $this->faker->sentence(),
            'zone_id' => null, // Will be set by states or explicitly
            'logo_path' => null,
            'header_text' => $this->generateHeaderText(),
            'footer_text' => $this->generateFooterText(),
            'contact_info' => $this->generateContactInfo(),
            'settings' => $this->generateSettings(),
            'is_active' => true,
            'is_default' => false,
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the letterhead is for a specific zone.
     */
    public function forZone(?int $zoneId = null): static
    {
        return $this->state(function (array $attributes) use ($zoneId) {
            return [
                'zone_id' => $zoneId ?? Zone::factory()->create()->id,
            ];
        });
    }

    /**
     * Indicate that the letterhead is global (no zone).
     */
    public function global(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'zone_id' => null,
                'title' => 'Letterhead Globale FIG',
                'header_text' => "FEDERAZIONE ITALIANA GOLF\nComitato Regionale Arbitri",
            ];
        });
    }

    /**
     * Indicate that the letterhead is the default for its zone.
     */
    public function default(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_default' => true,
                'is_active' => true,
            ];
        });
    }

    /**
     * Indicate that the letterhead is inactive.
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
                'is_default' => false,
            ];
        });
    }

    /**
     * Indicate that the letterhead has a logo.
     */
    public function withLogo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'logo_path' => 'letterheads/logos/sample_logo.png',
            ];
        });
    }

    /**
     * Create a complete letterhead with all features.
     */
    public function complete(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'title' => 'Letterhead Completa ' . $this->faker->randomElement(['Nord', 'Sud', 'Centro']),
                'description' => 'Letterhead completa con logo e tutte le informazioni',
                'logo_path' => 'letterheads/logos/complete_logo.png',
                'header_text' => $this->generateCompleteHeaderText(),
                'footer_text' => $this->generateCompleteFooterText(),
                'contact_info' => $this->generateCompleteContactInfo(),
                'settings' => $this->generateProfessionalSettings(),
            ];
        });
    }

    /**
     * Create a minimal letterhead.
     */
    public function minimal(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'title' => 'Letterhead Minimale',
                'description' => null,
                'logo_path' => null,
                'header_text' => 'Golf Referee Management System',
                'footer_text' => null,
                'contact_info' => [
                    'email' => 'info@golf.it',
                ],
                'settings' => [
                    'margins' => [
                        'top' => 15,
                        'bottom' => 15,
                        'left' => 20,
                        'right' => 20,
                    ],
                    'font' => [
                        'family' => 'Arial',
                        'size' => 10,
                        'color' => '#000000',
                    ],
                ],
            ];
        });
    }

    /**
     * Generate realistic header text.
     */
    private function generateHeaderText(): string
    {
        $organizations = [
            'Federazione Italiana Golf',
            'Comitato Regionale Golf',
            'Sezione Zonale Arbitri',
            'Delegazione Provinciale Golf',
        ];

        $departments = [
            'Settore Tecnico Arbitrale',
            'Commissione Regole',
            'Ufficio Competizioni',
            'Direzione Tecnica',
        ];

        return $this->faker->randomElement($organizations) . "\n" .
               $this->faker->randomElement($departments);
    }

    /**
     * Generate realistic footer text.
     */
    private function generateFooterText(): string
    {
        return "Questa comunicazione è stata generata automaticamente dal sistema di gestione arbitri.\n" .
               "Per informazioni contattare l'ufficio competente.";
    }

    /**
     * Generate complete header text.
     */
    private function generateCompleteHeaderText(): string
    {
        return "FEDERAZIONE ITALIANA GOLF\n" .
               "COMITATO REGIONALE {{zone_name}}\n" .
               "Settore Tecnico Arbitrale\n\n" .
               "Prot. n. _____ del {{date}}";
    }

    /**
     * Generate complete footer text.
     */
    private function generateCompleteFooterText(): string
    {
        return "Il presente documento è stato generato automaticamente.\n" .
               "La validità è subordinata alla firma digitale o timbro dell'ente.\n" .
               "Per informazioni: {{zone_name}} - Tel. {{contact_phone}}";
    }

    /**
     * Generate contact information.
     */
    private function generateContactInfo(): array
    {
        return [
            'address' => $this->faker->streetAddress() . ', ' .
                        $this->faker->postcode() . ' ' .
                        $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'website' => $this->faker->url(),
        ];
    }

    /**
     * Generate complete contact information.
     */
    private function generateCompleteContactInfo(): array
    {
        return [
            'address' => 'Via del Golf, 123 - 00100 Roma',
            'phone' => '+39 06 12345678',
            'email' => 'arbitri@federgolf.it',
            'website' => 'www.federgolf.it',
        ];
    }

    /**
     * Generate layout settings.
     */
    private function generateSettings(): array
    {
        return [
            'margins' => [
                'top' => $this->faker->numberBetween(15, 25),
                'bottom' => $this->faker->numberBetween(15, 25),
                'left' => $this->faker->numberBetween(20, 30),
                'right' => $this->faker->numberBetween(20, 30),
            ],
            'font' => [
                'family' => $this->faker->randomElement(['Arial', 'Times New Roman', 'Helvetica']),
                'size' => $this->faker->numberBetween(10, 12),
                'color' => $this->faker->randomElement(['#000000', '#333333', '#1a1a1a']),
            ],
        ];
    }

    /**
     * Generate professional settings.
     */
    private function generateProfessionalSettings(): array
    {
        return [
            'margins' => [
                'top' => 25,
                'bottom' => 20,
                'left' => 25,
                'right' => 25,
            ],
            'font' => [
                'family' => 'Times New Roman',
                'size' => 11,
                'color' => '#000000',
            ],
            'letterhead' => [
                'show_logo' => true,
                'logo_position' => 'left',
                'header_alignment' => 'center',
                'footer_alignment' => 'center',
            ],
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Letterhead $letterhead) {
            // If this is marked as default, ensure it's the only default for its zone
            if ($letterhead->is_default) {
                Letterhead::where('zone_id', $letterhead->zone_id)
                    ->where('id', '!=', $letterhead->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
