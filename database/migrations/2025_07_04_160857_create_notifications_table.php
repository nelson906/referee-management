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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->string('recipient_type', 50); // 'referee', 'circle', 'institutional'
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->string('template_used')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 50)->default('pending'); // pending, sent, failed
            $table->text('tracking_info')->nullable();
            $table->json('attachments')->nullable();
            $table->string('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('assignment_id');
            $table->index('status');
            $table->index('sent_at');
            $table->index(['recipient_type', 'recipient_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
