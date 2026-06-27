<x-filament-panels::page>
    @php($s = $this->season)

    @if (! ($s['has_season'] ?? false))
        <x-filament::section>
            <p>No active academic year is set. Create one under <strong>Academic Years</strong> and mark it current.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $s['year']['name'] }}</x-slot>
            <x-slot name="description">
                @if ($s['current_term'])
                    Currently in <strong>{{ $s['current_term']['name'] }}</strong>, sequence
                    {{ $s['current_sequence'] }} of 2
                    (overall evaluation {{ $s['global_sequence'] }} of 6).
                @elseif ($s['can_close_year'])
                    All three terms are closed — the year is ready to end.
                @endif
            </x-slot>

            {{-- Term stepper --}}
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                @foreach ($s['terms'] as $t)
                    @php($color = $t['status'] === 'active' ? '#2563eb' : ($t['status'] === 'closed' ? '#16a34a' : '#94a3b8'))
                    <div style="flex:1; min-width:170px; border:1.5px solid {{ $color }}; border-radius:12px; padding:14px 16px;">
                        <div style="font-weight:700; color:#0b1220;">{{ $t['name'] }}</div>
                        <div style="font-size:13px; color:{{ $color }}; margin-top:2px;">
                            @if ($t['status'] === 'active')
                                In progress · sequence {{ $t['current_sequence'] }}/2
                            @elseif ($t['status'] === 'closed')
                                &check; Closed{{ $t['closed_at'] ? ' · ' . \Illuminate\Support\Str::of($t['closed_at'])->before(' ') : '' }}
                            @else
                                Upcoming
                            @endif
                        </div>
                        @if ($t['status'] === 'closed' && $this->canManageSeason())
                            <div style="margin-top:8px;">
                                <x-filament::button size="xs" color="gray" wire:click="reopenTerm('{{ $t['id'] }}')">
                                    Reopen
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Actions --}}
            @if ($this->canManageSeason())
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:22px;">
                    @if ($s['can_open_next_sequence'])
                        <x-filament::button wire:click="openNextSequence" icon="heroicon-o-forward" color="gray">
                            Open next sequence
                        </x-filament::button>
                    @endif

                    @if ($s['can_advance'])
                        <x-filament::button
                            color="warning"
                            wire:click="advanceTerm"
                            wire:confirm="Close {{ $s['current_term']['name'] }} and open the next term? This computes results for the term (you can reopen it later)."
                        >
                            Close {{ $s['current_term']['name'] }} &amp; advance
                        </x-filament::button>
                    @endif

                    @if ($s['can_close_year'])
                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-flag"
                            wire:click="closeYear"
                            wire:confirm="End the academic year? This runs promotion deliberation for every stream and opens the next year. The current year is archived."
                        >
                            End academic year
                        </x-filament::button>
                    @endif
                </div>
            @else
                <p style="margin-top:18px; color:#64748b; font-size:13px;">
                    Only the principal can advance the season. You have view access.
                </p>
            @endif
        </x-filament::section>

        {{-- Year archive --}}
        <x-filament::section>
            <x-slot name="heading">Years</x-slot>
            <x-slot name="description">Past years are archived — their results and reports stay available.</x-slot>

            <div style="display:flex; flex-direction:column; gap:2px;">
                @foreach ($this->years as $y)
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                        <span style="font-weight:500;">{{ $y['name'] }}</span>
                        @if ($y['is_current'])
                            <x-filament::badge color="primary">Current</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Archived</x-filament::badge>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
