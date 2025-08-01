@extends('layouts.admin')

@section('title', 'Profilo')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Il mio Profilo</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profile Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Profilo</h3>

            <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('patch')

                <div>
                    <label for="name" class="block font-medium text-sm text-gray-700 mb-1">Nome</label>
                    <input id="name" name="name" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2" value="{{ old('name', $user->name) }}" required>
                </div>

                <div>
                    <label for="email" class="block font-medium text-sm text-gray-700 mb-1">Email</label>
                    <input id="email" name="email" type="email" class="w-full border border-gray-300 rounded-md px-3 py-2" value="{{ old('email', $user->email) }}" required>
                </div>

                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Salva</button>
            </form>
        </div>

        <!-- Update Password -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Cambia Password</h3>

            <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('put')

                <div>
                    <label for="current_password" class="block font-medium text-sm text-gray-700 mb-1">Password Attuale</label>
                    <input id="current_password" name="current_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label for="password" class="block font-medium text-sm text-gray-700 mb-1">Nuova Password</label>
                    <input id="password" name="password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label for="password_confirmation" class="block font-medium text-sm text-gray-700 mb-1">Conferma Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Salva</button>
            </form>
        </div>
    </div>
</div>
@endsection
