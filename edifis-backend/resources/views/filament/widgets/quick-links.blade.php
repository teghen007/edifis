<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Quick access</x-slot>
        <x-slot name="description">Jump straight to where you work most.</x-slot>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach ($this->getLinks() as $link)
                <a href="{{ $link['url'] }}"
                   class="group flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 transition hover:border-primary-500 hover:shadow-md">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 transition group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/10">
                        <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
