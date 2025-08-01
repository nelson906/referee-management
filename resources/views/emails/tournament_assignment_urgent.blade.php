<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 URGENTE - Assegnazione Arbitri {{ $tournament_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff5f5;
        }
        .urgent-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .urgent-badge {
            background-color: #fef2f2;
            color: #dc2626;
            border: 2px solid #dc2626;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 18px;
            display: inline-block;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .title-urgent {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .subtitle-urgent {
            font-size: 16px;
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .urgent-notice {
            background-color: #fef2f2;
            border-left: 5px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .urgent-notice strong {
            color: #dc2626;
        }
        .tournament-info-urgent {
            background: linear-gradient(135deg, #fef3c7, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .referees-urgent {
            background-color: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .referee-urgent {
            background-color: white;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin: 10px 0;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .referee-name-urgent {
            font-weight: bold;
            font-size: 16px;
            color: #0c4a6e;
        }
        .referee-role-urgent {
            color: #0369a1;
            font-weight: 500;
        }
        .referee-email-urgent {
            color: #0ea5e9;
            font-size: 14px;
        }
        .action-required {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .action-required h3 {
            margin-top: 0;
            font-size: 20px;
        }
        .deadline {
            background-color: #fbbf24;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
        .contact-urgent {
            background-color: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .contact-urgent h4 {
            color: #047857;
            margin-top: 0;
        }
        .phone-number {
            font-size: 18px;
            font-weight: bold;
            color: #047857;
        }
        .footer-urgent {
            margin-top: 40px;
            padding: 20px;
            background-color: #f3f4f6;
            border-radius: 10px;
            text-align: center;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="urgent-header">
        <div class="urgent-badge">🚨 COMUNICAZIONE URGENTE 🚨</div>
        <h1 class="title-urgent">FEDERAZIONE ITALIANA GOLF</h1>
        <p class="subtitle-urgent">Assegnazione Arbitri - Richiesta Conferma Immediata</p>
    </div>

    <div class="content">
        <div class="urgent-notice">
            <strong>⚠️ ATTENZIONE:</strong> Questa comunicazione richiede una <strong>conferma immediata</strong> della vostra disponibilità.
        </div>

        <p><strong>Gentile {{ $recipient_name }},</strong></p>

        <p>Vi comunichiamo con <strong>carattere di urgenza</strong> l'assegnazione degli arbitri per il torneo:</p>

        <div class="tournament-info-urgent">
            <h3 style="margin-top: 0; color: #92400e;">🏆 {{ $tournament_name }}</h3>
            <p><strong>📅 Date:</strong> {{ $tournament_dates }}</p>
            <p><strong>🏌️ Circolo:</strong> {{ $club_name }}</p>
        </div>

        @if(!empty($referees) && count($referees) > 0)
        <div class="referees-urgent">
            <h3 style="margin-top: 0; color: #0c4a6e;">⚖️ Comitato di Gara Assegnato:</h3>

            @foreach($referees as $referee)
            <div class="referee-urgent">
                <div class="referee-name-urgent">{{ $referee['name'] }}</div>
                <div class="referee-role-urgent">{{ $referee['role'] }}</div>
                @if(!empty($referee['email']))
                <div class="referee-email-urgent">📧 {{ $referee['email'] }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <div class="action-required">
            <h3>🕐 AZIONE RICHIESTA</h3>
            <p>È <strong>NECESSARIA</strong> conferma immediata della vostra disponibilità</p>
            <div class="deadline">⏰ Scadenza conferma: ENTRO 24 ORE</div>
        </div>

        <div class="urgent-notice">
            <strong>IMPORTANTE:</strong> In mancanza di conferma entro i termini indicati, si procederà con assegnazioni alternative.
        </div>

        @if(isset($zone_email) || isset($club_email))
        <div class="contact-urgent">
            <h4>📞 Contatti per Conferma IMMEDIATA:</h4>
            @if(isset($zone_email))
            <p><strong>Sezione Zonale:</strong> <a href="mailto:{{ $zone_email }}" style="color: #047857;">{{ $zone_email }}</a></p>
            @endif
            @if(isset($club_email))
            <p><strong>Circolo Organizzatore:</strong> <a href="mailto:{{ $club_email }}" style="color: #047857;">{{ $club_email }}</a></p>
            @endif
            <div class="phone-number">📱 Per urgenze: 06-XXXXXXX</div>
        </div>
        @endif

        <div style="margin: 30px 0; text-align: center; font-weight: bold; color: #dc2626;">
            Si prega di dare <u>MASSIMA PRIORITÀ</u> a questa comunicazione
        </div>
    </div>

    <div class="footer-urgent">
        <p><strong>FEDERAZIONE ITALIANA GOLF</strong><br>
        Sistema Gestione Arbitri - Comunicazione Urgente</p>
        <p style="font-size: 12px;">
            Generato il {{ date('d/m/Y') }} alle {{ date('H:i') }}<br>
            Per informazioni urgenti: <a href="mailto:arbitri@federgolf.it">arbitri@federgolf.it</a>
        </p>
    </div>
</body>
</html>
