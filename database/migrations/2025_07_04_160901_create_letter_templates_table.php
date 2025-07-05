<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['convocation', 'assignment', 'circle', 'general'])->default('general');
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable(); // Available merge fields
            $table->boolean('is_active')->default(true);
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tournament_category_id')->nullable()->constrained()->onDelete('set null');
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // Additional settings
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('is_active');
            $table->index('zone_id');
            $table->index('tournament_category_id');
            $table->index(['type', 'is_active']);
            $table->index(['zone_id', 'is_active']);
        });

        // Migrate data from template_letters table if it exists
        if (Schema::hasTable('template_letters')) {
            DB::table('template_letters')->orderBy('id')->each(function ($template) {
                $variables = [];
                if ($template->merge_fields) {
                    $variables = is_string($template->merge_fields)
                        ? json_decode($template->merge_fields, true)
                        : $template->merge_fields;
                }

                // Map old type to new type
                $typeMap = [
                    'convocation' => 'convocation',
                    'club' => 'circle',
                    'general' => 'general',
                ];
                $newType = $typeMap[$template->type] ?? 'general';

                DB::table('letter_templates')->insert([
                    'name' => $template->name,
                    'type' => $newType,
                    'subject' => $template->header ?? 'Comunicazione',
                    'body' => $template->body,
                    'variables' => json_encode($variables),
                    'is_active' => $template->is_active ?? true,
                    'zone_id' => $template->zone_id,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
