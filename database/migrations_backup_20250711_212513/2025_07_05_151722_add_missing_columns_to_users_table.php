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
        Schema::table('users', function (Blueprint $table) {
            // Add missing user fields only if they don't exist
            if (!Schema::hasColumn('users', 'referee_code')) {
                $table->string('referee_code', 20)->nullable()->unique()->after('email');
            }

            if (!Schema::hasColumn('users', 'level')) {
                $table->string('level', 50)->nullable()->after('referee_code');
            }

            if (!Schema::hasColumn('users', 'category')) {
                $table->string('category', 50)->nullable()->after('level');
            }

            if (!Schema::hasColumn('users', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('category');
            }

            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('zone_id');
            }

            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }

            if (!Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 50)->default('referee')->after('is_active');
            }

            if (!Schema::hasColumn('users', 'certified_date')) {
                $table->date('certified_date')->nullable()->after('user_type');
            }

            // Add soft deletes
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'referee_code',
                'level',
                'category',
                'zone_id',
                'phone',
                'notes',
                'is_active',
                'user_type',
                'certified_date',
                'deleted_at'
            ]);
        });
    }
};
