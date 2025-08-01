<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('tournament_notifications', function (Blueprint $table) {
        if (!Schema::hasColumn('tournament_notifications', 'sent_by')) {
            $table->unsignedBigInteger('sent_by')->nullable()->after('tournament_id');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            //
        });
    }
};
