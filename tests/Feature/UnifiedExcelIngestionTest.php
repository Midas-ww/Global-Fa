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

        // Verify AQUA October 2026 P&L excludes 'a Head Office Cost adjustment' (-375.23M) so OPEX & Net Profit match Excel (AF116)
        $aquaTotals = GlobalFinancialRecord::where('business_unit_code', 'aqua')
            ->where('period', '2026-10-01')
            ->where('report_type', 'pnl')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $aquaNetProfit = (float) ($aquaTotals['revenue'] ?? 0)
            - (float) ($aquaTotals['cogs'] ?? 0)
            - (float) ($aquaTotals['opex'] ?? 0)
            + (float) ($aquaTotals['other_income'] ?? 0)
            - (float) ($aquaTotals['other_expense'] ?? 0);

        $this->assertEqualsWithDelta(8236196161.93, (float) ($aquaTotals['opex'] ?? 0), 0.05);
        $this->assertEqualsWithDelta(2431733305.89, $aquaNetProfit, 0.05);
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

    public function test_it_parses_and_reimports_mohala_and_sangria_pnl_and_neraca_idempotently(): void
    {
        $service = new ExcelIngestionService;

        // 1. Uji format sintetis Mohala PNL (termasuk Omset to Sangria negatif & Biaya THR negatif)
        $mohalaPnl = new Spreadsheet;
        $shM = $mohalaPnl->getActiveSheet();
        $shM->setTitle('PNL');
        $shM->setCellValue('A2', 'PNL');
        $shM->setCellValue('A3', 'MOHALA BAKERY');
        $shM->setCellValue('A4', 'JANUARI 2026');
        $shM->setCellValue('A7', 'Omset');
        $shM->setCellValue('E7', 1000000);
        $shM->setCellValue('A8', 'Omset to Sangria');
        $shM->setCellValue('E8', 250000);
        $shM->setCellValue('A9', 'Omset Lain-lain');
        $shM->setCellValue('E9', 50000);
        $shM->setCellValue('A18', 'HPP');
        $shM->setCellValue('F18', 300000);
        $shM->setCellValue('A21', 'BIAYA');
        $shM->setCellValue('A22', 'Biaya Gaji');
        $shM->setCellValue('E22', 120000);
        $shM->setCellValue('A23', 'Biaya THR 2026');
        $shM->setCellValue('E23', -20000);
        $shM->setCellValue('A24', 'TOTAL BIAYA');
        $shM->setCellValue('A25', 'Biaya Packing dan Perlengkapan Resto');
        $shM->setCellValue('E25', -5000);
        $shM->setCellValue('A27', 'Laba Bersih');
        $shM->setCellValue('F27', 405000);
        $shM->setCellValue('A35', 'Pendapatan Lain-lain:');
        $shM->setCellValue('E35', 999999); // Detail di bawah Laba Bersih harus diabaikan

        $tmpMohalaPnl = tempnam(sys_get_temp_dir(), 'mohala_pnl_').'.xlsx';
        (new Xlsx($mohalaPnl))->save($tmpMohalaPnl);

        $detectedMohalaPnl = $service->detectFileType($tmpMohalaPnl);
        $this->assertEquals('mohala_pnl', $detectedMohalaPnl['type']);

        $resM = $service->ingestSangriaMohalaPnlFile($tmpMohalaPnl, 'mohala', 'test_mohala_pnl.xlsx');
        $service->ingestSangriaMohalaPnlFile($tmpMohalaPnl, 'mohala', 'test_mohala_pnl.xlsx');

        $revM = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-01-01')->where('category', 'revenue')->sum('amount');
        $cogsM = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-01-01')->where('category', 'cogs')->sum('amount');
        $opexM = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-01-01')->where('category', 'opex')->sum('amount');
        $oiM = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-01-01')->where('category', 'other_income')->sum('amount');

        $this->assertEquals(['2026-01-01'], $resM['periods']);
        $this->assertEquals(800000.0, $revM);
        $this->assertEquals(300000.0, $cogsM);
        $this->assertEquals(95000.0, $opexM);
        $this->assertEquals(0.0, $oiM);
        $this->assertEquals(405000.0, ($revM - $cogsM - $opexM + $oiM));
        $this->assertEquals(7, GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('report_type', 'pnl')->count());

        @unlink($tmpMohalaPnl);

        // 2. Uji format sintetis Sangria Neraca multi-sheet
        $sangriaBs = new Spreadsheet;
        $shS1 = $sangriaBs->getActiveSheet();
        $shS1->setTitle('OKT 26');
        $shS1->setCellValue('B1', 'NERACA');
        $shS1->setCellValue('B2', 'SANGRIA RESTO');
        $shS1->setCellValue('B3', 'OKTOBER 2026');
        $shS1->setCellValue('B5', 'Kas');
        $shS1->setCellValue('D5', 1500000);
        $shS1->setCellValue('B6', 'TOTAL AKTIVA');
        $shS1->setCellValue('D6', 1500000);
        $shS1->setCellValue('F5', 'Hutang Supplier');
        $shS1->setCellValue('H5', 500000);
        $shS1->setCellValue('F6', 'Modal');
        $shS1->setCellValue('H6', 800000);
        $shS1->setCellValue('F7', 'Laba 2026');
        $shS1->setCellValue('H7', 200000);
        $shS1->setCellValue('F8', 'TOTAL PASIVA');
        $shS1->setCellValue('H8', 1500000);

        $tmpSangriaBs = tempnam(sys_get_temp_dir(), 'sangria_bs_').'.xlsx';
        (new Xlsx($sangriaBs))->save($tmpSangriaBs);

        $detectedSangriaBs = $service->detectFileType($tmpSangriaBs);
        $this->assertEquals('sangria_bs', $detectedSangriaBs['type']);

        $resS = $service->ingestUnifiedFile($tmpSangriaBs, 'test_sangria_neraca.xlsx', true);
        $this->assertEquals(['2026-10-01'], $resS['periods']);
        $this->assertEquals(1500000.0, (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('report_type', 'balance_sheet')->where('category', 'asset')->sum('amount'));
        $this->assertEquals(500000.0, (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('report_type', 'balance_sheet')->where('category', 'liability')->sum('amount'));
        $this->assertEquals(1000000.0, (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('report_type', 'balance_sheet')->where('category', 'equity')->sum('amount'));

        @unlink($tmpSangriaBs);
    }

    public function test_it_ingests_real_mohala_and_sangria_pnl_workbooks_with_zero_discrepancy(): void
    {
        $mohalaPath = 'D:/Excel Global Fa/2026 10 PNL MOHALA BAKERY.xlsx';
        $sangriaPath = 'D:/Excel Global Fa/2026 10 PNL SANGRIA RESTO.xlsx';

        if (! file_exists($mohalaPath) || ! file_exists($sangriaPath)) {
            $this->markTestSkipped('Real Mohala/Sangria PNL workbooks not found.');
        }

        $service = new ExcelIngestionService;

        $this->assertEquals('mohala_pnl', $service->detectFileType($mohalaPath)['type']);
        $this->assertEquals('sangria_pnl', $service->detectFileType($sangriaPath)['type']);

        $resM = $service->ingestSangriaMohalaPnlFile($mohalaPath, 'mohala');
        $resS = $service->ingestSangriaMohalaPnlFile($sangriaPath, 'sangria');

        $this->assertCount(10, $resM['periods']);
        $this->assertCount(10, $resS['periods']);

        $octMohalaRev = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-10-01')->where('category', 'revenue')->sum('amount');
        $octMohalaCogs = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-10-01')->where('category', 'cogs')->sum('amount');
        $octMohalaOpex = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-10-01')->where('category', 'opex')->sum('amount');
        $octMohalaOi = (float) GlobalFinancialRecord::where('business_unit_code', 'mohala')->where('period', '2026-10-01')->where('category', 'other_income')->sum('amount');

        $this->assertEqualsWithDelta(31556095.00, $octMohalaRev, 0.01);
        $this->assertEqualsWithDelta(9991488.00, $octMohalaCogs, 0.01);
        $this->assertEqualsWithDelta(22377108.75, $octMohalaOpex, 0.01);
        $this->assertEqualsWithDelta(0.00, $octMohalaOi, 0.01);
        $this->assertEqualsWithDelta(-812501.75, ($octMohalaRev - $octMohalaCogs - $octMohalaOpex + $octMohalaOi), 0.01);

        $octSangriaRev = (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('period', '2026-10-01')->where('category', 'revenue')->sum('amount');
        $octSangriaCogs = (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('period', '2026-10-01')->where('category', 'cogs')->sum('amount');
        $octSangriaOpex = (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('period', '2026-10-01')->where('category', 'opex')->sum('amount');
        $octSangriaOi = (float) GlobalFinancialRecord::where('business_unit_code', 'sangria')->where('period', '2026-10-01')->where('category', 'other_income')->sum('amount');

        $this->assertEqualsWithDelta(112359500.00, $octSangriaRev, 0.01);
        $this->assertEqualsWithDelta(51035060.00, $octSangriaCogs, 0.01);
        $this->assertEqualsWithDelta(107843701.55, $octSangriaOpex, 0.01);
        $this->assertEqualsWithDelta(2186074.00, $octSangriaOi, 0.01);
        $this->assertEqualsWithDelta(-44333187.55, ($octSangriaRev - $octSangriaCogs - $octSangriaOpex + $octSangriaOi), 0.01);
    }

    public function test_it_shows_mohala_sangria_satu_pintu_and_processes_preview_and_upload(): void
    {
        $admin = User::factory()->create([
            'email' => 'superadmin@mjg.com',
            'name' => 'Super Admin',
        ]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'mohala'], ['name' => 'Mohala', 'data_source' => 'excel', 'is_active' => true]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'sangria'], ['name' => 'Sangria', 'data_source' => 'excel', 'is_active' => true]);

        $syncResp = $this->actingAs($admin)->get(route('finance.sync'));
        $syncResp->assertOk();
        $syncResp->assertSee('Satu Pintu — Ferdinand Winoto');
        $syncResp->assertSee('Satu Pintu — Viona Delian (Mohala Bakery x Sangria Resto)');
        $syncResp->assertSee('Satu Pintu — Fransiska Lia (Kandang PT MJKB)');

        $spreadsheet = new Spreadsheet;
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('PNL');
        $sh->setCellValue('A2', 'PNL');
        $sh->setCellValue('A3', 'MOHALA BAKERY');
        $sh->setCellValue('A4', 'OKTOBER 2026');
        $sh->setCellValue('A7', 'Omset');
        $sh->setCellValue('E7', 500000);
        $sh->setCellValue('A18', 'HPP');
        $sh->setCellValue('F18', 200000);
        $sh->setCellValue('A27', 'Laba Bersih');
        $sh->setCellValue('F27', 300000);

        $tmpFile = tempnam(sys_get_temp_dir(), 'ms_pintu_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        // File Mohala harus cocok di kotak Satu Pintu Viona, dan ditolak di kotak Satu Pintu Ferdinand
        $previewFerdinandMismatch = $this->actingAs($admin)->postJson(route('finance.sync.preview'), [
            'file' => new UploadedFile($tmpFile, '2026 10 PNL MOHALA BAKERY.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'type' => 'ferdinand_satu_pintu',
        ]);
        $previewFerdinandMismatch->assertOk();
        $previewFerdinandMismatch->assertJson([
            'is_type_match' => false,
            'detected_type' => 'mohala_pnl',
        ]);

        $previewResp = $this->actingAs($admin)->postJson(route('finance.sync.preview'), [
            'file' => new UploadedFile($tmpFile, '2026 10 PNL MOHALA BAKERY.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'type' => 'mohala_sangria',
        ]);
        $previewResp->assertOk();
        $previewResp->assertJson([
            'is_type_match' => true,
            'detected_type' => 'mohala_pnl',
        ]);

        $uploadResp = $this->actingAs($admin)->post(route('finance.sync.excel'), [
            'file' => new UploadedFile($tmpFile, '2026 10 PNL MOHALA BAKERY.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'type' => 'mohala_sangria',
        ]);
        $uploadResp->assertRedirect();
        $uploadResp->assertSessionHas('success');

        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'mohala',
            'period' => '2026-10-01',
            'account_code' => '4001',
            'amount' => 500000,
        ]);

        @unlink($tmpFile);

        // Uji juga upload lewat Satu Pintu Ferdinand & Viona untuk berkas real jika tersedia
        $ferdinandFile = 'D:/Excel Global Fa/PL & BS Oct 26 MJP,MJX,dan MJS.xlsx';
        if (file_exists($ferdinandFile)) {
            $respFerdinand = $this->actingAs($admin)->post(route('finance.sync.excel'), [
                'file' => new UploadedFile($ferdinandFile, 'PL & BS Oct 26 MJP,MJX,dan MJS.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'type' => 'ferdinand_satu_pintu',
                'overwrite' => '1',
            ]);
            $respFerdinand->assertRedirect();
            $respFerdinand->assertSessionHas('success');
        }

        $sangriaPnlFile = 'D:/Excel Global Fa/2026 10 PNL SANGRIA RESTO.xlsx';
        if (file_exists($sangriaPnlFile)) {
            $respViona = $this->actingAs($admin)->post(route('finance.sync.excel'), [
                'file' => new UploadedFile($sangriaPnlFile, '2026 10 PNL SANGRIA RESTO.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'type' => 'mohala_sangria',
                'overwrite' => '1',
            ]);
            $respViona->assertRedirect();
            $respViona->assertSessionHas('success');
            $this->assertDatabaseHas('global_financial_records', [
                'business_unit_code' => 'sangria',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'account_code' => '4001',
            ]);
        }
    }
}
