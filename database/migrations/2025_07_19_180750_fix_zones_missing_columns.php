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
        Schema::table('zones', function (Blueprint $table) {
            // ✅ AGGIUNGI ENTRAMBE LE COLONNE MANCANTI
            $table->boolean('is_national')->default(false);

            // Add indexes for performance
            $table->index('is_national');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropIndex(['is_national']);
            $table->dropColumn(['is_national']);
        });
    }
};
