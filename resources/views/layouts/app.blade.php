<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Data Orchestrator')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center gap-6">
            <a href="{{ route('tenants.index') }}" class="font-bold text-lg tracking-tight">
                Data Orchestrator
            </a>
            @isset($tenant)
                <span class="text-gray-400">/</span>
                <a href="{{ route('tenants.runs.index', $tenant) }}" class="text-gray-600 hover:text-gray-900">
                    {{ $tenant->name }}
                </a>
            @endisset
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6 py-8">
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('token'))
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-300 rounded-lg text-yellow-900 text-sm">
                <strong>Save this API token — it won't be shown again:</strong>
                <code class="ml-2 font-mono bg-yellow-100 px-2 py-1 rounded">{{ session('token') }}</code>
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
