Gentile {{ $tournament->club->name }},

Vi comunichiamo gli arbitri assegnati per il torneo:
**{{ $tournament->name }}**
Date: {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}

Comitato di Gara:
@foreach($tournament->assignments as $assignment)
- {{ $assignment->user->name }} ({{ $assignment->role }})
@endforeach

In allegato trovate il facsimile della convocazione da inviare su carta intestata.

Cordiali saluti,
Sezione Zonale Regole {{ $tournament->zone->name }}
