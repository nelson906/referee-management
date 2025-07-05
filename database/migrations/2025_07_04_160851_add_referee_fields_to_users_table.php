<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Aggiungi campi mancanti per allinearsi al nuovo schema
            if (!Schema::hasColumn('users', 'category')) {
                $table->string('category', 50)->after('level')->nullable();
            }

            if (!Schema::hasColumn('users', 'certified_date')) {
                $table->date('certified_date')->after('is_active')->nullable();
            }

            // Modifica il campo level per allinearsi al nuovo schema
            $table->string('level', 50)->change();
        });

        // Crea tabella referee_details per informazioni aggiuntive
        Schema::create('referee_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('badge_number')->nullable();
            $table->date('first_certification_date')->nullable();
            $table->date('last_renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('qualifications')->nullable(); // Array of additional qualifications
            $table->json('languages')->nullable(); // Languages spoken
            $table->boolean('available_for_international')->default(false);
            $table->text('specializations')->nullable();
            $table->integer('total_tournaments')->default(0);
            $table->integer('tournaments_current_year')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('expiry_date');
        });

        // Aggiorna i valori del campo level per allinearsi al nuovo schema
        DB::table('users')->update([
            'level' => DB::raw("
                CASE
                    WHEN level = '1_livello' THEN 'primo_livello'
                    WHEN level = 'nazionale_internazionale' THEN 'nazionale'
                    ELSE level
                END
            ")
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina i valori originali del campo level
        DB::table('users')->update([
            'level' => DB::raw("
                CASE
                    WHEN level = 'primo_livello' THEN '1_livello'
                    WHEN level = 'nazionale' THEN 'nazionale_internazionale'
                    ELSE level
                END
            ")
        ]);

        Schema::dropIfExists('referee_details');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'category')) {
                $table->dropColumn('category');
            }

            if (Schema::hasColumn('users', 'certified_date')) {
                $table->dropColumn('certified_date');
            }
        });
    }
};
