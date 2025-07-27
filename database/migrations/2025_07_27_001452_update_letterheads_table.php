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
        // Se la tabella non esiste, creala
        if (!Schema::hasTable('letterheads')) {
            Schema::create('letterheads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('logo_path')->nullable();
                $table->text('header_text')->nullable();
                $table->text('header_content')->nullable(); // Compatibilità
                $table->text('footer_text')->nullable();
                $table->text('footer_content')->nullable(); // Compatibilità
                $table->json('contact_info')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                // Indexes
                $table->index(['zone_id', 'is_active']);
                $table->index(['is_default']);
                $table->index(['zone_id', 'is_default']);
            });
        } else {
            // Se la tabella esiste, aggiungi solo i campi mancanti
            Schema::table('letterheads', function (Blueprint $table) {
                if (!Schema::hasColumn('letterheads', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }

                if (!Schema::hasColumn('letterheads', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->after('is_default');
                }

                if (!Schema::hasColumn('letterheads', 'header_content')) {
                    $table->text('header_content')->nullable()->after('header_text');
                }

                if (!Schema::hasColumn('letterheads', 'footer_content')) {
                    $table->text('footer_content')->nullable()->after('footer_text');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letterheads');
    }
};
