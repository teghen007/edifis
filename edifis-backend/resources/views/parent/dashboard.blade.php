<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1B5E20">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <title>EDIFIS Parent Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-green-800 text-white p-4 shadow">
        <div class="max-w-md mx-auto flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zM6.82 9L12 12.72L5.18 9L12 5.28L18.82 9zM17 15.99l-5 2.73l-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                <span class="font-bold">EDIFIS</span>
            </div>
            <span class="text-green-200 text-xs">GOD · PEACE · KNOWLEDGE</span>
        </div>
    </header>

    <main class="max-w-md mx-auto p-4" id="parent-app">
        @auth
        <div class="bg-white rounded-xl shadow p-4 mb-4">
            <h2 class="text-lg font-semibold text-green-800">My Children</h2>
            <div id="children-list" class="mt-3 space-y-2">
                @foreach($children ?? [] as $child)
                <a href="/parent/child/{{ $child->id }}"
                   class="block p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <p class="font-medium text-green-900">{{ $child->given_name }} {{ $child->family_name }}</p>
                    <p class="text-sm text-green-600">PEA ID: {{ $child->master_pea_id ?? '—' }}</p>
                </a>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="/parent/calendar" class="bg-white rounded-xl shadow p-4 text-center hover:shadow-md transition">
                <span class="text-2xl">📅</span>
                <p class="text-sm font-medium mt-1">Calendar</p>
            </a>
            <a href="/parent/notifications" class="bg-white rounded-xl shadow p-4 text-center hover:shadow-md transition">
                <span class="text-2xl">🔔</span>
                <p class="text-sm font-medium mt-1">Notices</p>
            </a>
        </div>
        @else
        <div class="bg-white rounded-xl shadow p-6 text-center">
            <h2 class="text-xl font-bold text-green-800 mb-4">Parent Portal</h2>
            <p class="text-gray-600 mb-4">Log in to view your child's results, fees, and attendance.</p>
            <a href="/staff/login"
               class="inline-block bg-green-700 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-800 transition">
                Sign In
            </a>
        </div>
        @endauth
    </main>
</body>
</html>
