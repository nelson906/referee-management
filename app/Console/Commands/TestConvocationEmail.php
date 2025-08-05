<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\Tournament;
use App\Mail\AssignmentNotification;

class TestConvocationEmail extends Command
{
    protected $signature = 'email:test-convocation {tournament_id} {email}';
    protected $description = 'Test convocation email with attachment';

    public function handle()
    {
        // CREA UN NOTIFICATION DI TEST
        $notification = new \App\Models\Notification();
        $notification->id = 999;
        $notification->subject = 'Test Convocazione';
        $notification->body = 'Questo è un test della convocazione';
        $notification->recipient_email = $this->argument('email') ?? 'test@example.com';
        $notification->recipient_name = 'Test User';
        $notification->recipient_type = 'referee';
        $notification->status = 'pending';

        // CREA LE VARIABILI PER IL TEMPLATE
        $variables = [
            'tournament_name' => 'Torneo di Test',
            'tournament_date' => now()->format('d/m/Y'),
            'club_name' => 'Golf Club Test',
            'referee_name' => 'Mario Rossi',
            'role' => 'Arbitro',
            'tournament' => (object)[
                'name' => 'Torneo di Test',
                'start_date' => now(),
                'end_date' => now()->addDays(2),
                'club' => (object)['name' => 'Golf Club Test']
            ]
        ];

        // CREA L'EMAIL
        $mail = new AssignmentNotification($notification, $variables);

        // INVIA O VISUALIZZA
        if ($this->option('send')) {
            Mail::to($notification->recipient_email)->send($mail);
            $this->info('Email inviata a: ' . $notification->recipient_email);
        } else {
            $this->info('Preview email:');
            $this->line($mail->render());
        }
    }
}
