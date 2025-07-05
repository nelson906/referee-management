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
        // Create zones table first (needed for users foreign key)
            Schema::create('zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_national')->default(false);
                $table->string('header_document_path')->nullable();
                $table->timestamp('header_updated_at')->nullable();
                $table->unsignedBigInteger('header_updated_by')->nullable();
                $table->timestamps();

                // Indexes
                $table->index('is_national');
                $table->index('name');
            });

        // Create users table with all fields from the start
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->enum('user_type', ['super_admin', 'national_admin', 'admin', 'referee'])->default('referee');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Add indexes
            $table->index(['user_type', 'zone_id']);
            $table->index(['zone_id', 'is_active']);
            $table->index(['user_type', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('zones');
    }
};
