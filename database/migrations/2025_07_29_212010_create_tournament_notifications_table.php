<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crea tabella principale notifiche torneo
        Schema::create('tournament_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['sent', 'partial', 'failed', 'pending'])->default('pending');
            $table->integer('total_recipients')->default(0);
            $table->text('referee_list')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('details')->nullable();
            $table->json('templates_used')->nullable();
            $table->text('error_message')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status']);
            $table->index(['sent_at']);
            $table->index(['status']);
        });

        // Aggiorna tabella notifications esistente
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'tournament_id')) {
                    $table->foreignId('tournament_id')->nullable()->after('assignment_id')
                        ->constrained()->onDelete('cascade');
                }
                if (!Schema::hasColumn('notifications', 'recipient_name')) {
                    $table->string('recipient_name')->nullable()->after('recipient_email');
                }
                if (!Schema::hasColumn('notifications', 'attachments')) {
                    $table->json('attachments')->nullable()->after('error_message');
                }

                $table->index(['tournament_id', 'recipient_type']);
                $table->index(['status', 'sent_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['tournament_id']);
                $table->dropIndex(['tournament_id', 'recipient_type']);
                $table->dropIndex(['status', 'sent_at']);
                $table->dropColumn(['tournament_id', 'recipient_name', 'attachments']);
            });
        }

        Schema::dropIfExists('tournament_notifications');
    }
};
