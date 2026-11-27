<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalFinancialRecord;
use App\Models\User;
use App\Services\GlobalFinance\ExcelIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

        // Verify Xtrans October 2026 P&L does not double-count subtotal/parent rows and matches leaf sum (-193,376,202.27)
        $this->assertDatabaseMissing('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'account_code' => '5000',
        ]);
        $this->assertDatabaseMissing('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'account_code' => '6000',
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'account_code' => '6000-02-038',
            'account_name' => 'Biaya LAKA',
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'account_code' => '9000-001',
            'account_name' => 'Pendapatan Atas Bunga Bank',
        ]);

        $xtransTotals = GlobalFinancialRecord::where('business_unit_code', 'xtrans')
            ->where('period', '2026-10-01')
            ->where('report_type', 'pnl')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $netProfit = (float) ($xtransTotals['revenue'] ?? 0)
            - (float) ($xtransTotals['cogs'] ?? 0)
            - (float) ($xtransTotals['opex'] ?? 0)
            + (float) ($xtransTotals['other_income'] ?? 0)
            - (float) ($xtransTotals['other_expense'] ?? 0);

        $this->assertEqualsWithDelta(-193376202.27, $netProfit, 1.0);
    }

    public function test_it_allows_upload_via_controller_endpoint(): void
    {
        $filePath = 'D:/excel global fa/PL & BS Oct 26 MJP,MJX,dan MJS.xlsx';
        if (! file_exists($filePath)) {
            $this->markTestSkipped("Test file not found at {$filePath}");
        }

        $user = User::factory()->create([
            'email' => 'superadmin@mjg.com',
            'name' => 'Super Admin',
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

    public function test_it_ingests_ho_cost_prorate_without_account_code_in_mjsm_ccod_and_department_files(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheetCcod = $spreadsheet->getActiveSheet();
        $sheetCcod->setTitle('PL Depo Sept 26 CCOD');
        $sheetCcod->setCellValue('I6', 'CCOD PTI');
        $sheetCcod->setCellValue('A41', '6000-01-01-001');
        $sheetCcod->setCellValue('E41', 'Biaya Gaji + Tunjangan Tetap');
        $sheetCcod->setCellValue('I41', 68809400);
        // Baris HO Cost Prorate memiliki kolom A kosong dan nama akun di kolom E
        $sheetCcod->setCellValue('E84', 'HO Cost Prorate');
        $sheetCcod->setCellValue('I84', 29981402.76);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_mjsm_ho_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $service = new ExcelIngestionService;
        $service->ingestMjsmCcodFile($tempPath, 'PL & BS MJSM+CCOD Jan-Sept 26.xlsx');

        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'ccod',
            'entity_code' => 'CCOD_PATI',
            'period' => '2026-09-01',
            'category' => 'opex',
            'account_name' => 'HO Cost Prorate',
            'amount' => 29981402.76,
        ]);

        // Juga pastikan format department standalone tetap membaca HO Cost Prorate
        $deptRows = [
            1 => [],
            2 => [],
            3 => ['A' => 'From 01 Sep 2026 to 30 Sep 2026'],
            4 => [],
            5 => ['A' => 'GL Account No.', 'B' => 'Descriptions', 'C' => 'CCOD PATI'],
            6 => ['A' => '', 'B' => 'HO Cost Prorate', 'C' => 29981402.76],
        ];
        $service->ingestFmcgDepartmentFile($deptRows, 'ccod_dept.xlsx');

        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'ccod',
            'entity_code' => 'CCOD_PATI',
            'period' => '2026-09-01',
            'category' => 'opex',
            'account_name' => 'HO Cost Prorate',
            'amount' => 29981402.76,
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    public function test_aqua_summary_ingestion_only_replaces_matching_periods(): void
    {
        // Data AQUA Oktober 2026 yang sudah ada sebelumnya
        GlobalFinancialRecord::insert([
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01-001',
                'account_name' => 'Penjualan Oktober',
                'amount' => 100000000,
                'source_type' => 'excel_upload',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-10-01',
                'report_type' => 'balance_sheet',
                'category' => 'asset',
                'account_code' => 'A.1',
                'account_name' => 'Kas Oktober',
                'amount' => 50000000,
                'source_type' => 'excel_upload',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Buat file ringkas AQUA hanya untuk September 2026 (2026-09-01)
        $spreadsheet = new Spreadsheet;
        $plSheet = $spreadsheet->getActiveSheet();
        $plSheet->setTitle('PL');
        $plSheet->setCellValue('A5', 'Account No');
        $plSheet->setCellValue('B5', 'Account Name');
        $plSheet->setCellValue('C5', '2026-09-01');
        $plSheet->setCellValue('A6', '4000-01-001');
        $plSheet->setCellValue('B6', 'Penjualan September');
        $plSheet->setCellValue('C6', 80000000);

        $bsSheet = $spreadsheet->createSheet();
        $bsSheet->setTitle('BS');
        $bsSheet->setCellValue('C6', 'A.1');
        $bsSheet->setCellValue('D6', 'Kas September');
        $bsSheet->setCellValue('G6', 25000000);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_aqua_sept_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $service = new ExcelIngestionService;
        $service->ingestAquaFile($tempPath, 'MJS SEPT 26.xlsx');

        // September ter-insert, Oktober tetap utuh
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'aqua',
            'entity_code' => 'MJS',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'amount' => 80000000,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'aqua',
            'entity_code' => 'MJS',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'account_name' => 'Penjualan Oktober',
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'aqua',
            'entity_code' => 'MJS',
            'period' => '2026-10-01',
            'report_type' => 'balance_sheet',
            'account_name' => 'Kas Oktober',
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    public function test_mjx_monthly_ingestion_sums_fleet_columns_skips_subtotals_and_reads_col_f(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PL Detail November 26 MJX');

        $sheet->setCellValue('D3', '1 November 2026 s/d 30 November 2026');
        $sheet->setCellValue('A5', 'GL Account No.');
        $sheet->setCellValue('C5', 'Description');
        $sheet->setCellValue('H5', 'HO');
        $sheet->setCellValue('I5', 'TRUCK-1');
        $sheet->setCellValue('J5', 'TRUCK-2');
        $sheet->setCellValue('K5', 'x');

        // Parent / subtotal rows that must be skipped
        $sheet->setCellValue('A7', '4000');
        $sheet->setCellValue('C7', 'Pendapatan');
        $sheet->setCellValue('H7', 1000);
        $sheet->setCellValue('K7', 'x');
        $sheet->setCellValue('FM7', 1000);

        // Leaf revenue
        $sheet->setCellValue('A8', '4000-01');
        $sheet->setCellValue('D8', 'Pendapatan Ekspedisi');
        $sheet->setCellValue('H8', 100);
        $sheet->setCellValue('I8', 500);
        $sheet->setCellValue('J8', 400);
        $sheet->setCellValue('K8', 'x');
        $sheet->setCellValue('FM8', 600); // Simulasi FM ketinggalan truk terakhir

        // Parent COGS
        $sheet->setCellValue('A10', '5000');
        $sheet->setCellValue('C10', 'HPP');
        $sheet->setCellValue('H10', 300);

        // Leaf COGS
        $sheet->setCellValue('A11', '5000-018');
        $sheet->setCellValue('E11', 'Biaya Bahan Bakar');
        $sheet->setCellValue('I11', 200);
        $sheet->setCellValue('J11', 100);

        // Parent Opex & sub-parent
        $sheet->setCellValue('A13', '6000');
        $sheet->setCellValue('C13', 'BIAYA Operasional');
        $sheet->setCellValue('H13', 250);

        $sheet->setCellValue('A14', '6000-02');
        $sheet->setCellValue('E14', 'Biaya Operasional & Administrasi');
        $sheet->setCellValue('H14', 250);

        // Leaf Opex dengan nama di kolom F & FM bernilai 0
        $sheet->setCellValue('A15', '6000-02-038');
        $sheet->setCellValue('F15', 'Biaya LAKA');
        $sheet->setCellValue('H15', 250);
        $sheet->setCellValue('FM15', 0);

        // Leaf Other Income & Other Expense di kolom F
        $sheet->setCellValue('A17', '9000-001');
        $sheet->setCellValue('F17', 'Pendapatan Atas Bunga Bank');
        $sheet->setCellValue('H17', 50);
        $sheet->setCellValue('FM17', 0);

        $sheet->setCellValue('A18', '8000-002');
        $sheet->setCellValue('F18', 'Biaya Administrasi Bank');
        $sheet->setCellValue('H18', 20);
        $sheet->setCellValue('FM18', 0);

        $service = new ExcelIngestionService;
        $service->ingestMjxMonthlySheets($spreadsheet, 'PL & BS Nov 26 MJX.xlsx', true);

        // Re-import periode yang sama harus menimpa bersih tanpa duplikasi
        $service->ingestMjxMonthlySheets($spreadsheet, 'PL & BS Nov 26 MJX.xlsx', true);

        $this->assertEquals(5, GlobalFinancialRecord::where('business_unit_code', 'xtrans')->where('period', '2026-11-01')->where('report_type', 'pnl')->count());

        $this->assertDatabaseMissing('global_financial_records', ['business_unit_code' => 'xtrans', 'period' => '2026-11-01', 'account_code' => '4000']);
        $this->assertDatabaseMissing('global_financial_records', ['business_unit_code' => 'xtrans', 'period' => '2026-11-01', 'account_code' => '5000']);
        $this->assertDatabaseMissing('global_financial_records', ['business_unit_code' => 'xtrans', 'period' => '2026-11-01', 'account_code' => '6000']);
        $this->assertDatabaseMissing('global_financial_records', ['business_unit_code' => 'xtrans', 'period' => '2026-11-01', 'account_code' => '6000-02']);

        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-11-01',
            'account_code' => '4000-01',
            'account_name' => 'Pendapatan Ekspedisi',
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-11-01',
            'account_code' => '6000-02-038',
            'account_name' => 'Biaya LAKA',
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-11-01',
            'account_code' => '9000-001',
            'category' => 'other_income',
            'account_name' => 'Pendapatan Atas Bunga Bank',
            'amount' => 50,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'xtrans',
            'period' => '2026-11-01',
            'account_code' => '8000-002',
            'category' => 'other_expense',
            'account_name' => 'Biaya Administrasi Bank',
            'amount' => 20,
        ]);
    }
}
