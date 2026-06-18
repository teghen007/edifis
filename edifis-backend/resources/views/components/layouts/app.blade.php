<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'EDIFIS Field' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-green-800 text-white p-3 shadow">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <span class="font-bold">EDIFIS Field</span>
            <span class="text-green-200 text-xs">{{ config('edifis.node_id') }} · {{ config('edifis.mode') }}</span>
        </div>
    </header>
    <main>{{ $slot }}</main>
    @livewireScripts
</body>
</html>
