@extends('layouts.app')
@section('title', 'Imports — ' . $tenant->name)
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $tenant->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Import history</p>
        </div>
        <a href="{{ route('tenants.setup', $tenant) }}" class="text-sm text-gray-500 hover:text-gray-700">
            Edit config
        </a>
    </div>

    {{-- Upload form --}}
    @if($tenant->isConfigured())
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6" x-data="{ uploading: false }">
            <h2 class="font-semibold mb-3">Upload weekly file</h2>
            <form method="POST" action="{{ route('tenants.runs.store', $tenant) }}"
                  enctype="multipart/form-data" @submit="uploading = true">
                @csrf
                <div class="flex gap-3 items-center">
                    <input type="file" name="file" accept=".xlsx,.xls,.csv"
                           class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                           required>
                    <button type="submit" :disabled="uploading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-60"
                            x-text="uploading ? 'Processing…' : 'Process Import'">
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 text-sm text-yellow-800">
            Setup required before uploading.
            <a href="{{ route('tenants.setup', $tenant) }}" class="font-medium underline">Complete setup →</a>
        </div>
    @endif

    {{-- Run history --}}
    @if($runs->isEmpty())
        <div class="text-center py-12 text-gray-400 text-sm">No imports yet.</div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">File</th>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Date</th>
                        <th class="text-right px-6 py-3 font-medium text-gray-500">Total</th>
                        <th class="text-right px-6 py-3 font-medium text-gray-500">New</th>
                        <th class="text-right px-6 py-3 font-medium text-gray-500">Changed</th>
                        <th class="text-right px-6 py-3 font-medium text-gray-500">Unchanged</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($runs as $run)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $run->filename }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $run->created_at->format('M j, Y g:ia') }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ number_format($run->total_jobs) }}</td>
                            <td class="px-6 py-3 text-right text-green-700 font-medium">{{ number_format($run->new_jobs) }}</td>
                            <td class="px-6 py-3 text-right text-blue-700 font-medium">{{ number_format($run->changed_jobs) }}</td>
                            <td class="px-6 py-3 text-right text-gray-400">{{ number_format($run->unchanged_jobs) }}</td>
                            <td class="px-6 py-3 text-right space-x-3">
                                @if($run->status === 'completed')
                                    <a href="{{ route('tenants.runs.show', [$tenant, $run]) }}"
                                       class="text-gray-400 hover:text-gray-700 text-xs">Detail</a>
                                    @if($run->new_jobs + $run->changed_jobs > 0)
                                        <a href="{{ route('tenants.runs.export', [$tenant, $run]) }}"
                                           class="text-blue-600 hover:text-blue-800 text-xs font-medium">Download</a>
                                    @endif
                                @elseif($run->status === 'failed')
                                    <span class="text-red-500 text-xs">Failed</span>
                                @else
                                    <span class="text-gray-400 text-xs">Processing…</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
@endsection
