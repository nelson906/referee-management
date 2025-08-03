<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\Tournament;

class TestConvocationEmail extends Command
{
    protected $signature = 'email:test-convocation {tournament_id} {email}';
    protected $description = 'Test convocation email with attachment';

    public function handle()
    {
        $tournamentId = $this->argument('tournament_id');
        $email = $this->argument('email');

        $tournament = Tournament::with(['club', 'assignments.referee.user'])->find($tournamentId);

        if (!$tournament) {
            $this->error('Tournament not found');
            return 1;
        }

        // Crea file test
        $testPath = storage_path('app/public/convocations/test_convocation.txt');
        file_put_contents($testPath, 'Test convocation content');

        // Crea mail
        $mail = new UnifiedAssignmentNotification(
            $tournament,
            $tournament->assignments,
            'Test Convocazione',
            'Questo è un test con allegato',
            'Test User'
        );

        // Aggiungi allegato
        $mail->attach($testPath, [
            'as' => 'convocazione_test.txt',
            'mime' => 'text/plain',
        ]);

        // Invia
        Mail::to($email)->send($mail);

        $this->info("Email inviata a {$email}");
        $this->info("Controlla Mailhog per verificare l'allegato");

        // Pulisci
        unlink($testPath);

        return 0;
    }
}
