@extends('layouts.referee')

@section('title', 'Il Mio Calendario')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Il Mio Calendario</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Le mie disponibilità e assegnazioni
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('referee.availability.index') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Vista Lista
                    </a>
                    <a href="{{ route('referee.assignments.index') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Le Mie Assegnazioni
                    </a>
                </div>
            </div>
        </div>

        {{-- Referee Calendar Container --}}
        <div id="referee-calendar-root"></div>
    </div>
</div>

{{-- Pass data to JavaScript --}}
<script>
    window.refereeCalendarData = @json($calendarData);
</script>
@endsection

@vite(['resources/js/app.js'])
