<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalFinancialRecord;
use App\Models\User;
use App\Services\GlobalFinance\ExcelIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UnifiedExcelIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic units
        GlobalBusinessUnit::firstOrCreate(['code' => 'fmcg'], ['name' => 'Unilever', 'data_source' => 'excel', 'is_active' => true]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'aqua'], ['name' => 'AQUA', 'data_source' => 'excel', 'is_active' => true]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'xtrans'], ['name' => 'Xtrans', 'data_source' => 'excel', 'is_active' => true]);
    }

    public function test_it_detects_multi_unit_excel_file(): void
    {
        $filePath = 'D:/excel global fa/PL & BS Oct 26 MJP,MJX,dan MJS.xlsx';
        if (! file_exists($filePath)) {
            $this->markTestSkipped("Test file not found at {$filePath}");
        }

        $service = new ExcelIngestionService;
        $detected = $service->detectFileType($filePath);

        $this->assertEquals('unified_multi_unit', $detected['type']);
        $this->assertStringContainsString('Unilever (MJP)', $detected['label']);
        $this->assertStringContainsString('AQUA (MJS)', $detected['label']);
        $this->assertStringContainsString('Xtrans (MJX)', $detected['label']);
    }

    public function test_it_ingests_multi_unit_file_through_unified_service(): void
    {
        $filePath = 'D:/excel global fa/PL & BS Oct 26 MJP,MJX,dan MJS.xlsx';
        if (! file_exists($filePath)) {
            $this->markTestSkipped("Test file not found at {$filePath}");
        }

        $service = new ExcelIngestionService;
        $result = $service->ingestUnifiedFile($filePath, 'PL & BS Oct 26 MJP,MJX,dan MJS.xlsx', true);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(500, $result['records_inserted']);
        $this->assertArrayHasKey('fmcg', $result['units_processed']);
        $this->assertArrayHasKey('aqua', $result['units_processed']);
        $this->assertArrayHasKey('xtrans', $result['units_processed']);

        // Verify records in database for October 2026
        $fmcgCount = GlobalFinancialRecord::where('business_unit_code', 'fmcg')->where('period', '2026-10-01')->count();
        $aquaCount = GlobalFinancialRecord::where('business_unit_code', 'aqua')->where('period', '2026-10-01')->count();
        $xtransCount = GlobalFinancialRecord::where('business_unit_code', 'xtrans')->where('period', '2026-10-01')->count();

        $this->assertGreaterThan(0, $fmcgCount);
        $this->assertGreaterThan(0, $aquaCount);
        $this->assertGreaterThan(0, $xtransCount);

        // Verify Balance Sheets exist
        $this->assertTrue(GlobalFinancialRecord::where('business_unit_code', 'fmcg')->where('period', '2026-10-01')->where('report_type', 'balance_sheet')->exists());
        $this->assertTrue(GlobalFinancialRecord::where('business_unit_code', 'aqua')->where('period', '2026-10-01')->where('report_type', 'balance_sheet')->exists());
        $this->assertTrue(GlobalFinancialRecord::where('business_unit_code', 'xtrans')->where('period', '2026-10-01')->where('report_type', 'balance_sheet')->exists());
    }

    public function test_it_allows_upload_via_controller_endpoint(): void
    {
        $filePath = 'D:/excel global fa/PL & BS Oct 26 MJP,MJX,dan MJS.xlsx';
        if (! file_exists($filePath)) {
            $this->markTestSkipped("Test file not found at {$filePath}");
        }

        $user = User::factory()->create([
            'email' => 'finance.super@mekar-jaya.com',
        ]);

        $uploadedFile = new UploadedFile(
            $filePath,
            'PL & BS Oct 26 MJP,MJX,dan MJS.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)->post(route('finance.sync.excel'), [
            'type' => 'auto',
            'file' => $uploadedFile,
            'overwrite' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
