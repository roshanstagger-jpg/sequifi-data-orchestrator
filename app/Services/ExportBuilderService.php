<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportRun;
use App\Models\Tenant;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportBuilderService
{
    public function buildResponse(Tenant $tenant, ImportRun $run): BinaryFileResponse
    {
        $templateColumns = $tenant->exportTemplateColumns;

        // Export-ready data is stored directly on each import_run_job row.
        // Only new/changed rows carry data; unchanged rows have data = null.
        $jobs = $run->runJobs()
            ->whereIn('change_type', ['new', 'changed'])
            ->whereNotNull('data')
            ->get()
            ->map(fn($rj) => $rj->data);   // already decoded by model's array cast

        $headers = $templateColumns->pluck('output_column')->toArray();

        $rows = $jobs->map(function (array $job) use ($templateColumns): array {
            return $templateColumns
                ->map(fn($col) => $job[$col->source_column] ?? '')
                ->toArray();
        })->toArray();

        $filename = 'delta-export-' . $run->id . '-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new class($headers, $rows) implements FromArray, WithHeadings {
                public function __construct(
                    private readonly array $headers,
                    private readonly array $rows,
                ) {}

                public function array(): array  { return $this->rows; }
                public function headings(): array { return $this->headers; }
            },
            $filename
        );
    }
}
