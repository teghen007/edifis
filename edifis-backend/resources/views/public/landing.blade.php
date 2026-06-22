<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDIFIS — AI School Management for Cameroon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass { background: rgba(255,255,255,.7); backdrop-filter: blur(10px); }
        .hero-grad { background: linear-gradient(135deg, #1e3a8a 0%, #2563EB 55%, #3b82f6 100%); }
        .glossy { box-shadow: 0 10px 30px -10px rgba(37,99,235,.35); }
    </style>
</head>
<body class="bg-white text-slate-800">
    <!-- Header -->
    <header class="hero-grad text-white sticky top-0 z-30 shadow-lg">
        <nav class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3z"/></svg>
                <span class="text-xl font-bold tracking-tight">EDIFIS</span>
            </a>
            <div class="hidden md:flex items-center gap-6 text-sm">
                <a href="#features" class="hover:text-blue-200">Features</a>
                <a href="#schools" class="hover:text-blue-200">Schools</a>
                <a href="#request" class="hover:text-blue-200">Onboard</a>
                <a href="/app.apk" class="hover:text-blue-200">Get the App</a>
                <a href="/staff/login" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition">Staff Login</a>
            </div>
        </nav>
    </header>

    <!-- Hero -->
    <section class="hero-grad text-white">
        <div class="max-w-4xl mx-auto text-center px-6 pt-20 pb-24">
            <span class="inline-block bg-white/15 text-blue-50 text-xs font-semibold px-3 py-1 rounded-full mb-5 tracking-wide">AI-POWERED · BILINGUAL · OFFLINE-READY</span>
            <h1 class="text-4xl md:text-6xl font-extrabold mb-5 leading-tight">School Management,<br>Reinvented for Cameroon</h1>
            <p class="text-lg md:text-xl text-blue-100 mb-9 max-w-2xl mx-auto">Coefficient-weighted report cards, fees, attendance, and an AI assistant for principals and parents — in English &amp; French. One platform, on and off campus.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="#request" class="bg-white text-blue-700 px-8 py-3.5 rounded-xl font-bold text-lg hover:bg-blue-50 transition glossy">Onboard Your School</a>
                <a href="/app.apk" class="border-2 border-white/70 text-white px-8 py-3.5 rounded-xl font-bold text-lg hover:bg-white/10 transition">Download the App</a>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="py-20 max-w-6xl mx-auto px-6">
        <h2 class="text-3xl md:text-4xl font-bold text-center text-slate-900 mb-3">Everything your school needs</h2>
        <p class="text-center text-slate-500 mb-14 max-w-2xl mx-auto">Built around how Cameroon secondary schools actually run — coefficients, sequences, mentions, conduct, and deliberation.</p>
        <div class="grid gap-7 md:grid-cols-3">
            @foreach ([
                ['📊', 'Report Cards', 'Coefficient-weighted averages, mention, conduct, class rank — instant branded PDF.'],
                ['🤖', 'AI Assistant', 'Principal VACUUM answers any question; parents get scoped answers about their own child.'],
                ['💰', 'Fees & Ledger', 'Fee structures per class, one-click billing, parent statements, MoMo-ready.'],
                ['✅', 'Attendance', 'Scoped to each teacher\'s classes — QR or manual, recorded instantly.'],
                ['🌍', 'Bilingual EN/FR', 'Report cards and AI in English or French — set per school.'],
                ['📥', 'Excel-first', 'Teachers fill marks in Excel they already know. Works offline, upload when connected.'],
            ] as [$icon, $title, $desc])
                <div class="rounded-2xl p-7 bg-gradient-to-b from-blue-50 to-white border border-blue-100 hover:shadow-lg transition">
                    <div class="text-3xl mb-4">{{ $icon }}</div>
                    <h3 class="font-bold text-lg text-slate-900 mb-2">{{ $title }}</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Schools using EDIFIS -->
    <section id="schools" class="bg-gradient-to-b from-white to-blue-50 py-20">
        <div class="max-w-6xl mx-auto px-6">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-slate-900 mb-3">Schools on EDIFIS</h2>
            <p class="text-center text-slate-500 mb-14">Trusted by schools across Cameroon.</p>
            @if (($schools ?? collect())->isEmpty())
                <p class="text-center text-slate-400">Be the first school on EDIFIS — <a href="#request" class="text-blue-600 font-semibold">onboard below</a>.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($schools as $s)
                        <a href="https://{{ $s->domain }}" class="block rounded-2xl bg-white border border-blue-100 p-6 hover:shadow-xl hover:-translate-y-0.5 transition glossy">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="h-11 w-11 rounded-xl hero-grad flex items-center justify-center text-white font-bold">
                                    {{ strtoupper(substr($s->school_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 leading-tight">{{ $s->school_name }}</div>
                                    @if ($s->school_location)
                                        <div class="text-xs text-slate-500">{{ $s->school_location }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm text-blue-600 font-medium">{{ $s->domain }} →</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Onboard -->
    <section id="request" class="py-20">
        <div class="max-w-xl mx-auto px-6">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-slate-900 mb-2">Onboard Your School</h2>
            <p class="text-center text-slate-500 mb-9">Tell us about your school and we'll set you up on EDIFIS with your own branded site &amp; app.</p>

            <form id="onboard-form" class="bg-white rounded-2xl shadow-xl border border-blue-100 p-7 space-y-4">
                <input id="school_name" placeholder="School Name *" required class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="school_code" placeholder="School Code (e.g. pssnkwen) *" required class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="location" placeholder="Location / Town" class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="contact_name" placeholder="Principal / Contact Name *" required class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="contact_email" type="email" placeholder="Contact Email *" required class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="contact_phone" placeholder="Contact Phone" class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <input id="estimated_students" type="number" placeholder="Estimated Students" class="w-full border border-slate-200 rounded-xl p-3.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                <button type="submit" class="w-full hero-grad text-white py-3.5 rounded-xl font-bold text-lg hover:opacity-95 transition glossy">
                    Submit Request
                </button>
                <p id="form-status" class="text-center text-sm hidden"></p>
            </form>

            <script>
                document.getElementById('onboard-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const status = document.getElementById('form-status');
                    status.className = 'text-center text-sm';
                    status.textContent = 'Submitting...';
                    status.classList.remove('hidden');
                    const res = await fetch('/api/onboarding/request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            school_name: document.getElementById('school_name').value,
                            school_code: document.getElementById('school_code').value,
                            location: document.getElementById('location').value,
                            contact_name: document.getElementById('contact_name').value,
                            contact_email: document.getElementById('contact_email').value,
                            contact_phone: document.getElementById('contact_phone').value,
                            estimated_students: parseInt(document.getElementById('estimated_students').value) || null,
                        }),
                    });
                    const data = await res.json();
                    status.textContent = data.message || (res.ok ? 'Submitted!' : 'Something went wrong.');
                    status.classList.add(res.ok ? 'text-green-600' : 'text-red-600');
                });
            </script>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
