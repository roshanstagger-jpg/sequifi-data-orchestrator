@extends('layouts.app')
@section('title', 'User Ledger — ' . $tenant->name)
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">User Ledger</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $tenant->name }} · {{ $agents->total() }} {{ Str::plural('user', $agents->total()) }}
                @if($tenant->sequifiAgents()->exists())
                    @php $lastSync = $tenant->sequifiAgents()->latest('synced_at')->value('synced_at'); @endphp
                    @if($lastSync)
                        · Last synced {{ \Carbon\Carbon::parse($lastSync)->diffForHumans() }}
                    @endif
                @endif
            </p>
        </div>

        @if($tenant->hasApiConfig())
            <form method="POST" action="{{ route('tenants.agents.sync', $tenant) }}"
                  x-data="{ syncing: false }" @submit="syncing = true">
                @csrf
                <button type="submit" :disabled="syncing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-60"
                        x-text="syncing ? 'Syncing…' : 'Sync Users'">
                </button>
            </form>
        @else
            <a href="{{ route('tenants.setup', $tenant) }}"
               class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200">
                Configure API to sync
            </a>
        @endif
    </div>

    @if($errors->has('sync'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
            {{ $errors->first('sync') }}
        </div>
    @endif

    @if($agents->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <p class="text-gray-400 text-sm mb-4">No users synced yet.</p>
            @if($tenant->hasApiConfig())
                <form method="POST" action="{{ route('tenants.agents.sync', $tenant) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        Sync Users Now
                    </button>
                </form>
            @else
                <a href="{{ route('tenants.setup', $tenant) }}" class="text-blue-600 hover:text-blue-800 text-sm underline">
                    Set up Sequifi API credentials →
                </a>
            @endif
        </div>
    @else
        {{-- Search / filter --}}
        <div class="mb-4" x-data="{ search: '' }">
            <input type="text" x-model="search" placeholder="Filter by name, email, position…"
                   class="w-full max-w-sm px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-3">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">ID</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Name</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Email</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Position</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Location</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Type</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($agents as $agent)
                            <tr class="hover:bg-gray-50"
                                x-show="!search ||
                                    '{{ strtolower($agent->full_name . ' ' . $agent->email . ' ' . $agent->position . ' ' . $agent->sequifi_id) }}'.includes(search.toLowerCase())">
                                <td class="px-6 py-3 font-mono text-xs text-gray-400">{{ $agent->sequifi_id }}</td>
                                <td class="px-6 py-3 font-medium text-gray-900">
                                    {{ $agent->full_name ?: '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ $agent->email ?: '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $agent->position ?: '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $agent->location ?: '—' }}</td>
                                <td class="px-6 py-3 text-gray-500 text-xs">{{ $agent->worker_type ?: '—' }}</td>
                                <td class="px-6 py-3">
                                    @if($agent->status)
                                        @php
                                            $statusColor = match(strtolower($agent->status)) {
                                                'active'     => 'bg-green-100 text-green-700',
                                                'terminated','inactive' => 'bg-red-100 text-red-600',
                                                default      => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                                            {{ ucfirst($agent->status) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $agents->links() }}</div>
    @endif
@endsection
