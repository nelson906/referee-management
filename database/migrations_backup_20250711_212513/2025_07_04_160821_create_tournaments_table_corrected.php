<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * TOURNAMENTS TABLE - Corrected foreign key references
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();

            // ✅ BASIC INFO
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('availability_deadline');

            // ✅ CORRECTED FOREIGN KEYS
            $table->foreignId('club_id')->constrained('clubs')->onDelete('restrict');
            $table->foreignId('tournament_type_id')->constrained('tournament_types')->onDelete('restrict'); // ✅ FIXED
            $table->foreignId('zone_id')->constrained('zones')->onDelete('restrict');

            // ✅ CONTENT & STATUS
            $table->text('notes')->nullable();
            $table->enum('status', [
                'draft',
                'open',
                'closed',
                'assigned',
                'completed',
                'cancelled'
            ])->default('draft');

            // ✅ DOCUMENT GENERATION FIELDS
            $table->text('convocation_letter')->nullable();
            $table->text('club_letter')->nullable();
            $table->timestamp('letters_generated_at')->nullable();

            // ✅ FILE PATHS
            $table->string('convocation_file_path')->nullable();
            $table->string('convocation_file_name')->nullable();
            $table->timestamp('convocation_generated_at')->nullable();

            $table->string('club_letter_file_path')->nullable();
            $table->string('club_letter_file_name')->nullable();
            $table->timestamp('club_letter_generated_at')->nullable();

            // ✅ DOCUMENT VERSIONING
            $table->foreignId('documents_last_updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('document_version')->default(1);

            $table->timestamps();

            // ✅ INDEXES
            $table->index(['zone_id', 'status']);
            $table->index(['start_date', 'status']);
            $table->index(['tournament_type_id', 'status']); // ✅ CORRECTED INDEX
            $table->index(['club_id', 'status']);
            $table->index(['availability_deadline', 'status']);
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
