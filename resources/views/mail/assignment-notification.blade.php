{{-- File: resources/views/mail/assignment-notification.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->subject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #0b74f5da;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            font-family: 'Sylfaen', serif;
            font-style: italic;
            color: #1a202c;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            font-size: 14px;
            color: #4a5568;
            margin: 5px 0 0 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .recipient-greeting {
            font-weight: 600;
            margin-bottom: 20px;
        }
        .message-content {
            margin: 20px 0;
            line-height: 1.7;
        }
        .tournament-details {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .tournament-details h3 {
            color: #1f2937;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        .detail-item {
            margin: 8px 0;
            display: flex;
        }
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: #4b5563;
        }
        .detail-value {
            color: #1f2937;
        }
        .assignments-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #ffffff;
        }
        .assignments-table th,
        .assignments-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }
        .assignments-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .attachments-info {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .attachments-info h4 {
            color: #92400e;
            margin: 0 0 10px 0;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            margin: 5px 0;
            color: #78350f;
        }
        .club-specific {
            background-color: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .club-specific h4 {
            color: #1e40af;
            margin: 0 0 15px 0;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            font-size: 12px;
            color: #6b7280;
        }
        .signature {
            margin: 30px 0 20px 0;
            font-weight: 600;
        }
        .contact-info {
            font-size: 14px;
            color: #6b7280;
            margin-top: 20px;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px;
            }
            .assignments-table {
                font-size: 14px;
            }
            .detail-item {
                flex-direction: column;
            }
            .detail-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

    {{-- Header con branding Federazione --}}
    <div class="header">
        <h1>Federazione Italiana Golf</h1>
        <p>Sezione Zonale Regole</p>
    </div>

    <div class="content">

        {{-- Saluto personalizzato --}}
        @if($recipientName)
            <div class="recipient-greeting">
                Gentile {{ $recipientName }},
            </div>
        @elseif($isClub)
            <div class="recipient-greeting">
                Spett.le {{ $tournament->club->name }},
            </div>
        @else
            <div class="recipient-greeting">
                Gentile Destinatario,
            </div>
        @endif

        {{-- Contenuto personalizzato del messaggio --}}
        <div class="message-content">
            {!! nl2br(e($messageContent)) !!}
        </div>

        {{-- Dettagli del torneo --}}
        <div class="tournament-details">
            <h3>🏌️ Dettagli Torneo</h3>
            <div class="detail-item">
                <span class="detail-label">Nome:</span>
                <span class="detail-value">{{ $tournament->name }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Circolo:</span>
                <span class="detail-value">{{ $tournament->club->name }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date:</span>
                <span class="detail-value">
                    {{ $tournament->start_date->format('d/m/Y') }}
                    @if(!$tournament->start_date->isSameDay($tournament->end_date))
                        - {{ $tournament->end_date->format('d/m/Y') }}
                    @endif
                </span>
            </div>
            @if($tournament->club->address)
                <div class="detail-item">
                    <span class="detail-label">Indirizzo:</span>
                    <span class="detail-value">{{ $tournament->club->address }}</span>
                </div>
            @endif
            @if($tournament->tournamentType)
                <div class="detail-item">
                    <span class="detail-label">Categoria:</span>
                    <span class="detail-value">{{ $tournament->tournamentType->name }}</span>
                </div>
            @endif
        </div>

        {{-- Comitato di Gara (solo se ci sono assegnazioni multiple) --}}
        @if($tournament->assignments && $tournament->assignments->count() > 1)
            <div style="margin: 20px 0;">
                <h3 style="color: #1f2937; margin-bottom: 15px;">👥 Comitato di Gara</h3>

                <table class="assignments-table">
                    <thead>
                        <tr>
                            <th>Ruolo</th>
                            <th>Arbitro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tournament->assignments as $assign)
                            <tr>
                                <td>{{ $assign->role }}</td>
                                <td>{{ $assign->user->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($assignment->user)
            {{-- Singola assegnazione --}}
            <div class="tournament-details">
                <h3>👨‍⚖️ Assegnazione</h3>
                <div class="detail-item">
                    <span class="detail-label">Arbitro:</span>
                    <span class="detail-value">{{ $assignment->user->name }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ruolo:</span>
                    <span class="detail-value">{{ $assignment->role }}</span>
                </div>
                @if($assignment->user->phone)
                    <div class="detail-item">
                        <span class="detail-label">Telefono:</span>
                        <span class="detail-value">{{ $assignment->user->phone }}</span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Informazioni specifiche per il circolo --}}
        @if($isClub)
            <div class="club-specific">
                <h4>📧 Indirizzi per convocazione</h4>
                <p>Il Club interessato è pertanto invitato ad inviare tramite e-mail la necessaria convocazione del Comitato di Gara nonché, per conoscenza, ai seguenti indirizzi:</p>

                <ul style="margin: 15px 0; padding-left: 20px;">
                    <li><strong>Sezione Zonale Regole:</strong> szr{{ $tournament->club->zone->id ?? 1 }}@federgolf.it</li>
                    <li><strong>Ufficio Campionati:</strong> campionati@federgolf.it</li>
                </ul>

                <p style="margin-top: 15px;">Si prega di confermare la ricezione della presente comunicazione e di contattarci per qualsiasi necessità organizzativa.</p>
            </div>
        @endif

        {{-- Informazioni allegati --}}
        @if(!empty($attachments))
            <div class="attachments-info">
                <h4>📎 Documenti Allegati</h4>
                @foreach($attachments as $type => $path)
                    @if(file_exists($path))
                        <div class="attachment-item">
                            <span style="margin-right: 8px;">📄</span>
                            @if($type === 'convocation')
                                Convocazione Sezione Zonale Regole
                            @elseif($type === 'club_letter')
                                Template lettera per il circolo
                            @else
                                Documento allegato
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Firma --}}
        <div class="signature">
            Cordiali saluti,<br>
            <strong>Sezione Zonale Regole</strong><br>
            {{ config('app.name', 'Federazione Italiana Golf') }}
        </div>

        {{-- Informazioni di contatto --}}
        <div class="contact-info">
            <p><strong>Per informazioni:</strong></p>
            <p>
                📧 Email: {{ config('mail.from.address') }}<br>
                🌐 Web: www.federgolf.it
            </p>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>
            Questa comunicazione è stata inviata automaticamente dal sistema di gestione arbitri.<br>
            © {{ date('Y') }} Federazione Italiana Golf - Tutti i diritti riservati
        </p>
    </div>

</body>
</html>
