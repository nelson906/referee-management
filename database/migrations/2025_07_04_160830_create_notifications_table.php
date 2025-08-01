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
            $table->foreignId('assignment_id')->constrained('assignments')->nullable();
            $table->enum('recipient_type', ['referee', 'club', 'institutional']);
            $table->string('recipient_email')->nullable();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('template_used')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp(column: 'sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('priority')->default(0);
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'recipient_type']);
            $table->index(['status', 'created_at']);
            $table->index(['recipient_email', 'status']);
            // $table->index(['status', 'priority', 'created_at'], 'idx_notifications_queue');
            // $table->index(['recipient_type', 'created_at'], 'idx_notifications_type_date');
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
