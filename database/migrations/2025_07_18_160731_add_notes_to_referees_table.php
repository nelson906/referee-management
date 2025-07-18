<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('referees', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('tax_code');
        });
    }

    public function down()
    {
        Schema::table('referees', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
