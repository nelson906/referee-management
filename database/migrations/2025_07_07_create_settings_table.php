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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, float, array, json
            $table->text('description')->nullable();
            $table->string('group')->default('general'); // general, mail, system, security, etc.
            $table->boolean('is_public')->default(false); // if setting can be viewed by non-admins
            $table->boolean('is_editable')->default(true); // if setting can be edited via UI
            $table->timestamps();

            $table->index(['group']);
            $table->index(['key', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
