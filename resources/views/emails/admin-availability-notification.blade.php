<x-mail::message>
# Aggiornamento Disponibilità Arbitro

**Arbitro:** {{ $referee_name }} ({{ $referee_code }})<br>
**Livello:** {{ $referee_level }}<br>
**Zona:** {{ $zone }}<br>
**Data aggiornamento:** {{ $updated_at }}

@if($added_tournaments->count() > 0)
## ✅ NUOVE DISPONIBILITÀ:
@component('mail::table')
| Torneo | Date | Circolo |
|:-------|:-----|:--------|
@foreach($added_tournaments as $tournament)
| {{ $tournament->name }} | {{ $tournament->start_date->format('d/m') }}-{{ $tournament->end_date->format('d/m/Y') }} | {{ $tournament->club->name }} |
@endforeach
@endcomponent
@endif

@if($removed_tournaments->count() > 0)
## ❌ DISPONIBILITÀ RIMOSSE:
@component('mail::table')
| Torneo | Date | Circolo |
|:-------|:-----|:--------|
@foreach($removed_tournaments as $tournament)
| {{ $tournament->name }} | {{ $tournament->start_date->format('d/m') }}-{{ $tournament->end_date->format('d/m/Y') }} | {{ $tournament->club->name }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => route('admin.availabilities.index')])
Visualizza Disponibilità
@endcomponent

</x-mail::message>
