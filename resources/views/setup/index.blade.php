@extends('layouts.app')
@section('title', 'Setup — ' . $tenant->name)
@section('content')
<div x-data="setupWizard()" class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Setup: {{ $tenant->name }}</h1>
        <a href="{{ route('tenants.runs.index', $tenant) }}" class="text-sm text-gray-500 hover:text-gray-700">
            Skip to imports →
        </a>
    </div>

    {{-- Progress --}}
    <div class="flex items-center gap-2 mb-8">
        <template x-for="(label, i) in ['Sample File', 'Job Key', 'Watched Fields', 'Output Template']" :key="i">
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <div :class="step > i+1 ? 'bg-green-500' : step === i+1 ? 'bg-blue-600' : 'bg-gray-200'"
                         class="w-6 h-6 rounded-full flex items-center justify-center text-xs text-white font-bold"
                         x-text="step > i+1 ? '✓' : i+1"></div>
                    <span :class="step === i+1 ? 'text-gray-900 font-medium' : 'text-gray-400'"
                          class="text-sm" x-text="label"></span>
                </div>
                <div x-show="i < 3" class="w-8 h-px bg-gray-200"></div>
            </div>
        </template>
    </div>

    {{-- Step 1: Upload sample file --}}
    <div x-show="step === 1" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-1">Upload a sample file</h2>
        <p class="text-sm text-gray-500 mb-4">One row from your typical weekly import. Used to detect column names.</p>

        <div x-show="!uploading && !columns.length">
            <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg p-8 cursor-pointer hover:border-blue-400 transition-colors">
                <span class="text-gray-400 text-sm mb-2">Click to upload .xlsx, .xls, or .csv</span>
                <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="uploadSample($event)">
            </label>
        </div>

        <div x-show="uploading" class="text-sm text-gray-500 py-4">Detecting columns...</div>

        <div x-show="columns.length > 0" class="space-y-3">
            <p class="text-sm text-green-700 font-medium" x-text="`Detected ${columns.length} columns`"></p>
            <div class="flex flex-wrap gap-2">
                <template x-for="col in columns" :key="col">
                    <span class="px-2 py-1 bg-gray-100 rounded text-xs text-gray-700" x-text="col"></span>
                </template>
            </div>
            <button @click="step = 2"
                    class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Continue →
            </button>
        </div>

        <div x-show="error" class="mt-3 text-sm text-red-600" x-text="error"></div>
    </div>

    {{-- Step 2: Pick job key column --}}
    <div x-show="step === 2" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-1">Select the unique job identifier column</h2>
        <p class="text-sm text-gray-500 mb-4">This column uniquely identifies each job (e.g. "Job ID", "Order #").</p>

        <div class="space-y-2">
            <template x-for="col in columns" :key="col">
                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer"
                       :class="jobKeyColumn === col ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                    <input type="radio" :value="col" x-model="jobKeyColumn" class="text-blue-600">
                    <span class="text-sm font-medium" x-text="col"></span>
                </label>
            </template>
        </div>

        <div class="flex gap-3 mt-4">
            <button @click="step = 1" class="px-4 py-2 text-gray-600 text-sm hover:text-gray-900">← Back</button>
            <button @click="step = 3" :disabled="!jobKeyColumn"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40">
                Continue →
            </button>
        </div>
    </div>

    {{-- Step 3: Watched fields --}}
    <div x-show="step === 3" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-1">Select watched fields</h2>
        <p class="text-sm text-gray-500 mb-4">A job is flagged as "changed" when any of these fields differ from the previous import.</p>

        <div class="space-y-2 max-h-72 overflow-y-auto">
            <template x-for="col in columns" :key="col">
                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer"
                       :class="watchedFields.includes(col) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                    <input type="checkbox" :value="col" x-model="watchedFields" class="text-blue-600 rounded">
                    <span class="text-sm" x-text="col"></span>
                </label>
            </template>
        </div>
        <p class="text-xs text-gray-400 mt-2" x-text="`${watchedFields.length} fields selected`"></p>

        <div class="flex gap-3 mt-4">
            <button @click="step = 2" class="px-4 py-2 text-gray-600 text-sm hover:text-gray-900">← Back</button>
            <button @click="step = 4" :disabled="watchedFields.length === 0"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40">
                Continue →
            </button>
        </div>
    </div>

    {{-- Step 4: Output template --}}
    <div x-show="step === 4" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-1">Configure output template</h2>
        <p class="text-sm text-gray-500 mb-4">Choose which columns appear in the export and what they're called.</p>

        <div class="space-y-2 max-h-72 overflow-y-auto">
            <template x-for="(mapping, index) in outputMappings" :key="index">
                <div class="flex items-center gap-2 p-2 rounded-lg border border-gray-200">
                    <select x-model="mapping.source_column"
                            class="flex-1 border border-gray-300 rounded px-2 py-1.5 text-sm">
                        <option value="">— source column —</option>
                        <template x-for="col in columns" :key="col">
                            <option :value="col" x-text="col"></option>
                        </template>
                    </select>
                    <span class="text-gray-400 text-sm">→</span>
                    <input type="text" x-model="mapping.output_column" placeholder="Output column name"
                           class="flex-1 border border-gray-300 rounded px-2 py-1.5 text-sm">
                    <button @click="outputMappings.splice(index, 1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                </div>
            </template>
        </div>

        <button @click="outputMappings.push({source_column: '', output_column: ''})"
                class="mt-3 text-sm text-blue-600 hover:text-blue-800">
            + Add column
        </button>

        <div class="flex gap-3 mt-4">
            <button @click="step = 3" class="px-4 py-2 text-gray-600 text-sm hover:text-gray-900">← Back</button>
            <button @click="saveConfig()" :disabled="saving || outputMappings.length === 0"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40"
                    x-text="saving ? 'Saving…' : 'Save Configuration'">
            </button>
        </div>

        <div x-show="saveError" class="mt-3 text-sm text-red-600" x-text="saveError"></div>
    </div>
</div>

<script>
function setupWizard() {
    return {
        step: 1,
        columns: [],
        jobKeyColumn: '',
        watchedFields: [],
        outputMappings: [],
        uploading: false,
        saving: false,
        error: '',
        saveError: '',

        async uploadSample(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = true;
            this.error = '';

            const form = new FormData();
            form.append('file', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const res = await fetch('{{ route('tenants.setup.sample', $tenant) }}', {
                    method: 'POST',
                    body: form,
                });
                const data = await res.json();
                this.columns = data.columns ?? [];
                if (!this.columns.length) this.error = 'No columns detected. Check the file format.';
            } catch {
                this.error = 'Upload failed. Try again.';
            } finally {
                this.uploading = false;
            }
        },

        async saveConfig() {
            this.saving = true;
            this.saveError = '';
            const token = document.querySelector('meta[name="csrf-token"]').content;

            try {
                const fieldsRes = await fetch('{{ route('tenants.setup.fields', $tenant) }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({
                        job_key_column: this.jobKeyColumn,
                        watched_fields: this.watchedFields,
                    }),
                });
                if (!fieldsRes.ok) throw new Error('Failed to save fields');

                const templateRes = await fetch('{{ route('tenants.setup.template', $tenant) }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({ columns: this.outputMappings }),
                });
                if (!templateRes.ok) throw new Error('Failed to save template');

                window.location.href = '{{ route('tenants.runs.index', $tenant) }}';
            } catch (e) {
                this.saveError = e.message;
            } finally {
                this.saving = false;
            }
        },
    }
}
</script>
@endsection
