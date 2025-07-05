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
        Schema::create('institutional_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('category', ['federazione', 'comitato', 'zona', 'altro'])->default('altro');
            $table->boolean('receive_all_notifications')->default(false);
            $table->json('notification_types')->nullable(); // Array of notification types to receive
            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('zone_id');
            $table->index('category');
            $table->index(['zone_id', 'is_active']);
        });

        // Migrate data from fixed_addresses table if it exists
        if (Schema::hasTable('fixed_addresses')) {
            DB::table('fixed_addresses')->orderBy('id')->each(function ($address) {
                DB::table('institutional_emails')->insert([
                    'name' => $address->name,
                    'email' => $address->email,
                    'description' => $address->description,
                    'is_active' => $address->active ?? true,
                    'zone_id' => $address->zone_id,
                    'category' => $address->category ?? 'altro',
                    'created_at' => $address->created_at,
                    'updated_at' => $address->updated_at,
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutional_emails');
    }
};
