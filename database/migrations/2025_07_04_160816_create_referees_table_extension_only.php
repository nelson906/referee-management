<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * REFEREES TABLE - Extension Only (NO Duplicates with Users)
     */
    public function up(): void
    {
        Schema::create('referees', function (Blueprint $table) {
            $table->id();

            // ✅ LINK TO USER (Single Source of Truth)
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');

            // ✅ EXTENDED ADDRESS INFO
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_code', 16)->nullable();

            // ✅ CERTIFICATION DETAILS
            $table->string('badge_number')->nullable();
            $table->date('first_certification_date')->nullable();
            $table->date('last_renewal_date')->nullable();
            $table->date('expiry_date')->nullable();

            // ✅ REFEREE PROFILE DATA
            $table->text('bio')->nullable();
            $table->integer('experience_years')->default(0);
            $table->json('qualifications')->nullable();
            $table->json('languages')->nullable();
            $table->json('specializations')->nullable();

            // ✅ AVAILABILITY & PREFERENCES
            $table->boolean('available_for_international')->default(false);
            $table->json('preferences')->nullable();

            // ✅ STATISTICS
            $table->integer('total_tournaments')->default(0);
            $table->integer('tournaments_current_year')->default(0);

            // ✅ PROFILE STATUS
            $table->timestamp('profile_completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ✅ INDEXES
            $table->index('user_id');
            $table->index('expiry_date');
            $table->index('available_for_international');
            $table->index('profile_completed_at');
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
