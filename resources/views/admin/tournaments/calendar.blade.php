@extends('layouts.admin')

@section('title', 'Calendario Tornei')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/calendar.css') }}">
@endpush

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Calendario Tornei</h1>
            <p class="mt-2 text-gray-600">Visualizza e gestisci i tornei in formato calendario</p>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('admin.tournaments.index') }}"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Lista Tornei
            </a>
            <a href="{{ route('admin.tournaments.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nuovo Torneo
            </a>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Successo!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errore!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- Calendar Container --}}
    <div id="tournament-calendar-root" class="bg-white rounded-lg shadow">
        {{-- Fallback content while React loads --}}
        <div class="flex items-center justify-center h-64">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
                <p class="mt-4 text-gray-600">Caricamento calendario...</p>
            </div>
        </div>
    </div>
</div>

{{-- Pass data to JavaScript --}}
<script>
    window.calendarData = @json($calendarData);
</script>
@endsection

@push('scripts')
    {{-- Include React calendar component --}}
    @vite(['resources/js/calendar.jsx'])
@endpush
