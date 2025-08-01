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
        Schema::table(
            'notifications', function (Blueprint $table) {
                // ✅ Add recipient_name if it doesn't exist
                if (!Schema::hasColumn('notifications', 'recipient_name')) {
                    $table->string('recipient_name')->nullable()->after('recipient_email');
                }

                // ✅ Add sender_id if it doesn't exist
                if (!Schema::hasColumn('notifications', 'sender_id')) {
                    $table->foreignId('sender_id')->nullable()->constrained('users')->after('priority');
                }
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'notifications', function (Blueprint $table) {
                $table->dropColumn(['recipient_name', 'sender_id']);
            }
        );
    }
};
