@extends('layouts.super-admin')

@section('title', 'Modifica Categoria: ' . $tournamentCategory->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Modifica Categoria Torneo</h1>
                <p class="mt-2 text-gray-600">Modifica le impostazioni della categoria: {{ $tournamentCategory->name }}</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.tournament-categories.show', $tournamentCategory) }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Visualizza
                </a>
                <a href="{{ route('super-admin.tournament-categories.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Alert if category has tournaments --}}
    @if($tournamentCategory->tournaments()->exists())
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Attenzione:</strong> Questa categoria ha {{ $tournamentCategory->tournaments()->count() }} tornei associati.
                    Le modifiche influenzeranno tutti i tornei esistenti.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('super-admin.tournament-categories.update', $tournamentCategory) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('super-admin.tournament-categories._form')
        </div>

        {{-- Actions --}}
        <div class="flex justify-between">
            <div>
                @if(!$tournamentCategory->tournaments()->exists())
                <button type="button"
                        onclick="confirmDelete()"
                        class="px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Elimina Categoria
                </button>
                @endif
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('super-admin.tournament-categories.index') }}"
                   class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Salva Modifiche
                </button>
            </div>
        </div>
    </form>

    {{-- Category Stats --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Statistiche Categoria</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ $tournamentCategory->tournaments()->count() }}</div>
                <div class="text-sm text-gray-600">Tornei Totali</div>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $tournamentCategory->tournaments()->where('status', 'open')->count() }}</div>
                <div class="text-sm text-gray-600">Tornei Aperti</div>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $tournamentCategory->tournaments()->where('status', 'completed')->count() }}</div>
                <div class="text-sm text-gray-600">Tornei Completati</div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Form (hidden) --}}
<form id="delete-form" action="{{ route('super-admin.tournament-categories.destroy', $tournamentCategory) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
function confirmDelete() {
    if (confirm('Sei sicuro di voler eliminare questa categoria? Questa azione è irreversibile.')) {
        document.getElementById('delete-form').submit();
    }
}
</script>
@endpush
@endsection
