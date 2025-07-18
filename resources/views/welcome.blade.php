<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Gestione Arbitri Golf') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="relative min-h-screen bg-gradient-to-b from-golf-green-50 to-golf-green-100">
            <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen">
                @if (Route::has('login'))
                    <div class="fixed top-0 right-0 px-6 py-4 sm:block z-10">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-golf-green-600 hover:text-golf-green-800 font-semibold">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-golf-green-600 hover:text-golf-green-800 font-semibold">Accedi</a>

                            {{-- @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="ml-4 text-golf-green-600 hover:text-golf-green-800 font-semibold">Registrati</a>
                            @endif --}}
                        @endauth
                    </div>
                @endif

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center pt-8 sm:pt-0">
                        <div class="flex items-center justify-center mb-6">
                            <img src="{{ asset('images/logo.png') }}" alt="Golf Arbitri Logo" class="h-20 w-auto">
                            <h1 class="ml-4 text-4xl font-bold text-golf-green-700">Gestione Arbitri Golf</h1>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg overflow-hidden w-full">
                            <div class="p-6 sm:p-8">
                                <h2 class="text-2xl font-bold text-golf-green-700 mb-6">Benvenuto nel sistema di gestione arbitri per tornei di golf</h2>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                    <div>
                                        <h3 class="text-xl font-semibold mb-3 text-golf-green-600">Come funziona</h3>
                                        <p class="text-gray-700 mb-4">
                                            Questo sistema permette la gestione completa degli arbitri di golf per i tornei in tutta Italia,
                                            semplificando la gestione delle disponibilità, le assegnazioni ai tornei e il monitoraggio delle attività.
                                        </p>
                                        <p class="text-gray-700">
                                            Ogni arbitro può indicare le proprie disponibilità e consultare le assegnazioni, mentre gli
                                            amministratori possono gestire tornei, circoli e assegnare gli arbitri in base alle loro qualifiche.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 class="text-xl font-semibold mb-3 text-golf-green-600">Caratteristiche principali</h3>
                                        <ul class="list-disc list-inside text-gray-700 space-y-2">
                                            <li>Gestione completa di tornei e circoli di golf</li>
                                            <li>Sistema di disponibilità per gli arbitri con notifiche</li>
                                            <li>Assegnazione degli arbitri per ruoli specifici</li>
                                            <li>Gestione delle zone geografiche</li>
                                            <li>Dashboard personalizzata per ogni tipo di utente</li>
                                            <li>Statistiche e reportistica avanzata</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-xl font-semibold mb-3 text-golf-green-600">Ruoli nel sistema</h3>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-golf-green-50 p-4 rounded-lg">
                                            <h4 class="font-semibold text-golf-green-700">Arbitri</h4>
                                            <p class="text-sm text-gray-700">Possono gestire le proprie disponibilità, visualizzare le assegnazioni e consultare il proprio curriculum di attività.</p>
                                        </div>

                                        <div class="bg-golf-green-50 p-4 rounded-lg">
                                            <h4 class="font-semibold text-golf-green-700">Amministratori di Zona</h4>
                                            <p class="text-sm text-gray-700">Gestiscono i tornei, i circoli e le assegnazioni degli arbitri nella propria zona di competenza.</p>
                                        </div>

                                        <div class="bg-golf-green-50 p-4 rounded-lg">
                                            <h4 class="font-semibold text-golf-green-700">Super Amministratori</h4>
                                            <p class="text-sm text-gray-700">Hanno accesso completo a tutte le funzionalità del sistema e possono gestire zone, arbitri e configurazioni.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-center">
                                    @auth
                                        <a href="{{ route('dashboard') }}" class="px-6 py-3 bg-golf-green-600 hover:bg-golf-green-700 text-white font-medium rounded-lg transition duration-150 ease-in-out">
                                            Vai alla dashboard
                                        </a>
                                    @else
                                        <a href="{{ route('login') }}" class="px-6 py-3 bg-golf-green-600 hover:bg-golf-green-700 text-white font-medium rounded-lg transition duration-150 ease-in-out">
                                            Accedi al sistema
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center mt-8 text-gray-600 text-sm">
                            <p>© {{ date('Y') }} Gestione Arbitri Golf. Tutti i diritti riservati.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
