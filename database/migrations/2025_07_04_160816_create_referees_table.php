<?php

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
        Schema::create('referees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->string('referee_code', 20)->unique();
            $table->enum('level', ['aspirante', 'primo_livello', 'regionale', 'nazionale', 'internazionale']);
            $table->enum('category', ['maschile', 'femminile', 'misto'])->nullable();
            $table->date('certified_date')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_code', 16)->nullable();
            $table->timestamp('profile_completed_at')->nullable();

            // Additional referee-specific fields
            $table->string('badge_number')->nullable();
            $table->date('first_certification_date')->nullable();
            $table->date('last_renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('qualifications')->nullable();
            $table->json('languages')->nullable();
            $table->boolean('available_for_international')->default(false);
            $table->text('specializations')->nullable();
            $table->integer('total_tournaments')->default(0);
            $table->integer('tournaments_current_year')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['zone_id', 'level']);
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referees');
    }
};
