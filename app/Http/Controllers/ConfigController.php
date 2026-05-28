<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveFieldsRequest;
use App\Http\Requests\SaveTemplateRequest;
use App\Models\Tenant;
use App\Services\FileParserService;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(private readonly FileParserService $parser) {}

    public function show(Tenant $tenant)
    {
        return view('setup.index', compact('tenant'));
    }

    public function uploadSample(Request $request, Tenant $tenant)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $columns = $this->parser->detectColumns($request->file('file'));
        session(['detected_columns_' . $tenant->id => $columns]);

        return response()->json(['columns' => $columns]);
    }

    public function saveFields(SaveFieldsRequest $request, Tenant $tenant)
    {
        $tenant->update(['job_key_column' => $request->job_key_column]);

        $tenant->watchedFields()->delete();
        $tenant->watchedFields()->createMany(
            array_map(fn($f) => ['column_name' => $f], $request->watched_fields)
        );

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
}
