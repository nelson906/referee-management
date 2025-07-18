@extends('layouts.super-admin')

@section('title', 'Impostazioni Sistema')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Impostazioni Sistema</h1>
                <p class="mt-2 text-gray-600">Gestisci le configurazioni globali del sistema</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="clearCache()"
                        class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Svuota Cache
                </button>
                <button onclick="optimizeSystem()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Ottimizza Sistema
                </button>
            </div>
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

    {{-- System Info Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">PHP Version</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $systemInfo['php_version'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Database</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $systemInfo['database_size'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Storage</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $systemInfo['storage_used'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Log Files</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $systemInfo['log_size'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Settings Form --}}
    <form action="{{ route('super-admin.settings.update') }}" method="POST" class="space-y-8">
        @csrf

        {{-- Application Settings --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Impostazioni Applicazione</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="app_name" class="block text-sm font-medium text-gray-700">
                        Nome Applicazione <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="app_name" id="app_name"
                           value="{{ old('app_name', $settings['app_name']) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('app_name') border-red-500 @enderror"
                           required>
                    @error('app_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="app_timezone" class="block text-sm font-medium text-gray-700">
                        Timezone <span class="text-red-500">*</span>
                    </label>
                    <select name="app_timezone" id="app_timezone"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('app_timezone') border-red-500 @enderror"
                            required>
                        <option value="Europe/Rome" {{ $settings['app_timezone'] === 'Europe/Rome' ? 'selected' : '' }}>Europe/Rome</option>
                        <option value="UTC" {{ $settings['app_timezone'] === 'UTC' ? 'selected' : '' }}>UTC</option>
                        <option value="Europe/London" {{ $settings['app_timezone'] === 'Europe/London' ? 'selected' : '' }}>Europe/London</option>
                        <option value="America/New_York" {{ $settings['app_timezone'] === 'America/New_York' ? 'selected' : '' }}>America/New_York</option>
                    </select>
                    @error('app_timezone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="app_locale" class="block text-sm font-medium text-gray-700">
                        Lingua <span class="text-red-500">*</span>
                    </label>
                    <select name="app_locale" id="app_locale"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('app_locale') border-red-500 @enderror"
                            required>
                        <option value="it" {{ $settings['app_locale'] === 'it' ? 'selected' : '' }}>Italiano</option>
                        <option value="en" {{ $settings['app_locale'] === 'en' ? 'selected' : '' }}>English</option>
                        <option value="fr" {{ $settings['app_locale'] === 'fr' ? 'selected' : '' }}>Français</option>
                        <option value="de" {{ $settings['app_locale'] === 'de' ? 'selected' : '' }}>Deutsch</option>
                    </select>
                    @error('app_locale')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="session_lifetime" class="block text-sm font-medium text-gray-700">
                        Durata Sessione (minuti) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="session_lifetime" id="session_lifetime"
                           value="{{ old('session_lifetime', $settings['session_lifetime']) }}"
                           min="1" max="1440"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('session_lifetime') border-red-500 @enderror"
                           required>
                    @error('session_lifetime')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="system_debug" id="system_debug" value="1"
                           {{ old('system_debug', $settings['system_debug']) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="system_debug" class="ml-2 text-sm text-gray-700">
                        Modalità Debug (solo per sviluppo)
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="system_maintenance" id="system_maintenance" value="1"
                           {{ old('system_maintenance', $settings['system_maintenance']) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="system_maintenance" class="ml-2 text-sm text-gray-700">
                        Modalità Manutenzione
                    </label>
                </div>
            </div>
        </div>

        {{-- Email Settings --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Impostazioni Email</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="mail_driver" class="block text-sm font-medium text-gray-700">
                        Driver Mail <span class="text-red-500">*</span>
                    </label>
                    <select name="mail_driver" id="mail_driver"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('mail_driver') border-red-500 @enderror"
                            required>
                        <option value="smtp" {{ $settings['mail_driver'] === 'smtp' ? 'selected' : '' }}>SMTP</option>
                        <option value="sendmail" {{ $settings['mail_driver'] === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                        <option value="mail" {{ $settings['mail_driver'] === 'mail' ? 'selected' : '' }}>PHP Mail</option>
                        <option value="log" {{ $settings['mail_driver'] === 'log' ? 'selected' : '' }}>Log (Test)</option>
                    </select>
                    @error('mail_driver')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="smtp_settings" style="{{ $settings['mail_driver'] !== 'smtp' ? 'display: none;' : '' }}">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="mail_host" class="block text-sm font-medium text-gray-700">
                                Host SMTP
                            </label>
                            <input type="text" name="mail_host" id="mail_host"
                                   value="{{ old('mail_host', $settings['mail_host']) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('mail_host') border-red-500 @enderror">
                        </div>

                        <div>
                            <label for="mail_port" class="block text-sm font-medium text-gray-700">
                                Porta
                            </label>
                            <input type="number" name="mail_port" id="mail_port"
                                   value="{{ old('mail_port', $settings['mail_port']) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('mail_port') border-red-500 @enderror">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="mail_from_address" class="block text-sm font-medium text-gray-700">
                        Indirizzo Mittente <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="mail_from_address" id="mail_from_address"
                           value="{{ old('mail_from_address', $settings['mail_from_address']) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('mail_from_address') border-red-500 @enderror"
                           required>
                    @error('mail_from_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="mail_from_name" class="block text-sm font-medium text-gray-700">
                        Nome Mittente <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="mail_from_name" id="mail_from_name"
                           value="{{ old('mail_from_name', $settings['mail_from_name']) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('mail_from_name') border-red-500 @enderror"
                           required>
                    @error('mail_from_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- System Settings --}}
        <div class="bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Impostazioni Sistema</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="cache_driver" class="block text-sm font-medium text-gray-700">
                        Driver Cache <span class="text-red-500">*</span>
                    </label>
                    <select name="cache_driver" id="cache_driver"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cache_driver') border-red-500 @enderror"
                            required>
                        <option value="file" {{ $settings['cache_driver'] === 'file' ? 'selected' : '' }}>File</option>
                        <option value="database" {{ $settings['cache_driver'] === 'database' ? 'selected' : '' }}>Database</option>
                        <option value="redis" {{ $settings['cache_driver'] === 'redis' ? 'selected' : '' }}>Redis</option>
                        <option value="memcached" {{ $settings['cache_driver'] === 'memcached' ? 'selected' : '' }}>Memcached</option>
                    </select>
                </div>

                <div>
                    <label for="log_level" class="block text-sm font-medium text-gray-700">
                        Livello Log <span class="text-red-500">*</span>
                    </label>
                    <select name="log_level" id="log_level"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('log_level') border-red-500 @enderror"
                            required>
                        <option value="debug" {{ $settings['log_level'] === 'debug' ? 'selected' : '' }}>Debug</option>
                        <option value="info" {{ $settings['log_level'] === 'info' ? 'selected' : '' }}>Info</option>
                        <option value="warning" {{ $settings['log_level'] === 'warning' ? 'selected' : '' }}>Warning</option>
                        <option value="error" {{ $settings['log_level'] === 'error' ? 'selected' : '' }}>Error</option>
                    </select>
                </div>

                <div>
                    <label for="api_rate_limit" class="block text-sm font-medium text-gray-700">
                        Limite API (req/ora) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="api_rate_limit" id="api_rate_limit"
                           value="{{ old('api_rate_limit', $settings['api_rate_limit']) }}"
                           min="1" max="10000"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('api_rate_limit') border-red-500 @enderror"
                           required>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="backup_enabled" id="backup_enabled" value="1"
                           {{ old('backup_enabled', $settings['backup_enabled']) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="backup_enabled" class="ml-2 text-sm text-gray-700">
                        Abilita Backup Automatici
                    </label>
                </div>

                <div id="backup_settings" style="{{ !$settings['backup_enabled'] ? 'display: none;' : '' }}">
                    <label for="backup_frequency" class="block text-sm font-medium text-gray-700">
                        Frequenza Backup
                    </label>
                    <select name="backup_frequency" id="backup_frequency"
                            class="mt-1 block w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="daily" {{ $settings['backup_frequency'] === 'daily' ? 'selected' : '' }}>Giornaliero</option>
                        <option value="weekly" {{ $settings['backup_frequency'] === 'weekly' ? 'selected' : '' }}>Settimanale</option>
                        <option value="monthly" {{ $settings['backup_frequency'] === 'monthly' ? 'selected' : '' }}>Mensile</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <button type="button" onclick="window.location.reload()"
                    class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Ripristina
            </button>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Salva Impostazioni
            </button>
        </div>
    </form>

    {{-- System Information --}}
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Sistema</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Software</h4>
                <ul class="space-y-1 text-gray-600">
                    <li><strong>Laravel:</strong> {{ $systemInfo['laravel_version'] }}</li>
                    <li><strong>PHP:</strong> {{ $systemInfo['php_version'] }}</li>
                    <li><strong>Server:</strong> {{ $systemInfo['server_software'] }}</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Risorse</h4>
                <ul class="space-y-1 text-gray-600">
                    <li><strong>Memoria PHP:</strong> {{ $systemInfo['memory_usage'] }}</li>
                    <li><strong>Storage:</strong> {{ $systemInfo['storage_used'] }}</li>
                    <li><strong>Log:</strong> {{ $systemInfo['log_size'] }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Toggle SMTP settings
document.getElementById('mail_driver').addEventListener('change', function() {
    const smtpSettings = document.getElementById('smtp_settings');
    if (this.value === 'smtp') {
        smtpSettings.style.display = 'block';
    } else {
        smtpSettings.style.display = 'none';
    }
});

// Toggle backup settings
document.getElementById('backup_enabled').addEventListener('change', function() {
    const backupSettings = document.getElementById('backup_settings');
    if (this.checked) {
        backupSettings.style.display = 'block';
    } else {
        backupSettings.style.display = 'none';
    }
});

// Clear cache
function clearCache() {
    if (confirm('Sei sicuro di voler svuotare la cache?')) {
        fetch('{{ route("super-admin.settings.clear-cache") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            alert('Errore durante la richiesta');
        });
    }
}

// Optimize system
function optimizeSystem() {
    if (confirm('Vuoi ottimizzare il sistema? Questo potrebbe richiedere alcuni minuti.')) {
        fetch('{{ route("super-admin.settings.optimize") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            alert('Errore durante la richiesta');
        });
    }
}
</script>
@endpush
@endsection
