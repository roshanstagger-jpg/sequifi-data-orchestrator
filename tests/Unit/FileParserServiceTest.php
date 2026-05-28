<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FileParserService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileParserServiceTest extends TestCase
{
    private FileParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FileParserService();
    }

    public function test_parse_returns_associative_rows(): void
    {
        $content = "Job ID,Status,Amount\n101,Active,500\n102,Pending,750";
        $file = UploadedFile::fake()->createWithContent('jobs.csv', $content);

        $result = $this->service->parse($file);

        $this->assertCount(2, $result);
        $this->assertSame('101', (string) $result[0]['Job ID']);
        $this->assertSame('Active', $result[0]['Status']);
        $this->assertSame('102', (string) $result[1]['Job ID']);
    }

    public function test_parse_skips_empty_rows(): void
    {
        $content = "Job ID,Status\n101,Active\n\n102,Pending";
        $file = UploadedFile::fake()->createWithContent('jobs.csv', $content);

        $result = $this->service->parse($file);

        $this->assertCount(2, $result);
    }

    public function test_detect_columns_returns_header_row(): void
    {
        $content = "Job ID,Status,Amount\n101,Active,500";
        $file = UploadedFile::fake()->createWithContent('jobs.csv', $content);

        $columns = $this->service->detectColumns($file);

        $this->assertSame(['Job ID', 'Status', 'Amount'], $columns);
    }
}
