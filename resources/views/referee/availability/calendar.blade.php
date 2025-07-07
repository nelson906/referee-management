@extends('layouts.referee')

@section('title', 'Calendario Disponibilità')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Calendario Disponibilità</h1>
                <p class="mt-2 text-gray-600">Vista calendario dei tornei disponibili</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('referee.availability.index') }}"
                   class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    Vista Lista
                </a>
            </div>
        </div>

        {{-- Calendar Container - STESSO PATTERN DEL TOURNAMENT CALENDAR --}}
        <div id="availability-calendar-root"></div>
    </div>
</div>

<script>
    // Make data available to JavaScript - STESSO PATTERN
    window.availabilityCalendarData = {!! json_encode($calendarData) !!};
</script>

@vite(['resources/css/app.css', 'resources/css/calendar.css', 'resources/js/app.js', 'resources/js/availability-calendar.jsx'])
@endsection
