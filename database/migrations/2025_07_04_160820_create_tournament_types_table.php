<?php
/**
 * MIGRATION CORRETTA - TOURNAMENT TYPES
 *
 * File: database/migrations/2025_07_04_160820_create_tournament_types_table.php
 *
 * Crea la tabella tournament_types con la struttura corretta
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournament_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(false);
            $table->enum('level', ['zonale', 'nazionale'])->default('zonale');
            $table->enum('required_level', ['aspirante', 'primo_livello', 'regionale', 'nazionale', 'internazionale'])
                  ->default('aspirante');

            // ✅ FIXED: Physical columns per compatibility
            $table->integer('min_referees')->default(1);
            $table->integer('max_referees')->default(1);

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index(['is_national', 'is_active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_types');
    }
};
