<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crea solo la tabella referee_level_history per storico livelli arbitri
     */
    public function up(): void
    {
        Schema::create('referee_level_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->year('year');
            // ✅ ENUM corretto che corrisponde a users.level
            $table->enum('level', ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale', 'Archivio']);
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Constraint: un utente può avere solo un livello per anno
            $table->unique(['user_id', 'year']);

            // Indexes per query veloci
            $table->index(['year', 'level']);
            $table->index(['user_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referee_level_history');
    }
};
