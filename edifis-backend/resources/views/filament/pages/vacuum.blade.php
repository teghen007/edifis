<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        {{-- Query Tab (Co-pilot) --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold mb-4">AI Co-pilot — Read-only</h2>
            <form wire:submit="submitQuery">
                {{ $this->queryForm }}
                <x-filament::button type="submit" class="mt-3">Ask</x-filament::button>
            </form>

            @if ($queryResult)
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded">
                    <p class="font-medium">{{ $queryResult['answer'] ?? 'No answer returned.' }}</p>
                    @if (! empty($queryResult['records']))
                        <p class="text-sm text-gray-500 mt-2">{{ count($queryResult['records']) }} records</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Command Tab --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold mb-4">Command Authority — Audited</h2>
            <form wire:submit="submitCommand">
                {{ $this->commandForm }}
                <x-filament::button type="submit" color="danger" class="mt-3">Execute Command</x-filament::button>
            </form>

            @if ($commandResult)
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded">
                    <p class="text-sm text-gray-500">Command recorded in audit trail.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
