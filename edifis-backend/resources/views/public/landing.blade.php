<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDIFIS — School Management Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white">
    <header class="bg-green-800 text-white">
        <nav class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3z"/></svg>
                <span class="text-xl font-bold">EDIFIS</span>
            </div>
            <div class="hidden md:flex gap-4 text-sm">
                <a href="#features" class="hover:text-green-200">Features</a>
                <a href="#request" class="hover:text-green-200">Onboard Your School</a>
                <a href="/staff/login" class="bg-white text-green-800 px-3 py-1 rounded font-medium">Staff Login</a>
            </div>
        </nav>
    </header>

    <section class="bg-green-700 text-white py-20">
        <div class="max-w-4xl mx-auto text-center px-4">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">School Management,<br>Built for Cameroon</h1>
            <p class="text-xl text-green-100 mb-8">Enrolment, fees, attendance, marks, textbooks — one platform. Works on and off campus.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="#request" class="bg-white text-green-800 px-8 py-3 rounded-lg font-bold text-lg hover:bg-green-50">Onboard Your School</a>
                <a href="#features" class="border-2 border-white text-white px-8 py-3 rounded-lg font-bold text-lg hover:bg-green-600">Learn More</a>
            </div>
        </div>
    </section>

    <section id="features" class="py-16 max-w-6xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-center text-green-800 mb-12">Everything your school needs</h2>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['📋', 'Student Records', 'Master PEA ID issuance, enrolment, consent, photos'],
                ['💰', 'Fees & Ledger', 'Immutable fee tracking, textbook issuance, parent balances'],
                ['✅', 'Attendance', 'QR scan or manual entry, live tally, register printing'],
                ['📊', 'Academic Marks', 'Per-teacher ownership, sequence marks, automated promotion'],
                ['📅', 'Timetable', 'Master timetable authored by VP, approved by Principal'],
                ['🔔', 'Parent Portal', 'Cloud-direct portal with push notifications and PWA install'],
            ] as [$icon, $title, $desc])
                <div class="bg-green-50 rounded-xl p-6 text-center">
                    <div class="text-3xl mb-3">{{ $icon }}</div>
                    <h3 class="font-bold text-lg text-green-900 mb-2">{{ $title }}</h3>
                    <p class="text-gray-600 text-sm">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="request" class="bg-gray-50 py-16">
        <div class="max-w-xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-green-800 mb-2">Onboard Your School</h2>
            <p class="text-center text-gray-500 mb-8">Fill out this form and the Presbyterian Education Authority will set up your school on EDIFIS.</p>

            <form id="onboard-form" class="bg-white rounded-xl shadow p-6 space-y-4">
                <input id="school_name" placeholder="School Name *" required class="w-full border rounded-lg p-3">
                <input id="school_code" placeholder="School Code (e.g. pssnkwen) *" required class="w-full border rounded-lg p-3">
                <input id="location" placeholder="Location / Town" class="w-full border rounded-lg p-3">
                <input id="contact_name" placeholder="Principal / Contact Name *" required class="w-full border rounded-lg p-3">
                <input id="contact_email" type="email" placeholder="Contact Email *" required class="w-full border rounded-lg p-3">
                <input id="contact_phone" placeholder="Contact Phone" class="w-full border rounded-lg p-3">
                <input id="estimated_students" type="number" placeholder="Estimated Students" class="w-full border rounded-lg p-3">
                <button type="submit" class="w-full bg-green-700 text-white py-3 rounded-lg font-bold text-lg hover:bg-green-800 transition">
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
                    if (res.ok) {
                        status.textContent = data.message;
                        status.classList.add('text-green-600');
                    } else {
                        status.textContent = data.message || 'Something went wrong. Please try again.';
                        status.classList.add('text-red-600');
                    }
                });
            </script>
        </div>
    </section>

    <footer class="bg-green-900 text-green-200 py-8 text-center text-sm">
        <p>EDIFIS — Presbyterian Education Authority, Cameroon</p>
        <p class="mt-1">GOD · PEACE · KNOWLEDGE</p>
    </footer>
</body>
</html>
