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
        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('title')->index();
            $table->text('description')->nullable();

            // Zone Association (null = global letterhead)
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('cascade');

            // Logo and Design Elements
            $table->string('logo_path')->nullable();
            $table->text('header_text')->nullable();
            $table->text('header_content')->nullable(); // Compatibilità
            $table->text('footer_text')->nullable();
            $table->text('footer_content')->nullable(); // Compatibilità

            // Contact Information (JSON)
            $table->json('contact_info')->nullable();

            // Layout Settings (JSON)
            $table->json('settings')->nullable();

            // Status and Defaults
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // Audit Fields
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for performance
            $table->index(['zone_id', 'is_active']);
            $table->index(['zone_id', 'is_default']);
            $table->index('updated_by');

            // Note: Unique default per zone is enforced in the model
            // MySQL doesn't support partial unique indexes, so we handle this in application logic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letterheads');
    }
};
