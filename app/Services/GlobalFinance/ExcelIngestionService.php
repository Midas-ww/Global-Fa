<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIngestionLog;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelIngestionService
{
    /**
     * Deteksi tipe unit bisnis dari file Excel berdasarkan lembar kerja dan teks header.
     *
     * @return array{type: string, label: string, sheets: array<string>}
     */
    public function detectFileType(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File tidak ditemukan: {$filePath}");
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $sheetNames = $reader->listWorksheetNames($filePath);

        // Fast probe: baca hanya sheet pertama baris 1-6
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $firstSheet = $spreadsheet->getSheet(0);

        $headerText = '';
        for ($r = 1; $r <= 6; $r++) {
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                $val = $firstSheet->getCell($col.$r)->getValue();
                if ($val !== null && $val !== '') {
                    $headerText .= ' '.(string) $val;
                }
            }
        }
        $upperHeader = strtoupper($headerText);
        $upperSheets = array_map('strtoupper', $sheetNames);

        // 0. Deteksi Template Universal Holding
        if (
            in_array('LAPORAN KEUANGAN', $upperSheets) ||
            str_contains($upperHeader, 'TEMPLATE IMPORT') ||
            str_contains($upperHeader, 'KODE CABANG') ||
            str_contains($upperHeader, 'KODE ENTITAS')
        ) {
            return [
                'type' => 'universal_template',
                'label' => 'Template Universal Holding',
                'sheets' => $sheetNames,
            ];
        }

        // 1. Deteksi Ekspedisi (PT Mekar Jaya Xtrans Jaya)
        if (
            str_contains($upperHeader, 'XTRANS') ||
            str_contains($upperHeader, 'PT MEKAR JAYA XTRANS') ||
            in_array('BS DET.', $upperSheets) ||
            (in_array('PL', $upperSheets) && in_array('BS DET.', $upperSheets))
        ) {
            return [
                'type' => 'xtrans',
                'label' => 'Ekspedisi (PT Mekar Jaya Xtrans Jaya)',
                'sheets' => $sheetNames,
            ];
        }

        // 2. Deteksi FMCG (CV Mekar Jaya Sentosa 9 Depo atau PT MJS Multikarya - CCOD)
        $fmcgDepoKnown = ['BMG', 'PWT', 'PML GT', 'PML MT', 'WLR', 'RDK', 'PWJ', 'KBM', 'PKL', 'TGL', 'KDS', 'CRB', 'KNG'];
        $hasDepoSheets = count(array_intersect($upperSheets, $fmcgDepoKnown)) > 0;
        // 2. Deteksi CCOD (PT Mekar Jaya Sentosa Multikarya - 12 Cabang)
        $isCcodDept = str_contains($upperHeader, 'MULTIKARYA') || str_contains($upperHeader, 'BY DEPARTMENT') || str_contains($upperHeader, 'CCOD');
        if ($isCcodDept) {
            return [
                'type' => 'ccod',
                'label' => 'MJS Multikarya (CCOD - 12 Cabang)',
                'sheets' => $sheetNames,
            ];
        }

        // 3. Deteksi FMCG (CV Mekar Jaya Sentosa - 9 Depo)
        if (
            str_contains($upperHeader, 'MEKAR JAYA SENTOSA') ||
            str_contains($upperHeader, 'CV MEKAR JAYA SENTOSA') ||
            str_contains($upperHeader, 'CV. MEKAR JAYA SENTOSA') ||
            str_contains($upperHeader, 'MJS') ||
            $hasDepoSheets ||
            (in_array('PL', $upperSheets) && in_array('BS', $upperSheets) && ! in_array('BS DET.', $upperSheets))
        ) {
            return [
                'type' => 'fmcg',
                'label' => 'Distribusi FMCG (CV Mekar Jaya Sentosa / 9 Depo Cabang)',
                'sheets' => $sheetNames,
            ];
        }

        // 3. Deteksi SPBU
        if (
            str_contains($upperHeader, 'SPBU') ||
            str_contains($upperHeader, 'PERTALITE') ||
            str_contains($upperHeader, 'PERTAMAX') ||
            str_contains($upperHeader, 'BIOSOLAR') ||
            in_array('DISPENSER', $upperSheets)
        ) {
            return [
                'type' => 'spbu',
                'label' => 'SPBU (PT Air Mekar Jaya)',
                'sheets' => $sheetNames,
            ];
        }

        // 4. Deteksi Retail Store
        if (
            str_contains($upperHeader, 'IQOS') ||
            str_contains($upperHeader, 'TEREA') ||
            str_contains($upperHeader, 'HEETS') ||
            str_contains($upperHeader, 'RETAIL')
        ) {
            return [
                'type' => 'iqos',
                'label' => 'Retail Store (PT Mekar Retail Jaya)',
                'sheets' => $sheetNames,
            ];
        }

        return [
            'type' => 'unknown',
            'label' => 'Format Tidak Dikenal',
            'sheets' => $sheetNames,
        ];
    }

    /**
     * Validasi apakah file Excel sesuai dengan unit bisnis yang dipilih oleh pengguna.
     *
     * @throws \InvalidArgumentException
     */
    public function validateBusinessUnit(string $filePath, string $selectedType): void
    {
        $detected = $this->detectFileType($filePath);

        // Jika file berupa template universal holding, selalu valid untuk semua unit bisnis
        if ($detected['type'] === 'universal_template') {
            return;
        }

        $selectedLabel = match ($selectedType) {
            'fmcg' => 'Distribusi FMCG (9 Depo Cabang / CV Mekar Jaya Sentosa)',
            'xtrans' => 'Ekspedisi (PT Mekar Jaya Xtrans Jaya)',
            'ccod' => 'MJS Multikarya (CCOD - 12 Cabang)',
            default => strtoupper($selectedType),
        };

        // Jika terdeteksi jelas sebagai unit bisnis yang berbeda
        if ($detected['type'] !== 'unknown' && $detected['type'] !== $selectedType) {
            throw new \InvalidArgumentException(
                "Validasi Format Gagal: File ini terdeteksi sebagai {$detected['label']}, bukan untuk {$selectedLabel}. ".
                "Mohon pastikan Anda mengunggah file pada kotak {$detected['label']}."
            );
        }

        // Jika format tidak dikenali secara umum, cek kriteria lembar kerja minimal
        if ($detected['type'] === 'unknown') {
            $upperSheets = array_map('strtoupper', $detected['sheets']);
            if ($selectedType === 'xtrans' && ! in_array('PL', $upperSheets) && ! in_array('BS DET.', $upperSheets)) {
                throw new \InvalidArgumentException(
                    "Validasi Format Gagal: File tidak memiliki sheet 'PL' atau 'BS Det.'. Format tidak sesuai dengan laporan resmi PT Mekar Jaya Xtrans Jaya."
                );
            }
            $fmcgDepoKnown = ['BMG', 'PWT', 'PML GT', 'PML MT', 'WLR', 'RDK', 'PWJ', 'KBM', 'PKL', 'TGL', 'KDS', 'CRB', 'KNG', 'PL', 'BS'];
            if ($selectedType === 'fmcg' && empty(array_intersect($upperSheets, $fmcgDepoKnown))) {
                throw new \InvalidArgumentException(
                    'Validasi Format Gagal: Format file tidak dikenali sebagai laporan Distribusi FMCG (tidak ditemukan sheet cabang depo maupun sheet laporan resmi MJS).'
                );
            }
        }
    }

    /**
     * Lakukan pratinjau dan analisis file Excel sebelum konfirmasi import (mirip sistem SIR-IQOS).
     *
     * @return array<string, mixed>
     */
    public function previewFile(string $filePath, string $targetType, ?string $filename = null): array
    {
        ini_set('memory_limit', '1024M');
        $filename = $filename ?: basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $fileHash = file_exists($filePath) ? hash_file('sha256', $filePath) : '';

        $sizeFormatted = $fileSize > 1048576
            ? round($fileSize / 1048576, 2).' MB'
            : round($fileSize / 1024, 1).' KB';

        $detected = $this->detectFileType($filePath);
        $detectedType = $detected['type'];
        $detectedLabel = $detected['label'];
        $sheets = $detected['sheets'] ?? [];

        $unit = GlobalBusinessUnit::where('code', $targetType)->first();
        $targetLabel = $unit ? $unit->name : strtoupper($targetType);

        // Kesesuaian tipe usaha
        $isTypeMatch = ($detectedType === 'universal_template' || $detectedType === $targetType);
        $matchError = null;
        if (! $isTypeMatch && $detectedType !== 'unknown') {
            $matchError = "File ini terdeteksi sebagai '{$detectedLabel}', yang tidak cocok untuk diimpor ke unit '{$targetLabel}'.";
        } elseif ($detectedType === 'unknown') {
            $matchError = 'Format file tidak dapat dikenali secara otomatis. Pastikan menggunakan format template resmi holding.';
        }

        // Cek duplikasi di log ingestion
        $existingLog = GlobalIngestionLog::query()
            ->where('business_unit_code', $targetType)
            ->where('source_type', 'excel_upload')
            ->where('status', 'success')
            ->where(function ($q) use ($filename, $fileHash) {
                $q->where('filename', $filename)
                    ->orWhere('details', 'like', '%"file_hash":"'.$fileHash.'"%');
            })
            ->latest()
            ->first();

        $isDuplicate = (bool) $existingLog;
        $duplicateInfo = $existingLog ? [
            'date' => $existingLog->created_at->translatedFormat('d M Y H:i'),
            'records_count' => $existingLog->records_count,
            'message' => "File ini sudah pernah diimpor pada {$existingLog->created_at->translatedFormat('d M Y H:i')} ({$existingLog->records_count} baris data tersimpan).",
        ] : null;

        // Ekstrak ringkasan isi
        $summary = $this->extractPreviewSummary($filePath, $detectedType, $sheets);

        return [
            'success' => true,
            'filename' => $filename,
            'file_size' => $sizeFormatted,
            'file_hash' => $fileHash,
            'detected_type' => $detectedType,
            'detected_label' => $detectedLabel,
            'target_type' => $targetType,
            'target_label' => $targetLabel,
            'is_type_match' => $isTypeMatch,
            'match_error' => $matchError,
            'is_duplicate' => $isDuplicate,
            'duplicate_info' => $duplicateInfo,
            'sheets' => $sheets,
            'summary' => $summary,
            'can_import' => $isTypeMatch,
        ];
    }

    /**
     * Ekstrak ringkasan isi file Excel untuk pratinjau sebelum import.
     */
    protected function extractPreviewSummary(string $filePath, string $type, array $sheets): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $upperSheets = array_map('strtoupper', $sheets);

            $periods = [];
            $sampleRows = [];
            $totalRevenueLatest = 0.0;
            $estimatedRecords = 0;
            $latestPeriod = null;

            // Kasus 1: Laporan Resmi MJS (PL & BS) atau Ekspedisi MJX (PL & BS Det.)
            if (in_array('PL', $upperSheets)) {
                $plSheet = $spreadsheet->getSheetByName('PL');
                $rows = $plSheet ? $plSheet->toArray(null, true, false, true) : [];
                $headerRow = $rows[5] ?? [];
                $dateCols = [];

                foreach ($headerRow as $col => $val) {
                    if (empty($val)) {
                        continue;
                    }
                    $d = null;
                    if (is_numeric($val) && (float) $val > 40000 && (float) $val < 50000) {
                        $d = ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                        $d = trim($val);
                    }
                    if ($d) {
                        $dateCols[$col] = $d;
                        $periods[] = $d;
                    }
                }

                $latestCol = array_key_last($dateCols);
                $latestPeriod = $dateCols[$latestCol] ?? null;

                for ($r = 6; $r <= count($rows); $r++) {
                    $row = $rows[$r] ?? [];
                    $code = trim($row['A'] ?? '');
                    $name = trim($row['B'] ?? '');
                    if (empty($name)) {
                        continue;
                    }
                    $val = (float) ($row[$latestCol] ?? 0);
                    $estimatedRecords += count($dateCols);

                    if (count($sampleRows) < 5 && ! empty($code)) {
                        $sampleRows[] = [
                            'code' => $code,
                            'name' => $name,
                            'amount' => $val,
                            'formatted_amount' => 'Rp '.number_format($val, 0, ',', '.'),
                        ];
                    }

                    if (str_starts_with($code, '4000') || stripos($name, 'penjualan') !== false || stripos($name, 'pendapatan') !== false) {
                        $totalRevenueLatest += $val;
                    }
                }
            }
            // Kasus 2: Laporan per Departemen CCOD (PT MJS Multikarya - Profit & Loss by Department)
            $firstSheet = $spreadsheet->getSheet(0);
            $r1 = strtoupper((string) $firstSheet->getCell('A1')->getValue().' '.(string) $firstSheet->getCell('B1')->getValue());
            $r2 = strtoupper((string) $firstSheet->getCell('A2')->getValue().' '.(string) $firstSheet->getCell('B2')->getValue());
            $r5 = strtoupper((string) $firstSheet->getCell('A5')->getValue().' '.(string) $firstSheet->getCell('B5')->getValue().' '.(string) $firstSheet->getCell('C5')->getValue());
            $isDeptFile = str_contains($r1, 'MULTIKARYA') || str_contains($r2, 'BY DEPARTMENT') || str_contains($r5, 'CCOD');

            if ($isDeptFile) {
                $rows = $firstSheet->toArray(null, true, false, true);
                $periodText = implode(' ', array_filter($rows[3] ?? []));
                $period = '2026-09-01';
                if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i', $periodText, $matches)) {
                    try {
                        $period = Carbon::parse($matches[1].' '.$matches[2].' '.$matches[3])->startOfMonth()->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $period = '2026-09-01';
                    }
                }
                $periods = [$period];
                $latestPeriod = $period;

                $headerRow = $rows[5] ?? [];
                $deptCols = [];
                foreach ($headerRow as $colLetter => $val) {
                    $valTrim = trim((string) $val);
                    if (! empty($valTrim) && ! in_array(strtoupper($valTrim), ['GL ACCOUNT NO.', 'DESCRIPTION', 'NO', 'AKUN', 'TOTAL'])) {
                        $deptCols[$colLetter] = $valTrim;
                    }
                }

                $validLeafPattern = '/^(4000-01|4000-02|5000(\.0)?$|6000-01-01|6000-01-02|6000-01-03|6000-03-00|7000-0|9000-0|8000-0)/';
                for ($r = 6; $r <= count($rows); $r++) {
                    $row = $rows[$r] ?? [];
                    $code = trim((string) ($row['A'] ?? ''));
                    $name = trim((string) ($row['B'] ?? ''));
                    if (! preg_match($validLeafPattern, $code)) {
                        continue;
                    }

                    $estimatedRecords += count($deptCols);
                    $firstCol = array_key_first($deptCols);
                    $val = (float) ($row[$firstCol] ?? 0);

                    if (count($sampleRows) < 5) {
                        $sampleRows[] = [
                            'code' => $code,
                            'name' => $name,
                            'amount' => $val,
                            'formatted_amount' => 'Rp '.number_format($val, 0, ',', '.'),
                        ];
                    }

                    if (str_starts_with($code, '4000')) {
                        foreach ($deptCols as $cl => $dn) {
                            $totalRevenueLatest += (float) ($row[$cl] ?? 0);
                        }
                    }
                }
            }
            // Kasus 3: Multi-Depo Cabang FMCG (Sheet BMG, PWT, PML GT, dll)
            elseif ($type === 'fmcg' || count(array_intersect($upperSheets, ['BMG', 'PWT', 'PML GT', 'WLR'])) > 0) {
                $firstSheet = $spreadsheet->getSheet(0);
                $rows = $firstSheet ? $firstSheet->toArray(null, true, false, true) : [];
                $headerRow = $rows[1] ?? [];
                $dateCols = [];

                foreach ($headerRow as $col => $val) {
                    if (empty($val)) {
                        continue;
                    }
                    $d = null;
                    if (is_numeric($val) && (float) $val > 40000 && (float) $val < 50000) {
                        $d = ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                        $d = trim($val);
                    }
                    if ($d) {
                        $dateCols[$col] = $d;
                        $periods[] = $d;
                    }
                }

                $latestCol = array_key_last($dateCols);
                $latestPeriod = $dateCols[$latestCol] ?? null;
                $accountsInSheet = 0;

                for ($r = 2; $r <= count($rows); $r++) {
                    $row = $rows[$r] ?? [];
                    $code = trim($row['A'] ?? '');
                    $name = trim($row['B'] ?? '');
                    if (empty($name)) {
                        continue;
                    }
                    $accountsInSheet++;
                    $val = (float) ($row[$latestCol] ?? 0);

                    if (count($sampleRows) < 5 && ! empty($code)) {
                        $sampleRows[] = [
                            'code' => $code,
                            'name' => $name,
                            'amount' => $val,
                            'formatted_amount' => 'Rp '.number_format($val, 0, ',', '.'),
                        ];
                    }

                    if (str_starts_with($code, '4000') || stripos($name, 'penjualan') !== false || stripos($name, 'operating revenue') !== false) {
                        $totalRevenueLatest += $val;
                    }
                }

                $estimatedRecords = count($sheets) * $accountsInSheet * count($dateCols);
            }
            // Kasus 3: Template Universal Holding
            elseif ($type === 'universal_template' || in_array('LAPORAN KEUANGAN', $upperSheets)) {
                $sheet = $spreadsheet->getSheet(0);
                $rows = $sheet ? $sheet->toArray(null, true, false, true) : [];
                for ($r = 5; $r <= count($rows); $r++) {
                    $row = $rows[$r] ?? [];
                    $period = trim($row['B'] ?? '');
                    $category = trim($row['D'] ?? '');
                    $code = trim($row['E'] ?? '');
                    $name = trim($row['F'] ?? '');
                    $val = (float) ($row['G'] ?? 0);

                    if (empty($name) && empty($period)) {
                        continue;
                    }

                    $estimatedRecords++;
                    if (! empty($period)) {
                        $periods[] = $period;
                    }

                    if (count($sampleRows) < 5 && ! empty($name)) {
                        $sampleRows[] = [
                            'code' => $code ?: '-',
                            'name' => $name,
                            'amount' => $val,
                            'formatted_amount' => 'Rp '.number_format($val, 0, ',', '.'),
                        ];
                    }

                    if ($category === 'revenue') {
                        $totalRevenueLatest += $val;
                    }
                }
                $latestPeriod = ! empty($periods) ? max($periods) : null;
            }
            // Kasus 4: File Lain (General Reader)
            else {
                $sheet = $spreadsheet->getSheet(0);
                $rows = $sheet ? $sheet->toArray(null, true, false, true) : [];
                for ($r = 1; $r <= min(count($rows), 30); $r++) {
                    $row = $rows[$r] ?? [];
                    $code = trim($row['A'] ?? '');
                    $name = trim($row['B'] ?? '');
                    if (! empty($name) && count($sampleRows) < 5) {
                        $val = (float) ($row['C'] ?? 0);
                        $sampleRows[] = [
                            'code' => $code ?: '-',
                            'name' => $name,
                            'amount' => $val,
                            'formatted_amount' => 'Rp '.number_format($val, 0, ',', '.'),
                        ];
                    }
                }
                $estimatedRecords = count($rows);
            }

            $uniquePeriods = array_values(array_unique(array_filter($periods)));
            sort($uniquePeriods);

            $periodRangeLabel = '-';
            if (! empty($uniquePeriods)) {
                $minP = Carbon::parse(min($uniquePeriods))->translatedFormat('F Y');
                $maxP = Carbon::parse(max($uniquePeriods))->translatedFormat('F Y');
                $periodRangeLabel = ($minP === $maxP) ? $minP : "{$minP} s.d. {$maxP}";
            }

            return [
                'sheets_count' => count($sheets),
                'periods_count' => count($uniquePeriods),
                'periods' => $uniquePeriods,
                'period_range' => $periodRangeLabel,
                'latest_period' => $latestPeriod ? Carbon::parse($latestPeriod)->translatedFormat('F Y') : '-',
                'estimated_records' => $estimatedRecords,
                'total_revenue_latest' => $totalRevenueLatest,
                'total_revenue_formatted' => $totalRevenueLatest > 0 ? 'Rp '.number_format($totalRevenueLatest, 0, ',', '.') : '-',
                'sample_rows' => $sampleRows,
            ];
        } catch (\Throwable $e) {
            return [
                'sheets_count' => count($sheets),
                'periods_count' => 0,
                'periods' => [],
                'period_range' => 'Gagal membaca periode',
                'latest_period' => '-',
                'estimated_records' => 0,
                'total_revenue_latest' => 0,
                'total_revenue_formatted' => '-',
                'sample_rows' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ingest file Excel Laporan Distributor FMCG (Multi-sheet 9 Depo atau Laporan Resmi MJS).
     */
    public function ingestFmcgFile(string $filePath, ?string $filename = null): array
    {
        ini_set('memory_limit', '1024M');
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();
        $upperSheets = array_map('strtoupper', $sheetNames);

        // 0. Cek apakah file berupa laporan "Profit & Loss by Department" (PT MJS Multikarya - CCOD)
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $r1 = strtoupper((string) $sheet->getCell('A1')->getValue().' '.(string) $sheet->getCell('B1')->getValue());
            $r2 = strtoupper((string) $sheet->getCell('A2')->getValue().' '.(string) $sheet->getCell('B2')->getValue());
            $r5 = strtoupper((string) $sheet->getCell('A5')->getValue().' '.(string) $sheet->getCell('B5')->getValue().' '.(string) $sheet->getCell('C5')->getValue());

            if (str_contains($r1, 'MULTIKARYA') || str_contains($r2, 'BY DEPARTMENT') || str_contains($r5, 'CCOD')) {
                return $this->ingestFmcgDepartmentFile($sheet, $filename);
            }
        }

        // Jika file berformat laporan resmi CV Mekar Jaya Sentosa (Sheet PL & BS)
        $fmcgDepoKnown = ['BMG', 'PWT', 'PML GT', 'PML MT', 'WLR', 'RDK', 'PWJ', 'KBM', 'PKL'];
        $hasDepoSheets = count(array_intersect($upperSheets, $fmcgDepoKnown)) > 0;

        if (! $hasDepoSheets && in_array('PL', $upperSheets) && (in_array('BS', $upperSheets) || in_array('BS DET.', $upperSheets))) {
            return $this->ingestMjsSummaryFile($spreadsheet, $filename);
        }

        $totalRecords = 0;

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $rows = $sheet->toArray(null, true, false, true);

            if (empty($rows)) {
                continue;
            }

            $entityCode = strtoupper(str_replace(' ', '_', trim($sheetName)));

            // Header ada di row 1
            $headerRow = $rows[1] ?? [];
            $dateColumns = [];

            foreach ($headerRow as $colLetter => $val) {
                if (empty($val)) {
                    continue;
                }
                $dateStr = null;

                if (is_numeric($val) && (float) $val > 40000 && (float) $val < 50000) {
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
                $accCode = trim((string) ($row['A'] ?? ''));

                // Hanya ambil baris yang memiliki kode akun resmi FMCG (4000, 5000, 6000, 8000, 9000, atau a)
                if (! preg_match('/^(4000|5000|6000|8000|9000|a)/i', $accCode)) {
                    continue;
                }

                // Cari deskripsi
                $desc = '';
                foreach (['F', 'B', 'C', 'D', 'E'] as $col) {
                    if (! empty($row[$col]) && ! is_numeric($row[$col])) {
                        $desc = trim($row[$col]);
                        break;
                    }
                }

                if (empty($desc)) {
                    continue;
                }

                // Tentukan kategori dari kode akun secara presisi
                $currentCategory = 'opex';
                if (str_starts_with($accCode, '4000')) {
                    $currentCategory = 'revenue';
                } elseif (str_starts_with($accCode, '5000')) {
                    $currentCategory = 'cogs';
                } elseif (str_starts_with($accCode, '6000') || strtolower($accCode) === 'a') {
                    $currentCategory = 'opex';
                } elseif (str_starts_with($accCode, '8000')) {
                    $currentCategory = 'other_expense';
                } elseif (str_starts_with($accCode, '9000')) {
                    $currentCategory = 'other_income';
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
                        'notes' => $filename ?: 'PL Depo Persentase MJP Jan 24 - Dec 26.xlsx',
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

            if (! empty($batch)) {
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
     * Ingest format "Profit & Loss by Department" (PT Mekar Jaya Sentosa Multikarya - CCOD per cabang).
     */
    public function ingestFmcgDepartmentFile($sheetOrRows, ?string $filename = null): array
    {
        ini_set('memory_limit', '1024M');
        if (is_string($sheetOrRows)) {
            $spreadsheet = IOFactory::load($sheetOrRows);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, true);
        } elseif (is_array($sheetOrRows)) {
            $rows = $sheetOrRows;
        } else {
            $rows = $sheetOrRows->toArray(null, true, false, true);
        }

        // 1. Ekstrak Periode dari Baris 3 (misal: "From 01 Sep 2026 to 30 Sep 2026")
        $periodText = '';
        foreach (['A', 'B', 'C', 'D'] as $col) {
            if (! empty($rows[3][$col])) {
                $periodText .= ' '.$rows[3][$col];
            }
        }

        $period = '2026-09-01'; // Default
        if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i', $periodText, $matches)) {
            try {
                $parsedDate = Carbon::parse($matches[1].' '.$matches[2].' '.$matches[3]);
                $period = $parsedDate->startOfMonth()->format('Y-m-d');
            } catch (\Throwable $e) {
                $period = '2026-09-01';
            }
        }

        // 2. Baca Kolom Departemen (Row 5)
        $headerRow = $rows[5] ?? [];
        $deptCols = [];
        foreach ($headerRow as $colLetter => $val) {
            $valTrim = trim((string) $val);
            if (! empty($valTrim) && ! in_array(strtoupper($valTrim), ['GL ACCOUNT NO.', 'DESCRIPTION', 'NO', 'AKUN', 'TOTAL'])) {
                $deptCols[$colLetter] = $valTrim;
            }
        }

        // 3. Pastikan Unit Bisnis CCOD Ada
        $ccodBu = GlobalBusinessUnit::firstOrCreate(
            ['code' => 'ccod'],
            [
                'name' => 'MJS Multikarya (CCOD)',
                'description' => 'PT Mekar Jaya Sentosa Multikarya - Jasa dan distribusi Cash Collection on Delivery (CCOD) 12 cabang.',
                'icon' => 'shopping-bag',
                'color' => '#f59e0b',
                'data_source' => 'excel',
                'sort_order' => 5,
                'is_active' => true,
            ]
        );

        // 4. Daftarkan Entitas CCOD ke global_entities jika belum ada
        $deptCodeMap = [
            'CCOD PATI' => 'CCOD_PATI',
            'CCOD SEMARANG' => 'CCOD_SMG',
            'CCOD PEKALONGAN' => 'CCOD_PKL',
            'CCOD TEGAL' => 'CCOD_TGL',
            'CCOD BANDUNG' => 'CCOD_BDG',
            'CCOD SUMEDANG' => 'CCOD_SMD',
            'CCOD SALATIGA' => 'CCOD_SLT',
            'CCOD MAGELANG' => 'CCOD_MGL',
            'CCOD CIREBON' => 'CCOD_CRB',
            'CCOD JATIWANGI' => 'CCOD_JTW',
            'CCOD KUNINGAN' => 'CCOD_KNG',
            'CCOD LOSARANG' => 'CCOD_LSR',
        ];

        $entityMap = [];
        $sortIdx = 20;
        foreach ($deptCols as $colLetter => $deptName) {
            $upperDept = strtoupper($deptName);
            $entCode = $deptCodeMap[$upperDept] ?? strtoupper(str_replace(['CCOD ', ' '], ['', '_'], $deptName));

            GlobalEntity::updateOrCreate(
                ['code' => $entCode],
                [
                    'business_unit_id' => $ccodBu->id,
                    'name' => $deptName,
                    'location' => str_replace('CCOD ', '', $deptName),
                    'sort_order' => $sortIdx++,
                    'is_active' => true,
                ]
            );

            $entityMap[$colLetter] = $entCode;
        }

        // 5. Hapus data lama untuk entitas dan periode ini
        $entCodes = array_values($entityMap);
        GlobalFinancialRecord::query()
            ->where('business_unit_code', 'ccod')
            ->whereIn('entity_code', $entCodes)
            ->where('period', $period)
            ->where('report_type', 'pnl')
            ->delete();

        // 6. Pola Akun Leaf (Akun rincian transaksi agar tidak terjadi double counting)
        $validLeafPattern = '/^(4000-01|4000-02|5000(\.0)?$|6000-01-01|6000-01-02|6000-01-03|6000-03-00|7000-0|9000-0|8000-0)/';

        $batch = [];
        $totalRecords = 0;

        for ($r = 6; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $accCode = trim((string) ($row['A'] ?? ''));
            $desc = trim((string) ($row['B'] ?? ''));

            if (! preg_match($validLeafPattern, $accCode)) {
                continue;
            }

            $cat = 'opex';
            if (str_starts_with($accCode, '4000')) {
                $cat = 'revenue';
            } elseif (str_starts_with($accCode, '5000')) {
                $cat = 'cogs';
            } elseif (str_starts_with($accCode, '6000') || str_starts_with($accCode, '7000')) {
                $cat = 'opex';
            } elseif (str_starts_with($accCode, '8000')) {
                $cat = 'other_expense';
            } elseif (str_starts_with($accCode, '9000')) {
                $cat = 'other_income';
            }

            foreach ($deptCols as $colLetter => $deptName) {
                $entCode = $entityMap[$colLetter];
                $val = $row[$colLetter] ?? 0;
                if (! is_numeric($val)) {
                    $val = 0;
                }
                $amount = (float) $val;

                $batch[] = [
                    'business_unit_code' => 'ccod',
                    'entity_code' => $entCode,
                    'period' => $period,
                    'report_type' => 'pnl',
                    'category' => $cat,
                    'sub_category' => null,
                    'account_code' => $accCode,
                    'account_name' => $desc,
                    'amount' => $amount,
                    'status' => 'final',
                    'source_type' => 'excel_upload',
                    'notes' => $filename ?: 'Profit & Loss by Department PT MJS Multikarya',
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

        if (! empty($batch)) {
            GlobalFinancialRecord::insert($batch);
            $totalRecords += count($batch);
        }

        GlobalIngestionLog::create([
            'business_unit_code' => 'ccod',
            'source_type' => 'excel_upload',
            'filename' => $filename ?: 'Profit & Loss by Department (PT MJS Multikarya)',
            'records_count' => $totalRecords,
            'status' => 'success',
            'details' => json_encode([
                'type' => 'department_pnl',
                'period' => $period,
                'entities_count' => count($deptCols),
                'departments' => array_values($deptCols),
            ]),
        ]);

        return [
            'success' => true,
            'business_unit' => 'ccod',
            'records_inserted' => $totalRecords,
            'departments_count' => count($deptCols),
            'period' => $period,
        ];
    }

    /**
     * Ingest file Excel Ekspedisi (PT Mekar Jaya Xtrans Jaya)
     */
    public function ingestXtransFile(string $filePath, ?string $filename = null): array
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
                if (empty($val)) {
                    continue;
                }
                $dateStr = null;

                if (is_numeric($val) && (float) $val > 40000 && (float) $val < 50000) {
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

                if (empty($accName)) {
                    continue;
                }

                $upper = strtoupper($accName);
                if (str_starts_with($accCode, '4000') || (empty($accCode) && str_contains($upper, 'PENDAPATAN') && ! str_contains($upper, 'DILUAR'))) {
                    $currentCategory = 'revenue';
                } elseif (str_starts_with($accCode, '5000') || (empty($accCode) && str_contains($upper, 'HARGA POKOK'))) {
                    $currentCategory = 'cogs';
                } elseif (str_starts_with($accCode, '6000') || (empty($accCode) && str_contains($upper, 'BIAYA UMUM'))) {
                    $currentCategory = 'opex';
                } elseif (str_starts_with($accCode, '8000') || (empty($accCode) && str_contains($upper, 'BIAYA DILUAR USAHA'))) {
                    $currentCategory = 'other_expense';
                } elseif (str_starts_with($accCode, '9000') || (empty($accCode) && str_contains($upper, 'PENDAPATAN DILUAR USAHA'))) {
                    $currentCategory = 'other_income';
                }

                // Lewati baris header/total murni jika tanpa akun
                if (empty($accCode) && (str_contains($upper, 'TOTAL') || str_contains($upper, 'LABA KOTOR'))) {
                    continue;
                }

                foreach ($dateColumns as $colLetter => $period) {
                    $val = $row[$colLetter] ?? 0;
                    if ($val === null || $val === '' || ! is_numeric($val)) {
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
                        'notes' => $filename ?: 'PL & BS Detail MJX Sept 26.xlsx',
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

            if (! empty($batch)) {
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
            $currentSubCategory = null;
            $sumAsset = 0;
            $sumLiab = 0;
            $sumEquity = 0;

            foreach ($rows as $row) {
                $codeCol = trim((string) ($row['A'] ?? ''));
                $accNo = trim((string) ($row['C'] ?? ''));
                $accName = trim((string) ($row['D'] ?? ''));
                $amountVal = $row['I'] ?? null;

                // Identifikasi kategori A-L
                if (in_array($codeCol, ['A', 'A.1', 'A.2', 'B', 'C', 'D', 'E', 'F', 'G'])) {
                    $currentBsCategory = 'asset';
                    $currentSubCategory = $codeCol;
                } elseif (in_array($codeCol, ['H', 'I', 'J', 'K'])) {
                    $currentBsCategory = 'liability';
                    $currentSubCategory = $codeCol;
                } elseif ($codeCol === 'L') {
                    $currentBsCategory = 'equity';
                    $currentSubCategory = $codeCol;
                }

                // Hanya ambil baris yang memiliki nomor akun valid (misal: diawali angka 1, 2, atau 3)
                if (! preg_match('/^[1-3]\d{3}/', $accNo)) {
                    continue;
                }

                // Lewati baris rekapitulasi/subtotal
                if (str_starts_with(strtoupper($accNo), 'TOTAL') || str_starts_with(strtoupper($accName), 'TOTAL')) {
                    continue;
                }

                if (empty($accName) || ! is_numeric($amountVal)) {
                    continue;
                }

                $amount = (float) $amountVal;
                if ($currentBsCategory === 'asset') {
                    $sumAsset += $amount;
                } elseif ($currentBsCategory === 'liability') {
                    $sumLiab += $amount;
                } elseif ($currentBsCategory === 'equity') {
                    $sumEquity += $amount;
                }

                $batchBs[] = [
                    'business_unit_code' => 'xtrans',
                    'entity_code' => 'XTRANS',
                    'period' => $period,
                    'report_type' => 'balance_sheet',
                    'category' => $currentBsCategory,
                    'sub_category' => $currentSubCategory,
                    'account_code' => $accNo,
                    'account_name' => $accName,
                    'amount' => $amount,
                    'status' => 'final',
                    'source_type' => 'excel_upload',
                    'notes' => $filename ?: 'PL & BS Detail MJX Sept 26.xlsx',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Hitung Laba/Rugi Tahun Berjalan 2026 agar Neraca Konsolidasi Balance Sempurna
            $netIncomeCurrentYear = $sumAsset - ($sumLiab + $sumEquity);
            if (abs($netIncomeCurrentYear) > 0.01) {
                $batchBs[] = [
                    'business_unit_code' => 'xtrans',
                    'entity_code' => 'XTRANS',
                    'period' => $period,
                    'report_type' => 'balance_sheet',
                    'category' => 'equity',
                    'sub_category' => 'L',
                    'account_code' => '320002',
                    'account_name' => 'Laba (Rugi) Tahun Berjalan 2026',
                    'amount' => (float) $netIncomeCurrentYear,
                    'status' => 'final',
                    'source_type' => 'excel_upload',
                    'notes' => $filename ?: 'PL & BS Detail MJX Sept 26.xlsx',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($batchBs)) {
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

    /**
     * Ingest file Excel Laporan Bulanan Resmi MJS (CV Mekar Jaya Sentosa - Sheet PL & BS).
     */
    protected function ingestMjsSummaryFile(Spreadsheet $spreadsheet, ?string $filename = null): array
    {
        $totalRecords = 0;

        // 1. SHEET PL (Laba Rugi CV Mekar Jaya Sentosa)
        $plSheet = $spreadsheet->getSheetByName('PL');
        if ($plSheet) {
            $rows = $plSheet->toArray(null, true, false, true);
            $headerRow = $rows[5] ?? [];
            $dateColumns = [];

            foreach ($headerRow as $colLetter => $val) {
                if (empty($val)) {
                    continue;
                }
                $dateStr = null;

                if (is_numeric($val) && (float) $val > 40000 && (float) $val < 50000) {
                    $dateStr = ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                    $dateStr = trim($val);
                }

                if ($dateStr) {
                    $dateColumns[$colLetter] = $dateStr;
                }
            }

            GlobalFinancialRecord::query()
                ->where('business_unit_code', 'fmcg')
                ->where('entity_code', 'MJS')
                ->where('report_type', 'pnl')
                ->delete();

            $batch = [];
            $currentCategory = 'revenue';

            for ($r = 6; $r <= count($rows); $r++) {
                $row = $rows[$r] ?? [];
                $accCode = trim($row['A'] ?? '');
                $accName = trim($row['B'] ?? '');

                if (empty($accName)) {
                    continue;
                }

                $upper = strtoupper($accName);
                if (str_contains($upper, 'REVENUE') || str_contains($upper, 'PENDAPATAN') || str_starts_with($accCode, '4000')) {
                    $currentCategory = 'revenue';
                }
                if (str_contains($upper, 'COST OF GOODS SOLD') || str_contains($upper, 'HARGA POKOK') || str_starts_with($accCode, '5000')) {
                    $currentCategory = 'cogs';
                }
                if (str_contains($upper, 'OPERATING EXPENSES') || str_contains($upper, 'BIAYA OPERASIONAL') || str_starts_with($accCode, '6000')) {
                    $currentCategory = 'opex';
                }
                if (str_contains($upper, 'OTHER EXPENSE') || (str_contains($upper, 'DILUAR USAHA') && str_starts_with($accCode, '8000'))) {
                    $currentCategory = 'other_expense';
                }
                if (str_contains($upper, 'OTHER INCOME') || (str_contains($upper, 'DILUAR USAHA') && str_starts_with($accCode, '9000'))) {
                    $currentCategory = 'other_income';
                }

                if (empty($accCode) && (str_contains($upper, 'TOTAL') || str_contains($upper, 'GROSS PROFIT') || str_contains($upper, 'LABA'))) {
                    continue;
                }

                foreach ($dateColumns as $colLetter => $period) {
                    $val = $row[$colLetter] ?? 0;
                    if ($val === null || $val === '' || ! is_numeric($val)) {
                        $val = 0;
                    }

                    $batch[] = [
                        'business_unit_code' => 'fmcg',
                        'entity_code' => 'MJS',
                        'period' => $period,
                        'report_type' => 'pnl',
                        'category' => $currentCategory,
                        'sub_category' => null,
                        'account_code' => $accCode ?: null,
                        'account_name' => $accName,
                        'amount' => (float) $val,
                        'status' => 'final',
                        'source_type' => 'excel_upload',
                        'notes' => $filename ?: 'Laporan Resmi CV Mekar Jaya Sentosa',
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

            if (! empty($batch)) {
                GlobalFinancialRecord::insert($batch);
                $totalRecords += count($batch);
            }
        }

        // 2. SHEET BS (Neraca CV Mekar Jaya Sentosa)
        $bsSheet = $spreadsheet->getSheetByName('BS') ?: $spreadsheet->getSheetByName('BS Det.');
        if ($bsSheet) {
            $rows = $bsSheet->toArray(null, true, false, true);
            $period = ! empty($dateColumns) ? reset($dateColumns) : '2026-09-01';

            GlobalFinancialRecord::query()
                ->where('business_unit_code', 'fmcg')
                ->where('entity_code', 'MJS')
                ->where('report_type', 'balance_sheet')
                ->delete();

            $batchBs = [];

            foreach ($rows as $row) {
                // Kolom Kiri: Asset (Col C: Code, Col D: Name, Col G: Amount)
                $codeLeft = trim($row['C'] ?? '');
                $nameLeft = trim($row['D'] ?? '');
                $amountLeft = $row['G'] ?? null;

                if (! empty($nameLeft) && is_numeric($amountLeft)) {
                    $batchBs[] = [
                        'business_unit_code' => 'fmcg',
                        'entity_code' => 'MJS',
                        'period' => $period,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => $codeLeft ?: null,
                        'account_code' => $codeLeft ?: null,
                        'account_name' => $nameLeft,
                        'amount' => (float) $amountLeft,
                        'status' => 'final',
                        'source_type' => 'excel_upload',
                        'notes' => $filename ?: 'Neraca Resmi CV Mekar Jaya Sentosa',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Kolom Kanan: Liabilities & Equities (Col J: Code, Col K: Name, Col N: Amount)
                $codeRight = trim($row['J'] ?? '');
                $nameRight = trim($row['K'] ?? '');
                $amountRight = $row['N'] ?? null;

                if (! empty($nameRight) && is_numeric($amountRight)) {
                    $category = 'liability';
                    if (str_contains(strtoupper($nameRight), 'EQUITY') || str_contains(strtoupper($nameRight), 'EARNING') || str_contains(strtoupper($nameRight), 'INCOME') || $codeRight === 'L') {
                        $category = 'equity';
                    }

                    $batchBs[] = [
                        'business_unit_code' => 'fmcg',
                        'entity_code' => 'MJS',
                        'period' => $period,
                        'report_type' => 'balance_sheet',
                        'category' => $category,
                        'sub_category' => $codeRight ?: null,
                        'account_code' => $codeRight ?: null,
                        'account_name' => $nameRight,
                        'amount' => (float) $amountRight,
                        'status' => 'final',
                        'source_type' => 'excel_upload',
                        'notes' => $filename ?: 'Neraca Resmi CV Mekar Jaya Sentosa',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($batchBs)) {
                GlobalFinancialRecord::insert($batchBs);
                $totalRecords += count($batchBs);
            }
        }

        return [
            'success' => true,
            'business_unit' => 'fmcg',
            'sheets_processed' => ['PL', 'BS'],
            'records_inserted' => $totalRecords,
            'summary_type' => 'mjs_holding',
        ];
    }

    /**
     * Ingest data dari Template Excel Universal Holding untuk unit bisnis apa pun.
     */
    public function ingestUniversalTemplate(string $filePath, string $businessUnitCode, ?string $filename = null): array
    {
        ini_set('memory_limit', '1024M');
        $spreadsheet = IOFactory::load($filePath);

        $sheet = $spreadsheet->getSheetByName('Laporan Keuangan') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, true);

        if (empty($rows)) {
            throw new \InvalidArgumentException('Sheet data Excel kosong.');
        }

        $headerRowIndex = null;
        $colMap = [];

        foreach ($rows as $rowIndex => $row) {
            $rowText = strtoupper(implode(' ', array_filter($row)));
            if (str_contains($rowText, 'KODE') && (str_contains($rowText, 'PERIODE') || str_contains($rowText, 'NOMINAL') || str_contains($rowText, 'AKUN'))) {
                $headerRowIndex = $rowIndex;
                foreach ($row as $colKey => $cellVal) {
                    $val = strtoupper(trim((string) $cellVal));
                    if (str_contains($val, 'CABANG') || str_contains($val, 'ENTITAS')) {
                        $colMap['entity'] = $colKey;
                    } elseif (str_contains($val, 'PERIODE') || str_contains($val, 'TANGGAL') || str_contains($val, 'BULAN')) {
                        $colMap['period'] = $colKey;
                    } elseif (str_contains($val, 'TIPE') || str_contains($val, 'JENIS')) {
                        $colMap['report_type'] = $colKey;
                    } elseif (str_contains($val, 'KATEGORI')) {
                        $colMap['category'] = $colKey;
                    } elseif (str_contains($val, 'KODE AKUN')) {
                        $colMap['account_code'] = $colKey;
                    } elseif (str_contains($val, 'NAMA AKUN') || str_contains($val, 'POS')) {
                        $colMap['account_name'] = $colKey;
                    } elseif (str_contains($val, 'NOMINAL') || str_contains($val, 'JUMLAH') || str_contains($val, 'AMOUNT')) {
                        $colMap['amount'] = $colKey;
                    } elseif (str_contains($val, 'CATATAN') || str_contains($val, 'NOTE')) {
                        $colMap['notes'] = $colKey;
                    }
                }
                break;
            }
        }

        if (! isset($colMap['entity'])) {
            $colMap['entity'] = 'A';
        }
        if (! isset($colMap['period'])) {
            $colMap['period'] = 'B';
        }
        if (! isset($colMap['report_type'])) {
            $colMap['report_type'] = 'C';
        }
        if (! isset($colMap['category'])) {
            $colMap['category'] = 'D';
        }
        if (! isset($colMap['account_code'])) {
            $colMap['account_code'] = 'E';
        }
        if (! isset($colMap['account_name'])) {
            $colMap['account_name'] = 'F';
        }
        if (! isset($colMap['amount'])) {
            $colMap['amount'] = 'G';
        }
        if (! isset($colMap['notes'])) {
            $colMap['notes'] = 'H';
        }

        $startRow = ($headerRowIndex ?? 4) + 1;
        $batch = [];
        $totalRecords = 0;
        $insertedEntities = [];
        $insertedPeriods = [];

        $existingEntities = GlobalEntity::pluck('code')->toArray();
        $unit = GlobalBusinessUnit::where('code', $businessUnitCode)->first();

        for ($r = $startRow; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $entityCode = strtoupper(trim($row[$colMap['entity']] ?? ''));
            $rawPeriod = trim($row[$colMap['period']] ?? '');
            $rawReportType = strtolower(trim($row[$colMap['report_type']] ?? 'pnl'));
            $rawCategory = strtolower(trim($row[$colMap['category']] ?? 'revenue'));
            $accCode = trim($row[$colMap['account_code']] ?? '');
            $accName = trim($row[$colMap['account_name']] ?? '');
            $rawAmount = $row[$colMap['amount']] ?? 0;

            if (empty($accName) && empty($entityCode) && empty($rawAmount)) {
                continue;
            }

            if (empty($entityCode)) {
                $entityCode = strtoupper($businessUnitCode);
            }

            if (! in_array($entityCode, $existingEntities) && $unit) {
                GlobalEntity::create([
                    'business_unit_id' => $unit->id,
                    'code' => $entityCode,
                    'name' => ucwords(strtolower(str_replace('_', ' ', $entityCode))),
                    'location' => 'Kantor / Cabang '.$entityCode,
                ]);
                $existingEntities[] = $entityCode;
            }

            $period = null;
            if (is_numeric($rawPeriod) && (float) $rawPeriod > 40000 && (float) $rawPeriod < 55000) {
                $period = ExcelDate::excelToDateTimeObject($rawPeriod)->format('Y-m-d');
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawPeriod)) {
                $period = $rawPeriod;
            } elseif (preg_match('/^\d{4}-\d{2}$/', $rawPeriod)) {
                $period = $rawPeriod.'-01';
            } else {
                $period = date('Y-m-01');
            }

            $reportType = str_contains($rawReportType, 'balance') || str_contains($rawReportType, 'neraca') || in_array($rawCategory, ['asset', 'liability', 'equity', 'aktiva', 'pasiva']) ? 'balance_sheet' : 'pnl';

            $category = match ($rawCategory) {
                'revenue', 'pendapatan', 'penjualan' => 'revenue',
                'cogs', 'hpp', 'harga pokok' => 'cogs',
                'opex', 'operasional', 'beban', 'biaya' => 'opex',
                'other_income', 'pendapatan lain', 'pendapatan di luar usaha' => 'other_income',
                'other_expense', 'beban lain', 'beban di luar usaha' => 'other_expense',
                'gross_profit', 'laba kotor' => 'gross_profit',
                'net_profit', 'laba bersih' => 'net_profit',
                'asset', 'aktiva', 'harta' => 'asset',
                'liability', 'kewajiban', 'hutang' => 'liability',
                'equity', 'ekuitas', 'modal' => 'equity',
                default => ($reportType === 'balance_sheet' ? 'asset' : 'opex'),
            };

            $amount = (float) str_replace([',', ' '], '', $rawAmount);

            $batch[] = [
                'business_unit_code' => $businessUnitCode,
                'entity_code' => $entityCode,
                'period' => $period,
                'report_type' => $reportType,
                'category' => $category,
                'sub_category' => null,
                'account_code' => $accCode ?: null,
                'account_name' => $accName,
                'amount' => $amount,
                'status' => 'final',
                'source_type' => 'excel_upload',
                'notes' => $filename,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insertedEntities[$entityCode] = true;
            $insertedPeriods[$period] = true;

            if (count($batch) >= 500) {
                GlobalFinancialRecord::insert($batch);
                $totalRecords += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            GlobalFinancialRecord::insert($batch);
            $totalRecords += count($batch);
        }

        return [
            'success' => true,
            'business_unit' => $businessUnitCode,
            'records_inserted' => $totalRecords,
            'entities' => array_keys($insertedEntities),
            'periods' => array_keys($insertedPeriods),
        ];
    }

    /**
     * Buat berkas Template Excel resmi holding yang siap diunduh untuk unit bisnis tertentu.
     */
    public function generateTemplate(string $businessUnitCode): Spreadsheet
    {
        $unit = GlobalBusinessUnit::with('entities')->where('code', $businessUnitCode)->first();
        $unitName = $unit ? $unit->name : strtoupper($businessUnitCode);
        $entities = $unit ? $unit->entities : collect();

        $spreadsheet = new Spreadsheet;

        // --- SHEET 1: Laporan Keuangan ---
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Keuangan');

        // Header Title
        $sheet->setCellValue('A1', 'TEMPLATE IMPORT LAPORAN KEUANGAN - '.strtoupper($unitName));
        $sheet->setCellValue('A2', 'Holding Mekar Jaya Group | Kode Unit Bisnis: '.strtoupper($businessUnitCode));
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1E293B');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');

        // Column Headers at Row 4
        $headers = [
            'A' => 'Kode Cabang / Entitas',
            'B' => 'Periode (YYYY-MM-01)',
            'C' => 'Tipe Laporan (pnl / balance_sheet)',
            'D' => 'Kategori Akun',
            'E' => 'Kode Akun (Opsional)',
            'F' => 'Nama Akun / Pos Finansial',
            'G' => 'Nominal (Rp)',
            'H' => 'Catatan (Opsional)',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue($col.'4', $text);
        }

        // Style Header Row 4
        $headerRange = 'A4:H4';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('10B981');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Pre-filled Samples
        $sampleEntity = $entities->first() ? $entities->first()->code : strtoupper($businessUnitCode).'_01';
        $samplePeriod = date('Y-m-01');

        $sampleData = [
            [$sampleEntity, $samplePeriod, 'pnl', 'revenue', '4001', 'Pendapatan Usaha Operasional', 150000000, 'Penjualan / Jasa Utama'],
            [$sampleEntity, $samplePeriod, 'pnl', 'cogs', '5001', 'Harga Pokok Penjualan / Bahan Baku', 85000000, 'HPP'],
            [$sampleEntity, $samplePeriod, 'pnl', 'opex', '6001', 'Beban Gaji & Upah Karyawan', 18000000, 'Gaji & Tunjangan'],
            [$sampleEntity, $samplePeriod, 'pnl', 'opex', '6002', 'Beban Operasional, Listrik & Air', 4500000, 'Utilitas'],
            [$sampleEntity, $samplePeriod, 'pnl', 'other_income', '8001', 'Pendapatan Bunga Bank / Lainnya', 1200000, 'Pendapatan Non Operasional'],
            [$sampleEntity, $samplePeriod, 'balance_sheet', 'asset', '1101', 'Kas & Bank Operasional', 65000000, 'Posisi Kas Akhir Periode'],
            [$sampleEntity, $samplePeriod, 'balance_sheet', 'liability', '2101', 'Hutang Dagang / Operasional', 20000000, 'Kewajiban Lancar'],
            [$sampleEntity, $samplePeriod, 'balance_sheet', 'equity', '3101', 'Modal Saham & Laba Ditahan', 45000000, 'Ekuitas Pemilik'],
        ];

        $rowIdx = 5;
        foreach ($sampleData as $row) {
            foreach ($row as $colIdx => $val) {
                $colLetter = chr(65 + $colIdx);
                $sheet->setCellValue($colLetter.$rowIdx, $val);
            }
            $rowIdx++;
        }

        $sheet->getStyle('G5:G'.($rowIdx - 1))->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // --- SHEET 2: Petunjuk & Daftar Cabang ---
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Petunjuk & Cabang');

        $sheet2->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE LAPORAN');
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sheet2->setCellValue('A3', '1. Pilihan Tipe Laporan (Kolom C):');
        $sheet2->setCellValue('B4', 'pnl : Laporan Laba Rugi');
        $sheet2->setCellValue('B5', 'balance_sheet : Laporan Neraca Keuangan');

        $sheet2->setCellValue('A7', '2. Pilihan Kategori Akun (Kolom D):');
        $sheet2->setCellValue('B8', 'revenue : Pendapatan / Penjualan');
        $sheet2->setCellValue('B9', 'cogs : Harga Pokok Penjualan (HPP)');
        $sheet2->setCellValue('B10', 'opex : Beban Operasional & Administrasi Umum');
        $sheet2->setCellValue('B11', 'other_income : Pendapatan Lain-lain / Luar Usaha');
        $sheet2->setCellValue('B12', 'other_expense : Beban Lain-lain / Luar Usaha');
        $sheet2->setCellValue('B13', 'asset : Aset / Aktiva (Kas, Piutang, Aset Tetap, dll)');
        $sheet2->setCellValue('B14', 'liability : Kewajiban / Hutang');
        $sheet2->setCellValue('B15', 'equity : Modal / Ekuitas / Laba Ditahan');

        $sheet2->setCellValue('A17', '3. Daftar Entitas / Cabang Terdaftar untuk Unit Usaha Ini:');
        $sheet2->getStyle('A17')->getFont()->setBold(true);

        $sheet2->setCellValue('A18', 'Kode Cabang (Wajib sesuai)');
        $sheet2->setCellValue('B18', 'Nama Cabang / Entitas');
        $sheet2->setCellValue('C18', 'Lokasi Cabang');
        $sheet2->getStyle('A18:C18')->getFont()->setBold(true);

        $eRow = 19;
        foreach ($entities as $entity) {
            $sheet2->setCellValue('A'.$eRow, $entity->code);
            $sheet2->setCellValue('B'.$eRow, $entity->name);
            $sheet2->setCellValue('C'.$eRow, $entity->location ?? '-');
            $eRow++;
        }

        foreach (range('A', 'C') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
