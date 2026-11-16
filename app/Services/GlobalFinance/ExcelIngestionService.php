<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExcelIngestionService
{
    /**
     * Ingest file Excel Laporan Distributor FMCG (Multi-sheet: BMG, PWT, PML GT, dll)
     */
    public function ingestFmcgFile(string $filePath): array
    {
        ini_set('memory_limit', '1024M');
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();
        $totalRecords = 0;

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $rows = $sheet->toArray(null, true, false, true);

            if (empty($rows)) continue;

            $entityCode = strtoupper(str_replace(' ', '_', trim($sheetName)));

            // Header ada di row 1
            $headerRow = $rows[1] ?? [];
            $dateColumns = [];

            foreach ($headerRow as $colLetter => $val) {
                if (empty($val)) continue;
                $dateStr = null;

                if (is_numeric($val) && (float)$val > 40000 && (float)$val < 50000) {
                    $dateStr = ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                    $dateStr = trim($val);
                }

                if ($dateStr) {
                    $dateColumns[$colLetter] = $dateStr;
                }
            }

            // Hapus data lama untuk entitas ini
            GlobalFinancialRecord::query()
                ->where('business_unit_code', 'fmcg')
                ->where('entity_code', $entityCode)
                ->delete();

            $batch = [];
            $currentCategory = 'revenue';

            for ($r = 2; $r <= count($rows); $r++) {
                $row = $rows[$r] ?? [];
                $accCode = trim($row['A'] ?? '');
                
                // Cari deskripsi
                $desc = '';
                foreach (['F', 'B', 'C', 'D', 'E'] as $col) {
                    if (!empty($row[$col]) && !is_numeric($row[$col])) {
                        $desc = trim($row[$col]);
                        break;
                    }
                }

                if (empty($desc)) continue;

                // Identifikasi kategori
                $upperDesc = strtoupper($desc);
                if (str_contains($upperDesc, 'OPERATING REVENUE')) {
                    $currentCategory = 'revenue';
                    continue;
                } elseif (str_contains($upperDesc, 'COST OF GOODS SOLD')) {
                    $currentCategory = 'cogs';
                    continue;
                } elseif (str_contains($upperDesc, 'GROSS PROFIT')) {
                    $currentCategory = 'gross_profit';
                    continue;
                } elseif (str_contains($upperDesc, 'OPERATING EXPENSES')) {
                    $currentCategory = 'opex';
                    continue;
                } elseif (str_contains($upperDesc, 'OTHER INCOME') && !str_contains($upperDesc, 'EXPENSES')) {
                    $currentCategory = 'other_income';
                    continue;
                } elseif (str_contains($upperDesc, 'OTHER EXPENSES')) {
                    $currentCategory = 'other_expense';
                    continue;
                } elseif (str_contains($upperDesc, 'NET PROFIT')) {
                    $currentCategory = 'net_profit';
                    continue;
                }

                // Ambil nilai nominal untuk setiap kolom tanggal
                foreach ($dateColumns as $colLetter => $period) {
                    $val = $row[$colLetter] ?? 0;
                    if ($val === null || $val === '' || $val === '#DIV/0!' || $val === 'NaN') {
                        $val = 0;
                    }

                    $amount = (float) $val;
                    if ($amount == 0 && ($period >= '2026-10-01')) {
                        continue; // Lewati template kosong masa depan
                    }

                    $batch[] = [
                        'business_unit_code' => 'fmcg',
                        'entity_code' => $entityCode,
                        'period' => $period,
                        'report_type' => 'pnl',
                        'category' => $currentCategory,
                        'sub_category' => null,
                        'account_code' => $accCode ?: null,
                        'account_name' => $desc,
                        'amount' => $amount,
                        'status' => 'final',
                        'source_type' => 'excel_upload',
                        'notes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= 500) {
                        GlobalFinancialRecord::insert($batch);
                        $totalRecords += count($batch);
                        $batch = [];
                    }
                }
            }

            if (!empty($batch)) {
                GlobalFinancialRecord::insert($batch);
                $totalRecords += count($batch);
            }
        }

        return [
            'success' => true,
            'business_unit' => 'fmcg',
            'sheets_processed' => $sheetNames,
            'records_inserted' => $totalRecords,
        ];
    }

    /**
     * Ingest file Excel Ekspedisi (PT Mekar Jaya Xtrans Jaya)
     */
    public function ingestXtransFile(string $filePath): array
    {
        ini_set('memory_limit', '1024M');
        $spreadsheet = IOFactory::load($filePath);
        $totalRecords = 0;

        // --- 1. SHEET PL (Laba Rugi) ---
        $plSheet = $spreadsheet->getSheetByName('PL');
        if ($plSheet) {
            $rows = $plSheet->toArray(null, true, false, true);
            $headerRow = $rows[5] ?? []; // Row 5 ada tanggal-tanggal
            $dateColumns = [];

            foreach ($headerRow as $colLetter => $val) {
                if (empty($val)) continue;
                $dateStr = null;

                if (is_numeric($val) && (float)$val > 40000 && (float)$val < 50000) {
                    $dateStr = ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                    $dateStr = trim($val);
                }

                if ($dateStr) {
                    $dateColumns[$colLetter] = $dateStr;
                }
            }

            GlobalFinancialRecord::query()
                ->where('business_unit_code', 'xtrans')
                ->where('entity_code', 'XTRANS')
                ->where('report_type', 'pnl')
                ->delete();

            $batch = [];
            $currentCategory = 'revenue';

            for ($r = 6; $r <= count($rows); $r++) {
                $row = $rows[$r] ?? [];
                $accCode = trim($row['A'] ?? '');
                $accName = trim($row['B'] ?? '');

                if (empty($accName)) continue;

                $upper = strtoupper($accName);
                if (str_contains($upper, 'PENDAPATAN') || str_starts_with($accCode, '4000')) {
                    $currentCategory = 'revenue';
                }
                if (str_contains($upper, 'HARGA POKOK') || str_starts_with($accCode, '5000')) {
                    $currentCategory = 'cogs';
                }
                if (str_contains($upper, 'BIAYA UMUM') || str_starts_with($accCode, '6000')) {
                    $currentCategory = 'opex';
                }
                if (str_contains($upper, 'DILUAR USAHA') && str_starts_with($accCode, '8000')) {
                    $currentCategory = 'other_expense';
                }
                if (str_contains($upper, 'DILUAR USAHA') && str_starts_with($accCode, '9000')) {
                    $currentCategory = 'other_income';
                }

                // Lewati baris header/total murni jika tanpa akun
                if (empty($accCode) && (str_contains($upper, 'TOTAL') || str_contains($upper, 'LABA KOTOR'))) {
                    continue;
                }

                foreach ($dateColumns as $colLetter => $period) {
                    $val = $row[$colLetter] ?? 0;
                    if ($val === null || $val === '' || !is_numeric($val)) {
                        $val = 0;
                    }

                    $amount = (float) $val;

                    $batch[] = [
                        'business_unit_code' => 'xtrans',
                        'entity_code' => 'XTRANS',
                        'period' => $period,
                        'report_type' => 'pnl',
                        'category' => $currentCategory,
                        'sub_category' => null,
                        'account_code' => $accCode ?: null,
                        'account_name' => $accName,
                        'amount' => $amount,
                        'status' => 'final',
                        'source_type' => 'excel_upload',
                        'notes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= 500) {
                        GlobalFinancialRecord::insert($batch);
                        $totalRecords += count($batch);
                        $batch = [];
                    }
                }
            }

            if (!empty($batch)) {
                GlobalFinancialRecord::insert($batch);
                $totalRecords += count($batch);
            }
        }

        // --- 2. SHEET BS Det. (Neraca per 30 Sep 2026) ---
        $bsSheet = $spreadsheet->getSheetByName('BS Det.');
        if ($bsSheet) {
            $rows = $bsSheet->toArray(null, true, false, true);
            $period = '2026-09-01'; // As of September 30, 2026

            GlobalFinancialRecord::query()
                ->where('business_unit_code', 'xtrans')
                ->where('entity_code', 'XTRANS')
                ->where('report_type', 'balance_sheet')
                ->delete();

            $batchBs = [];
            $currentBsCategory = 'asset';

            foreach ($rows as $row) {
                $codeCol = trim($row['A'] ?? '');
                $accNo = trim($row['C'] ?? '');
                $accName = trim($row['D'] ?? '');
                $amountVal = $row['I'] ?? $row['H'] ?? $row['G'] ?? $row['E'] ?? 0;

                // Identifikasi kategori A-L
                if (in_array($codeCol, ['A', 'A.1', 'A.2', 'B', 'C', 'D', 'E', 'F', 'G'])) {
                    $currentBsCategory = 'asset';
                } elseif (in_array($codeCol, ['H', 'I', 'J', 'K'])) {
                    $currentBsCategory = 'liability';
                } elseif ($codeCol === 'L') {
                    $currentBsCategory = 'equity';
                }

                if (empty($accName) || !is_numeric($amountVal)) continue;

                $batchBs[] = [
                    'business_unit_code' => 'xtrans',
                    'entity_code' => 'XTRANS',
                    'period' => $period,
                    'report_type' => 'balance_sheet',
                    'category' => $currentBsCategory,
                    'sub_category' => $codeCol ?: null,
                    'account_code' => $accNo ?: null,
                    'account_name' => $accName,
                    'amount' => (float) $amountVal,
                    'status' => 'final',
                    'source_type' => 'excel_upload',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($batchBs)) {
                GlobalFinancialRecord::insert($batchBs);
                $totalRecords += count($batchBs);
            }
        }

        return [
            'success' => true,
            'business_unit' => 'xtrans',
            'records_inserted' => $totalRecords,
        ];
    }
}
