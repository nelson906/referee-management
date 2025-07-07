@props([
    'title' => '',
    'description' => '',
    'createRoute' => null,
    'createText' => 'Nuovo',
    'additionalActions' => ''
])

<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $title }}</h1>
        @if($description)
            <p class="mt-2 text-gray-600">{{ $description }}</p>
        @endif
    </div>
    <div class="flex space-x-4">
        @if($additionalActions)
            {!! $additionalActions !!}
        @endif

        @if($createRoute)
            <a href="{{ $createRoute }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                {{ $createText }}
            </a>
        @endif
    </div>
</div>
