<?php
/**
 * MIGRATION CORRETTA - TOURNAMENTS
 *
 * File: database/migrations/2025_07_04_160821_create_tournaments_table.php
 *
 * Usa tournament_type_id foreign key (FASE 2 compliant)
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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('availability_deadline');
            $table->foreignId('club_id')->constrained('clubs');

            // ✅ FIXED: tournament_type_id instead of tournament_category_id
            $table->foreignId('tournament_type_id')->constrained('tournament_types');

            $table->foreignId('zone_id')->constrained('zones');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'open', 'closed', 'assigned', 'completed'])->default('draft');

            // Document generation fields
            $table->text('convocation_letter')->nullable();
            $table->text('club_letter')->nullable();
            $table->timestamp('letters_generated_at')->nullable();
            $table->string('convocation_file_path')->nullable();
            $table->string('convocation_file_name')->nullable();
            $table->timestamp('convocation_generated_at')->nullable();
            $table->string('club_letter_file_path')->nullable();
            $table->string('club_letter_file_name')->nullable();
            $table->timestamp('club_letter_generated_at')->nullable();
            $table->foreignId('documents_last_updated_by')->nullable()->constrained('users');
            $table->integer('document_version')->default(1);

            $table->timestamps();

            // ✅ FIXED: Indexes updated for tournament_type_id
            $table->index(['zone_id', 'status']);
            $table->index(['start_date', 'status']);
            $table->index(['tournament_type_id', 'status']);
            $table->index(['club_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
