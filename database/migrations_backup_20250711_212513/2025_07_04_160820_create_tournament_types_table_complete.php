<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * TOURNAMENT_TYPES TABLE - Complete with all fields incorporated
     */
    public function up(): void
    {
        Schema::create('tournament_types', function (Blueprint $table) {
            $table->id();

            // ✅ BASIC INFO
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();

            // ✅ TYPE & LEVEL
            $table->boolean('is_national')->default(false);
            $table->enum('level', ['zonale', 'nazionale'])->default('zonale');

            // ✅ REFEREE REQUIREMENTS
            $table->enum('required_level', [
                'aspirante',
                'primo_livello',
                'regionale',
                'nazionale',
                'internazionale'
            ])->default('aspirante');

            // ✅ REFEREE NUMBERS (Incorporated from add_migration)
            $table->integer('min_referees')->default(1);
            $table->integer('max_referees')->default(1);

            // ✅ DISPLAY & STATUS
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // ✅ ADVANCED SETTINGS (JSON)
            $table->json('settings')->nullable();

            $table->timestamps();

            // ✅ INDEXES
            $table->index(['is_active', 'sort_order']);
            $table->index(['is_national', 'is_active']);
            $table->index(['required_level', 'is_active']);
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
