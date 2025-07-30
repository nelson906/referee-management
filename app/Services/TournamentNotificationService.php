<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Dompdf\Dompdf;
use Dompdf\Options;

class TournamentNotificationService
{
    /**
     * 🎯 FUNZIONE PRINCIPALE: Invia tutte le notifiche di un torneo
     */
    public function sendTournamentNotifications(Tournament $tournament, array $options): array
    {
        $results = [
            'tournament_id' => $tournament->id,
            'total_sent' => 0,
            'details' => [
                'club' => ['sent' => 0, 'failed' => 0, 'errors' => []],
                'referees' => ['sent' => 0, 'failed' => 0, 'errors' => []],
                'institutional' => ['sent' => 0, 'failed' => 0, 'errors' => []]
            ]
        ];

        try {
            // 📝 1. Genera tutti i documenti Word personalizzati
            $documents = $this->generateDocuments($tournament);

            // 📎 2. Prepara allegati per ogni tipo destinatario
            $attachments = $this->prepareAttachments($documents, $tournament);

            // 📧 3. Invia email al circolo
            if ($options['send_to_club'] ?? true) {
                $clubResult = $this->sendToClub($tournament, $attachments, $options);
                $results['details']['club'] = $clubResult;
                $results['total_sent'] += $clubResult['sent'];
            }

            // ⚖️ 4. Invia email agli arbitri
            if ($options['send_to_referees'] ?? true) {
                $refereesResult = $this->sendToReferees($tournament, $attachments, $options);
                $results['details']['referees'] = $refereesResult;
                $results['total_sent'] += $refereesResult['sent'];
            }

            // 🏛️ 5. Invia email agli istituzionali
            if ($options['send_to_institutional'] ?? true) {
                $institutionalResult = $this->sendToInstitutional($tournament, $options);
                $results['details']['institutional'] = $institutionalResult;
                $results['total_sent'] += $institutionalResult['sent'];
            }

            // 💾 6. Salva record torneo
            $status = ($results['details']['club']['failed'] + $results['details']['referees']['failed'] + $results['details']['institutional']['failed']) > 0 ? 'partial' : 'sent';

            $tournamentNotification = TournamentNotification::create([
                'tournament_id' => $tournament->id,
                'status' => $status,
                'total_recipients' => $results['total_sent'],
                'sent_at' => now(),
                'sent_by' => $options['sent_by'],
                'details' => $results['details'],
                'templates_used' => [
                    'email' => $options['email_template'] ?? 'tournament_assignment_generic',
                    'documents' => $documents['templates_used'] ?? []
                ]
            ]);

            $results['tournament_notification_id'] = $tournamentNotification->id;

        } catch (\Exception $e) {
            Log::error('Tournament notification error', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * 📝 Genera tutti i documenti Word personalizzati
     */
    private function generateDocuments(Tournament $tournament): array
    {
        $zone = $tournament->zone;
        $templateBasePath = storage_path('app/public/convocationi/templates/');
        $generatedBasePath = storage_path("app/public/convocationi/SZR{$zone->id}/generated/");

        // Crea directory se non esiste
        if (!file_exists($generatedBasePath)) {
            mkdir($generatedBasePath, 0755, true);
        }

        $documents = [
            'szr_convocation' => null,
            'club_facsimile' => null,
            'referee_convocations' => [],
            'templates_used' => []
        ];

        try {
            // 🏌️ 1. Genera Convocazione SZR per circolo
            $documents['szr_convocation'] = $this->generateSZRConvocation($tournament, $templateBasePath, $generatedBasePath);

            // 📄 2. Genera Facsimile per circolo
            $documents['club_facsimile'] = $this->generateClubFacsimile($tournament, $templateBasePath, $generatedBasePath);

            // ⚖️ 3. Genera convocazioni personalizzate per ogni arbitro
            foreach ($tournament->assignments as $assignment) {
                $documents['referee_convocations'][$assignment->id] = $this->generateRefereeConvocation(
                    $assignment, $templateBasePath, $generatedBasePath
                );
            }

            $documents['templates_used'] = [
                'szr_template' => 'convocazione_szr_template.docx',
                'facsimile_template' => 'facsimile_circolo_template.docx',
                'referee_template' => 'convocazione_arbitro_template.docx'
            ];

        } catch (\Exception $e) {
            Log::error('Document generation error', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Errore nella generazione documenti: ' . $e->getMessage());
        }

        return $documents;
    }

    /**
     * 📎 Prepara allegati per invio email
     */
    private function prepareAttachments(array $documents, Tournament $tournament): array
    {
        $attachments = [
            'club' => [],
            'referees' => []
        ];

        try {
            // 🏌️ Allegati per circolo: SZR PDF + Facsimile DOCX
            if ($documents['szr_convocation']) {
                // Converte SZR in PDF
                $pdfPath = $this->convertWordToPDF($documents['szr_convocation']);
                $attachments['club'][] = [
                    'path' => $pdfPath,
                    'name' => 'Convocazione_SZR.pdf',
                    'mime' => 'application/pdf'
                ];
            }

            if ($documents['club_facsimile']) {
                // Facsimile resta in Word
                $attachments['club'][] = [
                    'path' => $documents['club_facsimile'],
                    'name' => 'Facsimile_Convocazione_Circolo.docx',
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
            }

            // ⚖️ Allegati per arbitri: Convocazione PDF personalizzata
            foreach ($documents['referee_convocations'] as $assignmentId => $docPath) {
                $pdfPath = $this->convertWordToPDF($docPath);
                $attachments['referees'][$assignmentId] = [
                    'path' => $pdfPath,
                    'name' => 'Convocazione_Ufficiale.pdf',
                    'mime' => 'application/pdf'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Attachments preparation error', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }

        return $attachments;
    }

    /**
     * 📝 Genera Convocazione SZR
     */
    private function generateSZRConvocation(Tournament $tournament, string $templatePath, string $outputPath): string
    {
        $templateFile = $templatePath . 'convocazione_szr_template.docx';
        $outputFile = $outputPath . "tournament_{$tournament->id}_szr.docx";

        if (!file_exists($templateFile)) {
            throw new \Exception("Template SZR non trovato: {$templateFile}");
        }

        $templateProcessor = new TemplateProcessor($templateFile);

        // Variabili base
        $templateProcessor->setValue('club_name', $tournament->club->name ?? '');
        $templateProcessor->setValue('tournament_name', $tournament->name);
        $templateProcessor->setValue('tournament_dates', $this->formatTournamentDates($tournament));
        $templateProcessor->setValue('zone_name', $tournament->zone->name ?? '');

        // Lista arbitri per SZR
        $refereesList = $tournament->assignments->map(function($assignment) {
            $referee = $assignment->referee ?? $assignment->user;
            return [
                'name' => $referee->name ?? 'N/A',
                'role' => $this->translateRole($assignment->role)
            ];
        })->toArray();

        // Clona righe per arbitri
        $templateProcessor->cloneRow('referee_name', count($refereesList));
        foreach ($refereesList as $index => $referee) {
            $templateProcessor->setValue("referee_name#" . ($index + 1), $referee['name']);
            $templateProcessor->setValue("referee_role#" . ($index + 1), $referee['role']);
        }

        $templateProcessor->saveAs($outputFile);

        Log::info('SZR convocation generated', ['file' => $outputFile]);

        return $outputFile;
    }

    /**
     * 📄 Genera Facsimile Circolo
     */
    private function generateClubFacsimile(Tournament $tournament, string $templatePath, string $outputPath): string
    {
        $templateFile = $templatePath . 'facsimile_circolo_template.docx';
        $outputFile = $outputPath . "tournament_{$tournament->id}_facsimile.docx";

        if (!file_exists($templateFile)) {
            throw new \Exception("Template facsimile non trovato: {$templateFile}");
        }

        $templateProcessor = new TemplateProcessor($templateFile);

        // Variabili per facsimile
        $templateProcessor->setValue('club_name', $tournament->club->name ?? '');
        $templateProcessor->setValue('tournament_name', $tournament->name);
        $templateProcessor->setValue('tournament_dates', $this->formatTournamentDates($tournament));
        $templateProcessor->setValue('zone_email', $tournament->zone->email ?? 'szr@federgolf.it');
        $templateProcessor->setValue('club_email', $tournament->club->email ?? '');

        // Lista arbitri completa per facsimile
        $refereesList = $tournament->assignments->map(function($assignment) {
            $referee = $assignment->referee ?? $assignment->user;
            return [
                'name' => $referee->name ?? 'N/A',
                'role' => $this->translateRole($assignment->role),
                'email' => $referee->email ?? ''
            ];
        })->toArray();

        // Clona righe per arbitri nel facsimile
        $templateProcessor->cloneRow('referee_name', count($refereesList));
        foreach ($refereesList as $index => $referee) {
            $templateProcessor->setValue("referee_name#" . ($index + 1), $referee['name']);
            $templateProcessor->setValue("referee_role#" . ($index + 1), $referee['role']);
        }

        $templateProcessor->saveAs($outputFile);

        Log::info('Club facsimile generated', ['file' => $outputFile]);

        return $outputFile;
    }

    /**
     * ⚖️ Genera Convocazione Arbitro personalizzata
     */
    private function generateRefereeConvocation($assignment, string $templatePath, string $outputPath): string
    {
        $templateFile = $templatePath . 'convocazione_arbitro_template.docx';
        $outputFile = $outputPath . "tournament_{$assignment->tournament_id}_referee_{$assignment->id}.docx";

        if (!file_exists($templateFile)) {
            throw new \Exception("Template arbitro non trovato: {$templateFile}");
        }

        $templateProcessor = new TemplateProcessor($templateFile);

        $referee = $assignment->referee ?? $assignment->user;
        $tournament = $assignment->tournament;

        // Variabili personalizzate arbitro
        $templateProcessor->setValue('referee_name', $referee->name ?? '');
        $templateProcessor->setValue('assignment_role', $this->translateRole($assignment->role));
        $templateProcessor->setValue('tournament_name', $tournament->name);
        $templateProcessor->setValue('tournament_dates', $this->formatTournamentDates($tournament));
        $templateProcessor->setValue('club_name', $tournament->club->name ?? '');
        $templateProcessor->setValue('club_address', $tournament->club->address ?? '');
        $templateProcessor->setValue('zone_name', $tournament->zone->name ?? '');
        $templateProcessor->setValue('zone_email', $tournament->zone->email ?? 'szr@federgolf.it');

        $templateProcessor->saveAs($outputFile);

        Log::info('Referee convocation generated', [
            'file' => $outputFile,
            'referee' => $referee->name,
            'assignment_id' => $assignment->id
        ]);

        return $outputFile;
    }

    /**
     * 📄➡️📋 Converte Word in PDF
     */
    private function convertWordToPDF(string $wordPath): string
    {
        $pdfPath = str_replace('.docx', '.pdf', $wordPath);

        try {
            // Metodo 1: Se hai LibreOffice installato (production)
            if ($this->hasLibreOffice()) {
                $command = "libreoffice --headless --convert-to pdf --outdir " . dirname($pdfPath) . " " . escapeshellarg($wordPath);
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($pdfPath)) {
                    Log::info('PDF converted with LibreOffice', ['pdf' => $pdfPath]);
                    return $pdfPath;
                }
            }

            // Metodo 2: Conversione semplificata (development)
            $this->createSimplePDF($wordPath, $pdfPath);

        } catch (\Exception $e) {
            Log::error('PDF conversion error', [
                'word_path' => $wordPath,
                'error' => $e->getMessage()
            ]);

            // Fallback: usa il file Word originale
            return $wordPath;
        }

        return $pdfPath;
    }

    /**
     * 🔍 Verifica se LibreOffice è disponibile
     */
    private function hasLibreOffice(): bool
    {
        $output = shell_exec('which libreoffice 2>/dev/null');
        return !empty($output);
    }

    /**
     * 📋 Crea PDF semplificato per development
     */
    private function createSimplePDF(string $wordPath, string $pdfPath): void
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Contenuto semplificato per sviluppo
        $html = "<html><body><h1>Documento di Convocazione</h1><p>File generato da: " . basename($wordPath) . "</p><p>Data: " . date('d/m/Y H:i') . "</p></body></html>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($pdfPath, $dompdf->output());

        Log::info('Simple PDF created for development', ['pdf' => $pdfPath]);
    }

    /**
     * 🏌️ Invio email al circolo
     */
    private function sendToClub(Tournament $tournament, array $attachments, array $options): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'errors' => []];

        try {
            if (!$tournament->club || !$tournament->club->email) {
                $result['errors'][] = "Circolo senza email";
                $result['failed'] = 1;
                return $result;
            }

            $templateData = [
                'recipient_name' => $tournament->club->name,
                'tournament_name' => $tournament->name,
                'tournament_dates' => $this->formatTournamentDates($tournament),
                'club_name' => $tournament->club->name,
                'referees' => $tournament->assignments->map(function($assignment) {
                    $referee = $assignment->referee ?? $assignment->user;
                    return [
                        'name' => $referee->name ?? 'N/A',
                        'role' => $this->translateRole($assignment->role),
                        'email' => $referee->email ?? ''
                    ];
                })->toArray()
            ];

            $this->sendEmailWithTemplate(
                $tournament->club->email,
                $tournament->club->name,
                $templateData,
                $attachments['club'] ?? [],
                $options['email_template'] ?? 'tournament_assignment_generic'
            );

            $this->createIndividualNotification($tournament, [
                'recipient_type' => 'club',
                'recipient_email' => $tournament->club->email,
                'recipient_name' => $tournament->club->name,
                'template_used' => $options['email_template'] ?? 'tournament_assignment_generic',
                'attachments' => $attachments['club'] ?? []
            ]);

            $result['sent'] = 1;

        } catch (\Exception $e) {
            $result['failed'] = 1;
            $result['errors'][] = "Errore invio circolo: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * ⚖️ Invio email agli arbitri
     */
    private function sendToReferees(Tournament $tournament, array $attachments, array $options): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($tournament->assignments as $assignment) {
            try {
                $referee = $assignment->referee ?? $assignment->user;

                if (!$referee || !$referee->email) {
                    $result['failed']++;
                    $result['errors'][] = "Arbitro senza email in assegnazione ID: {$assignment->id}";
                    continue;
                }

                $templateData = [
                    'recipient_name' => $referee->name,
                    'tournament_name' => $tournament->name,
                    'tournament_dates' => $this->formatTournamentDates($tournament),
                    'club_name' => $tournament->club->name ?? 'N/A',
                    'assignment_role' => $this->translateRole($assignment->role),
                    'referees' => [[ // Per compatibility con template unico
                        'name' => $referee->name,
                        'role' => $this->translateRole($assignment->role),
                        'email' => $referee->email
                    ]]
                ];

                $refereeAttachments = isset($attachments['referees'][$assignment->id])
                    ? [$attachments['referees'][$assignment->id]]
                    : [];

                $this->sendEmailWithTemplate(
                    $referee->email,
                    $referee->name,
                    $templateData,
                    $refereeAttachments,
                    $options['email_template'] ?? 'tournament_assignment_generic'
                );

                $this->createIndividualNotification($tournament, [
                    'assignment_id' => $assignment->id,
                    'recipient_type' => 'referee',
                    'recipient_email' => $referee->email,
                    'recipient_name' => $referee->name,
                    'template_used' => $options['email_template'] ?? 'tournament_assignment_generic',
                    'attachments' => $refereeAttachments
                ]);

                $result['sent']++;

            } catch (\Exception $e) {
                $result['failed']++;
                $refereeName = ($assignment->referee ?? $assignment->user)->name ?? 'Sconosciuto';
                $result['errors'][] = "Errore invio a {$refereeName}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * 🏛️ Invio email istituzionali
     */
    private function sendToInstitutional(Tournament $tournament, array $options): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'errors' => []];

        $institutionalEmails = [
            'crc@federgolf.it' => 'CRC Nazionale',
            $tournament->zone->email ?? 'szr@federgolf.it' => $tournament->zone->name ?? 'SZR',
        ];

        foreach ($institutionalEmails as $email => $name) {
            try {
                $templateData = [
                    'recipient_name' => $name,
                    'tournament_name' => $tournament->name,
                    'tournament_dates' => $this->formatTournamentDates($tournament),
                    'club_name' => $tournament->club->name ?? 'N/A',
                    'referees' => $tournament->assignments->map(function($assignment) {
                        $referee = $assignment->referee ?? $assignment->user;
                        return [
                            'name' => $referee->name ?? 'N/A',
                            'role' => $this->translateRole($assignment->role),
                            'email' => $referee->email ?? ''
                        ];
                    })->toArray()
                ];

                $this->sendEmailWithTemplate(
                    $email,
                    $name,
                    $templateData,
                    [], // Nessun allegato per istituzionali
                    $options['email_template'] ?? 'tournament_assignment_generic'
                );

                $this->createIndividualNotification($tournament, [
                    'recipient_type' => 'institutional',
                    'recipient_email' => $email,
                    'recipient_name' => $name,
                    'template_used' => $options['email_template'] ?? 'tournament_assignment_generic',
                    'attachments' => []
                ]);

                $result['sent']++;

            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = "Errore invio istituzionale a {$name}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * 📧 Invio email con template Blade
     */
    private function sendEmailWithTemplate(string $to, string $recipientName, array $data, array $attachments, string $template): void
    {
        Mail::send("emails.{$template}", $data, function($message) use ($to, $recipientName, $data, $attachments) {
            $message->to($to, $recipientName)
                   ->subject("Assegnazione Arbitri - " . $data['tournament_name']);

            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $message->attach($attachment['path'], [
                        'as' => $attachment['name'],
                        'mime' => $attachment['mime']
                    ]);
                }
            }
        });

        Log::info("Email sent successfully", [
            'to' => $to,
            'template' => $template,
            'attachments_count' => count($attachments)
        ]);
    }

    /**
     * 🔗 Salva notifica individuale
     */
    private function createIndividualNotification(Tournament $tournament, array $data): void
    {
        Notification::create([
            'tournament_id' => $tournament->id,
            'assignment_id' => $data['assignment_id'] ?? null,
            'recipient_type' => $data['recipient_type'],
            'recipient_email' => $data['recipient_email'],
            'recipient_name' => $data['recipient_name'] ?? '',
            'subject' => "Assegnazione Arbitri - {$tournament->name}",
            'body' => "Email inviata con template: {$data['template_used']}",
            'template_used' => $data['template_used'],
            'status' => 'sent',
            'sent_at' => now(),
            'attachments' => $data['attachments'] ?? []
        ]);
    }

    /**
     * 🔄 Reinvio notifiche torneo
     */
    public function resendTournamentNotifications(TournamentNotification $tournamentNotification): array
    {
        $tournament = $tournamentNotification->tournament;
        $originalTemplates = $tournamentNotification->templates_used;

        // Reinvia con gli stessi template originali
        $options = [
            'email_template' => $originalTemplates['email'] ?? 'tournament_assignment_generic',
            'include_attachments' => true,
            'sent_by' => auth()->id()
        ];

        // Elimina il vecchio record
        $tournamentNotification->individualNotifications()->delete();
        $tournamentNotification->delete();

        // Crea nuovo invio
        return $this->sendTournamentNotifications($tournament, $options);
    }

    /**
     * 📅 Helper: Formatta date torneo
     */
    private function formatTournamentDates(Tournament $tournament): string
    {
        if ($tournament->start_date->isSameDay($tournament->end_date)) {
            return $tournament->start_date->format('d/m/Y');
        }
        return $tournament->start_date->format('d/m/Y') . ' - ' . $tournament->end_date->format('d/m/Y');
    }

    /**
     * 🎯 Helper: Traduce ruoli
     */
    private function translateRole(string $role): string
    {
        $roles = [
            'chief_referee' => 'Direttore di Torneo',
            'referee' => 'Arbitro',
            'observer' => 'Osservatore'
        ];
        return $roles[$role] ?? $role;
    }
}
