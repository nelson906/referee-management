<x-mail::message>
# Conferma aggiornamento disponibilità

Gentile {{ $referee_name }},

La tua disponibilità è stata aggiornata con successo.

## Riepilogo modifiche:

@if($added_count > 0)
### ✅ Nuove disponibilità aggiunte ({{ $added_count }}):
@foreach($added_tournaments as $tournament)
- **{{ $tournament->name }}**
  - Date: {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
  - Circolo: {{ $tournament->club->name }}
@endforeach
@endif

@if($removed_count > 0)
### ❌ Disponibilità rimosse ({{ $removed_count }}):
@foreach($removed_tournaments as $tournament)
- {{ $tournament->name }} ({{ $tournament->start_date->format('d/m/Y') }})
@endforeach
@endif

**Totale tornei con disponibilità: {{ $total_availabilities }}**

Puoi sempre modificare le tue disponibilità accedendo al sistema.

Cordiali saluti,<br>
{{ config('app.name') }}
</x-mail::message>
