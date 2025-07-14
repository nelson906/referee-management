<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * COMPLETE USERS TABLE - Single Source of Truth per Referee Data
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ✅ CORE USER FIELDS
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // ✅ USER TYPE & PERMISSIONS
            $table->enum('user_type', ['referee', 'admin', 'national_admin', 'super_admin'])->default('referee');
            $table->boolean('is_active')->default(true);

            // ✅ REFEREE CORE FIELDS (Single Source of Truth)
            $table->string('referee_code', 20)->unique()->nullable();
            $table->enum('level', [
                'aspirante',
                'primo_livello',
                'regionale',
                'nazionale',
                'internazionale',
                'archivio'
            ])->nullable();
            $table->enum('category', ['maschile', 'femminile', 'misto'])->nullable();
            $table->date('certified_date')->nullable();

            // ✅ CONTACT & LOCATION
            $table->string('phone', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('set null');

            // ✅ SYSTEM FIELDS
            $table->text('notes')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ✅ INDEXES
            $table->index(['user_type', 'is_active']);
            $table->index(['zone_id', 'user_type']);
            $table->index(['level', 'is_active']);
            $table->index('referee_code');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
