@props([
    'status' => true,
    'activeText' => 'Attivo',
    'inactiveText' => 'Non Attivo',
    'activeClass' => 'bg-green-100 text-green-800',
    'inactiveClass' => 'bg-red-100 text-red-800'
])

<span class="px-2 py-1 text-xs font-semibold rounded-full {{ $status ? $activeClass : $inactiveClass }}">
    {{ $status ? $activeText : $inactiveText }}
</span>
