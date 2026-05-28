<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveFieldsRequest;
use App\Http\Requests\SaveTemplateRequest;
use App\Models\SnapshotJob;
use App\Models\Tenant;
use App\Services\FileParserService;
use App\Services\SequifiApiService;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(
        private readonly FileParserService $parser,
        private readonly SequifiApiService $apiService,
    ) {}

    public function show(Tenant $tenant)
    {
        return view('setup.index', compact('tenant'));
    }

    public function uploadSample(Request $request, Tenant $tenant)
    {
        // mimes:xlsx,xls,csv is intentionally omitted — PHP's fileinfo extension
        // often misidentifies .xlsx files as application/zip (xlsx is ZIP-based),
        // causing false validation failures. Extension is validated via the client-
        // side accept attribute; the parser will throw on truly unreadable files.
        $request->validate(['file' => 'required|file|max:10240']);

        try {
            $columns = $this->parser->detectColumns($request->file('file'));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not read the file. Please upload a valid .xlsx, .xls, or .csv file.',
            ], 422);
        }

        if (empty($columns)) {
            return response()->json([
                'message' => 'No column headers detected. Ensure the file has a header row.',
            ], 422);
        }

        session(['detected_columns_' . $tenant->id => $columns]);

        return response()->json(['columns' => $columns]);
    }

    public function saveFields(SaveFieldsRequest $request, Tenant $tenant)
    {
        $tenant->update(['job_key_column' => $request->job_key_column]);

        // Build per-field change_mode map; default to 'any_change' when omitted.
        $fieldModes = $request->field_modes ?? [];

        $tenant->watchedFields()->delete();
        $tenant->watchedFields()->createMany(
            array_map(fn($f) => [
                'column_name' => $f,
                'change_mode' => $fieldModes[$f] ?? 'any_change',
            ], $request->watched_fields)
        );

        // Invalidate existing snapshots — the hash computation depends on which
        // fields are watched and their change_mode, so old hashes are stale.
        SnapshotJob::where('tenant_id', $tenant->id)->delete();

        return response()->json(['success' => true]);
    }

    public function saveTemplate(SaveTemplateRequest $request, Tenant $tenant)
    {
        $tenant->exportTemplateColumns()->delete();
        $tenant->exportTemplateColumns()->createMany(
            array_map(fn($col, $i) => [
                'source_column' => $col['source_column'],
                'output_column' => $col['output_column'],
                'sort_order' => $i,
            ], $request->columns, array_keys($request->columns))
        );

        return response()->json(['success' => true]);
    }

    public function saveApiConfig(Request $request, Tenant $tenant)
    {
        $request->validate([
            'sequifi_api_url'       => 'sometimes|nullable|url',
            'sequifi_bearer_token'  => 'sometimes|nullable|string',
            'api_lookback_days'     => 'sometimes|integer|min:1|max:730',
        ]);

        $updates = [];

        if ($request->has('sequifi_api_url')) {
            $updates['sequifi_api_url'] = $request->sequifi_api_url;
        }
        if ($request->has('sequifi_bearer_token') && $request->sequifi_bearer_token !== null) {
            $updates['sequifi_bearer_token'] = $request->sequifi_bearer_token;
        }
        if ($request->has('api_lookback_days')) {
            $updates['api_lookback_days'] = (int) $request->api_lookback_days;
        }

        if (!empty($updates)) {
            $tenant->update($updates);
        }

        return response()->json(['success' => true]);
    }

    public function testApiConnection(Request $request, Tenant $tenant)
    {
        $request->validate([
            'sequifi_api_url'      => 'nullable|url',
            'sequifi_bearer_token' => 'required|string',
            'api_lookback_days'    => 'nullable|integer|min:1|max:730',
        ]);

        // Temporarily set credentials on the model without persisting to DB
        $tenant->sequifi_api_url      = $request->sequifi_api_url ?? $tenant->sequifi_api_url;
        $tenant->sequifi_bearer_token = $request->sequifi_bearer_token;
        if ($request->filled('api_lookback_days')) {
            $tenant->api_lookback_days = (int) $request->api_lookback_days;
        }

        try {
            $result = $this->apiService->testConnection($tenant);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
