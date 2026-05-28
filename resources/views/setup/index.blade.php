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

    {{-- Step 1: Sample file / API config --}}
    <div x-show="step === 1" class="bg-white rounded-xl border border-gray-200 p-6">

        {{-- Mode toggle --}}
        <div class="flex items-center gap-1 p-1 bg-gray-100 rounded-lg w-fit mb-5">
            <button type="button"
                    @click="apiMode = false; columns = apiMode ? [] : columns; error = ''"
                    :class="!apiMode ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Upload file
            </button>
            <button type="button"
                    @click="apiMode = true; error = ''"
                    :class="apiMode ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Pull from Sequifi API
            </button>
        </div>

        {{-- File upload panel --}}
        <div x-show="!apiMode">
            <h2 class="font-semibold mb-1">Upload a sample file</h2>
            <p class="text-sm text-gray-500 mb-4">One row from your typical weekly import. Used to detect column names.</p>

            <div x-show="!uploading && !columns.length">
                <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg p-8 cursor-pointer hover:border-blue-400 transition-colors">
                    <span class="text-gray-400 text-sm mb-2">Click to upload .xlsx, .xls, or .csv</span>
                    <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="uploadSample($event)">
                </label>
            </div>

            <div x-show="uploading" class="text-sm text-gray-500 py-4">Detecting columns...</div>

            <div x-show="columns.length > 0 && !apiMode" class="space-y-3">
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
        </div>

        {{-- API config panel --}}
        <div x-show="apiMode" class="space-y-4">
            <div>
                <h2 class="font-semibold mb-1">Connect to Sequifi API</h2>
                <p class="text-sm text-gray-500">Configure your API credentials to pull data directly from Sequifi.</p>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Base URL</label>
                    <input type="url" x-model="apiUrl"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="https://marketplace-api.sequifi.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bearer Token</label>
                    <input type="password" x-model="apiToken"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="Paste Bearer token…"
                           autocomplete="off">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Days of history to pull</label>
                    <input type="number" x-model.number="apiLookbackDays" min="1" max="730"
                           class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div x-show="apiTestError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2" x-text="apiTestError"></div>

            <div x-show="apiTotal > 0 && columns.length > 0 && !apiTestError" class="text-sm text-green-700 bg-green-50 rounded-lg px-3 py-2">
                Connected — <span x-text="apiTotal.toLocaleString()"></span> records found, <span x-text="columns.length"></span> columns detected.
            </div>

            <div class="flex items-center gap-3">
                <button @click="testApi()"
                        :disabled="testingApi || !apiToken"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        x-text="testingApi ? 'Testing…' : 'Test connection'">
                </button>

                <button x-show="columns.length > 0 && apiTotal > 0 && !apiTestError"
                        @click="step = 2"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Continue →
                </button>
            </div>
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
                <div class="rounded-lg border"
                     :class="watchedFields.includes(col) ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                    <label class="flex items-center gap-3 p-3 cursor-pointer">
                        <input type="checkbox" :value="col" x-model="watchedFields" class="text-blue-600 rounded flex-shrink-0">
                        <span class="text-sm flex-1" x-text="col"></span>
                    </label>

                    {{-- Change-mode toggle — only shown when the field is checked --}}
                    <div x-show="watchedFields.includes(col)"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-3 pb-3 flex items-center gap-2">
                        <span class="text-xs text-gray-400 mr-1">Trigger on:</span>
                        <button type="button"
                                @click.prevent="fieldModes[col] = 'any_change'"
                                :class="(fieldModes[col] || 'any_change') === 'any_change'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                            Any change
                        </button>
                        <button type="button"
                                @click.prevent="fieldModes[col] = 'fill_only'"
                                :class="fieldModes[col] === 'fill_only'
                                    ? 'bg-amber-500 text-white'
                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                            Fill only
                        </button>
                        <span x-show="fieldModes[col] === 'fill_only'"
                              class="text-xs text-amber-600 ml-1">
                            blank → value only
                        </span>
                    </div>
                </div>
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
        fieldModes: {},   // { columnName: 'any_change' | 'fill_only' }
        outputMappings: [],
        uploading: false,
        saving: false,
        error: '',
        saveError: '',

        // API mode state
        apiMode: {{ $tenant->hasApiConfig() ? 'true' : 'false' }},
        apiUrl: '{{ $tenant->sequifi_api_url ?? 'https://marketplace-api.sequifi.com' }}',
        apiToken: '',
        apiLookbackDays: {{ $tenant->api_lookback_days ?? 90 }},
        testingApi: false,
        apiTestError: '',
        apiTotal: 0,

        // Always read the XSRF-TOKEN cookie — it's updated on every response so it
        // stays current even if the page has been open across cold-starts or navigation,
        // preventing 419 errors caused by stale CSRF tokens in the meta tag.
        xsrfToken() {
            const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        },

        async uploadSample(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = true;
            this.error = '';

            const form = new FormData();
            form.append('file', file);

            try {
                const res = await fetch('{{ route('tenants.setup.sample', $tenant) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': this.xsrfToken(),
                    },
                    body: form,
                });
                const data = await res.json();
                if (!res.ok) {
                    this.error = data.message ?? (res.status === 422 ? 'Invalid file. Please upload .xlsx, .xls, or .csv.' : 'Upload failed. Try again.');
                    return;
                }
                this.columns = data.columns ?? [];
                if (!this.columns.length) this.error = 'No columns detected. Check the file has a header row.';
            } catch {
                this.error = 'Upload failed. Try again.';
            } finally {
                this.uploading = false;
            }
        },

        async testApi() {
            this.testingApi = true;
            this.apiTestError = '';

            try {
                const res = await fetch('{{ route('tenants.setup.api-test', $tenant) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': this.xsrfToken(),
                    },
                    body: JSON.stringify({
                        sequifi_api_url: this.apiUrl,
                        sequifi_bearer_token: this.apiToken,
                        api_lookback_days: this.apiLookbackDays,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.apiTestError = data.message ?? 'Connection test failed. Please check your credentials.';
                    return;
                }
                // Success — populate columns and total, then auto-save the API config
                this.columns = data.columns ?? [];
                this.apiTotal = data.total ?? 0;

                // Auto-save credentials now that the test passed
                await fetch('{{ route('tenants.setup.api', $tenant) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': this.xsrfToken(),
                    },
                    body: JSON.stringify({
                        sequifi_api_url: this.apiUrl,
                        sequifi_bearer_token: this.apiToken,
                        api_lookback_days: this.apiLookbackDays,
                    }),
                });
            } catch {
                this.apiTestError = 'Connection test failed. Please try again.';
            } finally {
                this.testingApi = false;
            }
        },

        async saveConfig() {
            this.saving = true;
            this.saveError = '';

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': this.xsrfToken(),
                };

                const fieldsRes = await fetch('{{ route('tenants.setup.fields', $tenant) }}', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        job_key_column: this.jobKeyColumn,
                        watched_fields: this.watchedFields,
                        field_modes: this.fieldModes,
                    }),
                });
                if (!fieldsRes.ok) {
                    const d = await fieldsRes.json().catch(() => ({}));
                    throw new Error(d.message ?? (fieldsRes.status === 422 ? 'Validation error saving fields.' : 'Failed to save configuration.'));
                }

                const templateRes = await fetch('{{ route('tenants.setup.template', $tenant) }}', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ columns: this.outputMappings }),
                });
                if (!templateRes.ok) {
                    const d = await templateRes.json().catch(() => ({}));
                    throw new Error(d.message ?? (templateRes.status === 422 ? 'Validation error saving template.' : 'Failed to save configuration.'));
                }

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
