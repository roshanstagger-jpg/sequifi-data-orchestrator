@extends('layouts.app')
@section('title', 'New Tenant')
@section('content')
    <div class="max-w-md">
        <h1 class="text-2xl font-bold mb-6">New Tenant</h1>

        <form method="POST" action="{{ route('tenants.store') }}"
              class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g. Acme Solar" required>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Create Tenant
                </button>
                <a href="{{ route('tenants.index') }}"
                   class="px-4 py-2 text-gray-600 hover:text-gray-900 text-sm">Cancel</a>
            </div>
        </form>
    </div>
@endsection
