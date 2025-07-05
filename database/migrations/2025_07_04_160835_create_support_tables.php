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
        // Create institutional_emails table
        Schema::create('institutional_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->enum('category', ['federazione', 'comitati', 'zone', 'altro'])->default('altro');
            $table->boolean('receive_all_notifications')->default(false);
            $table->json('notification_types')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
            $table->index(['category', 'is_active']);
        });

        // Create letter_templates table
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['assignment', 'convocation', 'circle', 'institutional'])->default('assignment');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('tournament_category_id')->nullable()->constrained('tournament_categories');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['zone_id', 'type']);
            $table->index(['tournament_category_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('institutional_emails');
    }
};
