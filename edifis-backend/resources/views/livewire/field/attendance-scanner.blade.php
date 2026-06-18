<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-2xl font-bold text-green-800 mb-4">Attendance Scanner</h1>

    @if (! $sessionOpen)
        {{-- Open Session Form --}}
        <div class="bg-white rounded-xl shadow p-6 mb-4">
            <h2 class="text-lg font-semibold mb-3">Open Session</h2>
            <div class="space-y-3">
                <input type="text" wire:model="classId" placeholder="Class ID"
                       class="w-full border rounded-lg p-2">
                <input type="text" wire:model="subjectId" placeholder="Subject ID"
                       class="w-full border rounded-lg p-2">
                <select wire:model="period" class="w-full border rounded-lg p-2">
                    <option value="AM">Morning (AM)</option>
                    <option value="PM">Afternoon (PM)</option>
                </select>
                <input type="number" wire:model="headcount" placeholder="Headcount (optional)"
                       class="w-full border rounded-lg p-2" min="0">
                <button wire:click="openSession"
                        class="w-full bg-green-700 text-white py-3 rounded-lg font-bold text-lg">
                    Open Session
                </button>
            </div>
        </div>
    @else
        {{-- Active Session --}}
        <div class="bg-white rounded-xl shadow p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <span class="text-sm text-gray-500">Session: {{ $sessionId }}</span>
                <span class="text-lg font-bold">
                    Scanned: {{ $tally['scanned'] ?? 0 }}
                    @if($tally['headcount'])
                        / {{ $tally['headcount'] }}
                    @endif
                </span>
            </div>

            {{-- QR Scan Input --}}
            <div class="mb-3">
                <input type="text" wire:model="scanStudentId"
                       wire:keydown.enter="scan"
                       placeholder="Scan or type Student ID..."
                       class="w-full border-2 border-green-300 rounded-lg p-3 text-lg"
                       autofocus>
            </div>

            {{-- Camera QR Scan (html5-qrcode) --}}
            <div x-data="{ scanning: false, scanner: null }"
                 x-init="
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/html5-qrcode';
                    script.onload = () => {
                        scanner = new Html5Qrcode('qr-reader');
                    };
                    document.head.appendChild(script);
                 "
                 class="mb-3">
                <button type="button" x-on:click="
                    if (scanning) { scanner.stop(); scanning = false; return; }
                    scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 250 },
                        (text) => { $wire.set('scanStudentId', text); $wire.call('scan'); scanner.stop(); scanning = false; },
                        () => {}
                    ).catch(() => alert('Camera not available — use manual entry.'));
                    scanning = true;
                " class="w-full bg-blue-600 text-white py-2 rounded-lg text-sm font-medium">
                    <span x-show="!scanning">📷 Open Camera Scanner</span>
                    <span x-show="scanning">⏹ Stop Scanner</span>
                </button>
                <div id="qr-reader" class="mt-2" style="max-width: 100%;"></div>
            </div>

            {{-- Scan Source Toggle --}}
            <div class="flex gap-2 mb-3">
                <button wire:click="$set('scanSource', 'qr_scan')"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                               {{ $scanSource === 'qr_scan' ? 'bg-green-600 text-white' : 'bg-gray-200' }}">
                    QR Scan
                </button>
                <button wire:click="$set('scanSource', 'manual_override')"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                               {{ $scanSource === 'manual_override' ? 'bg-amber-500 text-white' : 'bg-gray-200' }}">
                    Override
                </button>
            </div>

            @if ($scanSource === 'manual_override')
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3">
                    <label class="text-sm font-medium text-amber-800">Override Reason (required)</label>
                    <input type="text" wire:model="overrideReason"
                           placeholder="Present but forgot ID card..."
                           class="w-full border border-amber-300 rounded-lg p-2 mt-1">
                </div>
            @endif

            @if ($lastScanStatus === 'replay')
                <div class="bg-blue-50 text-blue-700 rounded-lg p-2 mb-3 text-sm">
                    This student was already scanned this session.
                </div>
            @endif

            <button wire:click="close"
                    class="w-full bg-red-600 text-white py-2 rounded-lg font-medium">
                Close Session
            </button>
        </div>

        {{-- Recent Scans --}}
        @if ($this->recentEvents->count())
            <div class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-2">Recent Scans</h3>
                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($this->recentEvents as $event)
                        <div class="flex justify-between text-sm py-1 border-b">
                            <span class="font-mono text-xs">{{ $event->student_id }}</span>
                            <span class="text-gray-500">{{ $event->scanned_at?->format('H:i:s') }}</span>
                            <span class="px-2 rounded text-xs
                                {{ $event->status === 'present' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $event->status }}
                            </span>
                            @if ($event->status === 'present')
                                <button wire:click="voidScan('{{ $event->id }}', 'Corrected by teacher')"
                                        class="text-red-500 text-xs hover:underline">Void</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
