<?php

namespace App\Mail\TournamentNotifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Services\ConvocationPdfService;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Services\CsvReportService;

/**
 * 📊 Mailable per report statistiche
 */
class NotificationStatsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $stats,
        public string $period = 'weekly',
        public ?string $recipientName = null
    ) {
        $this->onQueue('reports');
    }

    public function envelope(): Envelope
    {
        $periodLabels = [
            'daily' => 'Giornaliero',
            'weekly' => 'Settimanale',
            'monthly' => 'Mensile',
            'yearly' => 'Annuale'
        ];

        $periodLabel = $periodLabels[$this->period] ?? ucfirst($this->period);

        return new Envelope(
            subject: "📊 Report Notifiche {$periodLabel} - " . now()->format('d/m/Y'),
            from: config('tournament-notifications.email.from.address'),
            tags: ['stats-report', 'periodic-report'],
            metadata: [
                'period' => $this->period,
                'total_notifications' => $this->stats['total_notifications'] ?? 0
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.stats',
            with: [
                'stats' => $this->stats,
                'period' => $this->period,
                'recipientName' => $this->recipientName,
                'generated_at' => now(),
                'charts' => $this->generateChartData()
            ]
        );
    }

    public function attachments(): array
    {
        // Genera CSV report se richiesto
        $csvPath = $this->generateCsvReport();

        if ($csvPath) {
            return [
                Attachment::fromStorage($csvPath)
                          ->as("notification_stats_{$this->period}_" . now()->format('Y_m_d') . '.csv')
                          ->withMime('text/csv')
            ];
        }

        return [];
    }

    private function generateChartData(): array
    {
        // Prepara dati per grafici nelle email
        return [
            'success_rate' => $this->stats['success_rate'] ?? 0,
            'by_type' => $this->stats['by_type'] ?? [],
            'trends' => $this->stats['trends'] ?? []
        ];
    }

    private function generateCsvReport(): ?string
    {
        try {
            $csvService = app(\App\Services\CsvReportService::class);
            return $csvService->generateNotificationStats($this->stats, $this->period);
        } catch (\Exception $e) {
            \Log::warning('Failed to generate CSV report', [
                'period' => $this->period,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
