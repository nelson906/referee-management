@extends('layouts.super-admin')

@section('title', 'Nuova Tipo Gara')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Nuova Tipo Gara</h1>
                <p class="mt-2 text-gray-600">Crea una nuova categoria per organizzare i tornei</p>
            </div>
            <a href="{{ route('super-admin.tournament-types.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('super-admin.tournament-types.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('super-admin.tournament-types._form')
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('super-admin.tournament-types.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Crea Categoria
            </button>
        </div>
    </form>

    {{-- Help Box --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Guida alla creazione delle categorie</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
            <div>
                <h4 class="font-medium text-gray-900 mb-2">🏌️ Categorie Zonali</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Visibili solo alle zone selezionate</li>
                    <li>Per tornei locali e regionali</li>
                    <li>Es: Gara Sociale, Coppa del Circolo</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">🏆 Categorie Nazionali</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Visibili a tutte le zone</li>
                    <li>Per tornei federali e major</li>
                    <li>Es: Open Nazionali, Campionati</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">📋 Livelli Arbitro</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li><strong>Aspirante/1° Livello:</strong> Tornei base</li>
                    <li><strong>Regionale:</strong> Tornei regionali</li>
                    <li><strong>Nazionale/Int.:</strong> Tornei maggiori</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">🎨 Codici Suggeriti</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li><strong>T18:</strong> 18 buche</li>
                    <li><strong>GN-36/54/72:</strong> Gare nazionali</li>
                    <li><strong>CI:</strong> Coppa Italia</li>
                    <li><strong>CNZ/TNZ:</strong> Campionati/Trofei</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
