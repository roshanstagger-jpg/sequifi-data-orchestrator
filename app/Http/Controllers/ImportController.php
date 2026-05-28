<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ImportRun;
use App\Models\Tenant;
use App\Services\DiffEngineService;
use App\Services\ExportBuilderService;
use App\Services\FileParserService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly FileParserService $parser,
        private readonly DiffEngineService $diffEngine,
        private readonly ExportBuilderService $exporter,
    ) {}

    public function index(Tenant $tenant)
    {
        $runs = $tenant->importRuns()->latest()->paginate(20);
        return view('runs.index', compact('tenant', 'runs'));
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:51200']);

        if (!$tenant->isConfigured()) {
            return back()->withErrors(['file' => 'Tenant configuration is incomplete. Complete setup first.']);
        }

        $file = $request->file('file');
        $run = $tenant->importRuns()->create([
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        try {
            $jobs = $this->parser->parse($file);
            $this->diffEngine->diff($tenant, $run, $jobs);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            \Illuminate\Support\Facades\Log::error('Import failed', [
                'run_id' => $run->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['file' => 'Import failed. Please check your file format and try again.']);
        }

        return redirect()
            ->route('tenants.runs.show', [$tenant, $run])
            ->with('success', 'Import processed. ' . $run->new_jobs . ' new, ' . $run->changed_jobs . ' changed.');
    }

    public function show(Tenant $tenant, ImportRun $run)
    {
        abort_if($run->tenant_id !== $tenant->id, 404);

        $changedJobs = $run->runJobs()
            ->whereIn('change_type', ['new', 'changed'])
            ->paginate(50);

        return view('runs.show', compact('tenant', 'run', 'changedJobs'));
    }

    public function export(Tenant $tenant, ImportRun $run)
    {
        abort_if($run->tenant_id !== $tenant->id, 404);
        abort_if($run->status !== 'completed', 400);

        return $this->exporter->buildResponse($tenant, $run);
    }
}
