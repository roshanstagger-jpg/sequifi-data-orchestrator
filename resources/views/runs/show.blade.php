@extends('layouts.app')
@section('title', 'Run #' . $run->id . ' — ' . $tenant->name)
@section('content')
    <div class="mb-6">
        <a href="{{ route('tenants.runs.index', $tenant) }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← {{ $tenant->name }}
        </a>
    </div>

    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Run #{{ $run->id }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $run->filename }} · {{ $run->created_at->format('M j, Y g:ia') }}</p>
        </div>
        @if($run->status === 'completed' && $run->new_jobs + $run->changed_jobs > 0)
            <a href="{{ route('tenants.runs.export', [$tenant, $run]) }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Download Delta Export
            </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-gray-900">{{ number_format($run->total_jobs) }}</div>
            <div class="text-xs text-gray-500 mt-0.5">Total Jobs</div>
        </div>
        <div class="bg-green-50 rounded-xl border border-green-200 p-4 text-center">
            <div class="text-2xl font-bold text-green-700">{{ number_format($run->new_jobs) }}</div>
            <div class="text-xs text-green-600 mt-0.5">New</div>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center">
            <div class="text-2xl font-bold text-blue-700">{{ number_format($run->changed_jobs) }}</div>
            <div class="text-xs text-blue-600 mt-0.5">Changed</div>
        </div>
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-gray-400">{{ number_format($run->unchanged_jobs) }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Unchanged</div>
        </div>
    </div>

    {{-- Changed jobs list --}}
    @if($changedJobs->isNotEmpty())
        <h2 class="font-semibold mb-3">New & Changed Jobs (exported)</h2>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Job Key</th>
                        <th class="text-left px-6 py-3 font-medium text-gray-500">Change Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($changedJobs as $job)
                        <tr>
                            <td class="px-6 py-3 font-mono text-xs">{{ $job->job_key }}</td>
                            <td class="px-6 py-3">
                                @if($job->change_type === 'new')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">New</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Changed</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $changedJobs->links() }}</div>
    @else
        <div class="text-center py-8 text-gray-400 text-sm">No new or changed jobs in this run.</div>
    @endif
@endsection
