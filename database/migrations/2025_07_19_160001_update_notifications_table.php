<?php
// File: database/migrations/2025_07_19_160001_update_notifications_table.php

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
        Schema::table('notifications', function (Blueprint $table) {
            // Add new columns for extended functionality
            if (!Schema::hasColumn('notifications', 'priority')) {
                $table->integer('priority')->default(0)->after('retry_count');
            }

            if (!Schema::hasColumn('notifications', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('sent_at');
            }

            if (!Schema::hasColumn('notifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('scheduled_at');
            }

            if (!Schema::hasColumn('notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('attachments');
            }

            // Add indexes for better performance
            if (!Schema::hasIndex('notifications', ['status', 'priority', 'created_at'])) {
                $table->index(['status', 'priority', 'created_at'], 'idx_notifications_queue');
            }

            if (!Schema::hasIndex('notifications', ['recipient_type', 'created_at'])) {
                $table->index(['recipient_type', 'created_at'], 'idx_notifications_type_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_notifications_queue');
            $table->dropIndex('idx_notifications_type_date');

            // Drop columns
            if (Schema::hasColumn('notifications', 'priority')) {
                $table->dropColumn('priority');
            }

            if (Schema::hasColumn('notifications', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }

            if (Schema::hasColumn('notifications', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('notifications', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
