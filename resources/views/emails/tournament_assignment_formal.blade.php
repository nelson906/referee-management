<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocazione Ufficiale - {{ $tournament_name }}</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            color: #000;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background-color: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 30px;
            margin-bottom: 40px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 0;
        }
        .subtitle {
            font-size: 18px;
            margin: 10px 0 0 0;
            font-style: italic;
        }
        .document-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            text-decoration: underline;
            margin: 30px 0;
            letter-spacing: 1px;
        }
        .formal-greeting {
            font-size: 16px;
            margin: 30px 0;
            text-align: justify;
        }
        .tournament-details {
            border: 2px solid #000;
            padding: 20px;
            margin: 30px 0;
            background-color: #f9f9f9;
        }
        .referees-formal {
            margin: 30px 0;
        }
        .referee-formal {
            margin: 15px 0;
            padding: 10px;
            border-left: 3px solid #000;
            padding-left: 20px;
        }
        .referee-name-formal {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
        }
        .referee-role-formal {
            font-style: italic;
            margin-top: 5px;
        }
        .formal-text {
            text-align: justify;
            margin: 25px 0;
            line-height: 2;
        }
        .signature-formal {
            margin-top: 50px;
            text-align: right;
            font-weight: bold;
        }
        .footer-formal {
            margin-top: 60px;
            border-top: 1px solid #000;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">FEDERAZIONE ITALIANA GOLF</h1>
        <p class="subtitle">Convocazione Ufficiale</p>
    </div>

    <div class="document-title">
        OGGETTO: {{ strtoupper($tournament_name) }}
    </div>

    <div class="formal-greeting">
        <strong>Al Circolo {{ $club_name }}</strong><br>
        e per conoscenza:<br>
        @if(!empty($referees))
            @foreach($referees as $referee)
                {{ $referee['name'] }},<br>
            @endforeach
        @endif
        Comitato Regionale di competenza<br>
        Responsabile Attività della Zona
    </div>

    <div class="tournament-details">
        <strong>MANIFESTAZIONE:</strong> {{ $tournament_name }}<br>
        <strong>DATE:</strong> {{ $tournament_dates }}<br>
        <strong>CIRCOLO ORGANIZZATORE:</strong> {{ $club_name }}
    </div>

    <div class="formal-text">
        Si comunica la composizione del Comitato di Gara per la manifestazione in oggetto come individuata da questa Sezione Zonale Regole per la successiva convocazione da parte di codesto Circolo:
    </div>

    @if(!empty($referees))
    <div class="referees-formal">
        <strong>COMITATO DI GARA:</strong>
        @foreach($referees as $referee)
        <div class="referee-formal">
            <div class="referee-name-formal">{{ $referee['name'] }}</div>
            <div class="referee-role-formal">{{ $referee['role'] }}</div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="formal-text">
        <em>I suddetti componenti possono prendere decisioni definitive sul campo e fuori.</em>
    </div>

    <div class="formal-text">
        Il Comitato e gli Osservatori sono tenuti a presenziare dalle ore 9.00 del giorno precedente l'inizio della manifestazione sino al termine della stessa o secondo le decisioni che verranno direttamente comunicate dal Direttore di Torneo.
    </div>

    <div class="formal-text">
        Si ricorda che il Circolo Organizzatore rimborserà le spese vive di vitto e di viaggio al Direttore di Torneo e agli Arbitri convocati per la Gara. Le spese di alloggio saranno altresì rimborsate agli stessi soggetti su specifica loro motivata richiesta di necessità di alloggio. Agli Osservatori saranno rimborsate esclusivamente le spese di vitto.
    </div>

    <div class="formal-text">
        Il rimborso sarà effettuato sulla base della nota spese emessa dal singolo soggetto. Tutte le spese sono rimborsate nei limiti previsti dalla FIG e indicati nelle "Linee guida trasferte e rimborsi spese" annualmente pubblicate.
    </div>

    @if(isset($zone_email) || isset($club_email))
    <div class="formal-text">
        Si prega di confermare la propria presenza sia alla Sezione Zonale Regole di competenza
        @if(isset($zone_email))
        ({{ $zone_email }})
        @endif
        @if(isset($club_email))
        sia al Circolo Organizzatore ({{ $club_email }})
        @endif
        .
    </div>
    @endif

    <div class="signature-formal">
        Il Responsabile SZR<br>
        <strong>Federazione Italiana Golf</strong>
    </div>

    <div class="footer-formal">
        Documento generato dal Sistema di Gestione Tornei FIG<br>
        {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>
