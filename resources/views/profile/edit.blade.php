@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Profile Information -->
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <div class="max-w-xl">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h3>

                <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('patch')

                    <div>
                        <label for="name" class="block font-medium text-sm text-gray-700 mb-1">Name</label>
                        <input id="name" name="name" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div>
                        <label for="email" class="block font-medium text-sm text-gray-700 mb-1">Email</label>
                        <input id="email" name="email" type="email" class="w-full border border-gray-300 rounded-md px-3 py-2" value="{{ old('email', $user->email) }}" required>
                    </div>

                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save</button>
                </form>
            </div>
        </div>

        <!-- Update Password -->
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <div class="max-w-xl">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Password</h3>

                <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div>
                        <label for="current_password" class="block font-medium text-sm text-gray-700 mb-1">Current Password</label>
                        <input id="current_password" name="current_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <div>
                        <label for="password" class="block font-medium text-sm text-gray-700 mb-1">New Password</label>
                        <input id="password" name="password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block font-medium text-sm text-gray-700 mb-1">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save</button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
