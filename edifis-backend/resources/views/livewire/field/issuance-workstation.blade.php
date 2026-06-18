<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-2xl font-bold text-green-800 mb-4">Issuance Workstation</h1>

    @if ($issuedResult)
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
            <h2 class="text-lg font-semibold text-green-800 mb-2">Issued — Batch {{ $batchId }}</h2>
            <p class="text-sm text-green-600 mb-2">{{ count($issuedResult['events'] ?? []) }} items · {{ count($issuedResult['posted'] ?? []) }} debits posted</p>
            <button wire:click="$set('issuedResult', null)"
                    class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                New Issuance
            </button>
        </div>
    @endif

    @if ($lastError)
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg p-3 mb-4">
            {{ $lastError }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-4 mb-4">
        {{-- Student ID Input --}}
        <input type="text" wire:model="studentId"
               placeholder="Scan or type Student ID..."
               class="w-full border-2 border-green-300 rounded-lg p-3 text-lg mb-4"
               autofocus>

        {{-- Rubric Checklist --}}
        <h3 class="font-semibold mb-2">Catalogue Items</h3>
        <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
            @foreach ($this->catalogueItems as $item)
                <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-green-50 cursor-pointer">
                    <input type="checkbox"
                           wire:model.live="selectedItems"
                           value="{{ $item->id }}"
                           class="w-5 h-5 text-green-600 rounded">
                    <span class="flex-1">{{ $item->name }}</span>
                    <span class="text-sm font-mono text-gray-500">{{ number_format($item->cost) }} XAF</span>
                </label>
            @endforeach
        </div>

        {{-- Running Total --}}
        <div class="bg-gray-100 rounded-lg p-3 mb-4 text-center">
            <span class="text-sm text-gray-500">Running Total</span>
            <p class="text-2xl font-bold text-green-800">{{ number_format($this->runningTotal) }} XAF</p>
        </div>

        {{-- Signature Pad — real canvas capture --}}
        <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 mb-4"
             x-data="{
                drawing: false,
                clear() { const c = $refs.canvas; c.getContext('2d').clearRect(0,0,c.width,c.height); $wire.set('signatureData', ''); },
                save() { $wire.set('signatureData', $refs.canvas.toDataURL('image/png')); $refs.canvas.classList.add('ring-2','ring-green-400'); }
             }"
             x-init="
                const c = $refs.canvas;
                c.width = c.parentElement.offsetWidth - 32;
                c.height = 120;
                const ctx = c.getContext('2d');
                ctx.strokeStyle = '#1B5E20';
                ctx.lineWidth = 2;
                c.addEventListener('mousedown', e => { ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); drawing = true; });
                c.addEventListener('mousemove', e => { if (!drawing) return; ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); });
                c.addEventListener('mouseup', () => { drawing = false; save(); });
                c.addEventListener('touchstart', e => { e.preventDefault(); const t = e.touches[0]; ctx.beginPath(); ctx.moveTo(t.clientX - c.getBoundingClientRect().left, t.clientY - c.getBoundingClientRect().top); drawing = true; });
                c.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const t = e.touches[0]; ctx.lineTo(t.clientX - c.getBoundingClientRect().left, t.clientY - c.getBoundingClientRect().top); ctx.stroke(); });
                c.addEventListener('touchend', () => { drawing = false; save(); });
             ">
            <canvas x-ref="canvas" class="w-full border border-gray-200 rounded-lg cursor-crosshair"
                    style="touch-action: none;"></canvas>
            <div class="flex justify-between mt-2">
                <span class="text-xs text-gray-400">Sign above with mouse or touch</span>
                <button type="button" x-on:click="clear()" class="text-xs text-red-500 hover:underline">Clear</button>
            </div>
        </div>

        <button wire:click="issue"
                class="w-full bg-green-700 text-white py-3 rounded-lg font-bold text-lg disabled:opacity-50"
                {{ empty($selectedItems) ? 'disabled' : '' }}>
            Issue Items ({{ count($selectedItems) }} selected)
        </button>
    </div>
</div>
