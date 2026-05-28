@extends('layouts.app')
@section('title', 'Tenants')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Tenants</h1>
        <a href="{{ route('tenants.create') }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            New Tenant
        </a>
    </div>

    @if($tenants->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg">No tenants yet.</p>
            <a href="{{ route('tenants.create') }}" class="mt-2 inline-block text-blue-600 hover:underline">
                Create your first tenant
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Name</th>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Job Key</th>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Runs</th>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($tenants as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium">{{ $t->name }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $t->job_key_column ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $t->import_runs_count }}</td>
                            <td class="px-6 py-4">
                                @if($t->isConfigured())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Configured</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Setup needed</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <a href="{{ route('tenants.setup', $t) }}" class="text-gray-400 hover:text-gray-700 text-xs">Setup</a>
                                <a href="{{ route('tenants.runs.index', $t) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">View Runs →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
