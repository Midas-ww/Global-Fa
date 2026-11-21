<?php

namespace App\Http\Controllers;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIngestionLog;
use App\Services\GlobalFinance\ConsolidationReportService;
use App\Services\GlobalFinance\ExcelIngestionService;
use App\Services\GlobalFinance\IqosSyncService;
use App\Services\GlobalFinance\SpbuSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GlobalFinanceController extends Controller
{
    protected ConsolidationReportService $reportService;

    public function __construct(ConsolidationReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Dashboard Konsolidasi Finansial Holding.
     */
    public function dashboard(Request $request): View
    {
        $availablePeriods = $this->reportService->getAvailablePeriods();
        $selectedPeriod = $request->query('period');

        if (! $selectedPeriod || ! in_array($selectedPeriod, $availablePeriods)) {
            $selectedPeriod = $availablePeriods[0] ?? '2026-07-01';
        }

        $summary = $this->reportService->getDashboardSummary($selectedPeriod);
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();

        return view('finance.dashboard', [
            'summary' => $summary,
            'selectedPeriod' => $selectedPeriod,
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
        ]);
    }

    /**
     * Laporan Laba Rugi (P&L) Terkonsolidasi Holding.
     */
    public function pnl(Request $request): View
    {
        $availablePeriods = $this->reportService->getAvailablePeriods();
        $selectedPeriod = $request->query('period');

        if (! $selectedPeriod || ! in_array($selectedPeriod, $availablePeriods)) {
            $selectedPeriod = $availablePeriods[0] ?? '2026-07-01';
        }

        $selectedBu = $request->query('business_unit');
        $selectedEntity = $request->query('entity');

        $pnlData = $this->reportService->getConsolidatedPnl($selectedPeriod, $selectedBu, $selectedEntity);
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();

        return view('finance.pnl', [
            'pnl' => $pnlData,
            'selectedPeriod' => $selectedPeriod,
            'selectedBu' => $selectedBu,
            'selectedEntity' => $selectedEntity,
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
        ]);
    }

    /**
     * Laporan Neraca (Balance Sheet) Terkonsolidasi Holding.
     */
    public function balanceSheet(Request $request): View
    {
        $availablePeriods = $this->reportService->getAvailablePeriods();
        $selectedPeriod = $request->query('period');

        if (! $selectedPeriod || ! in_array($selectedPeriod, $availablePeriods)) {
            $selectedPeriod = $availablePeriods[0] ?? '2026-07-01';
        }

        $selectedBu = $request->query('business_unit');
        $selectedEntity = $request->query('entity');

        $balanceSheetData = $this->reportService->getConsolidatedBalanceSheet($selectedPeriod, $selectedBu, $selectedEntity);
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();

        return view('finance.balance-sheet', [
            'bs' => $balanceSheetData,
            'selectedPeriod' => $selectedPeriod,
            'selectedBu' => $selectedBu,
            'selectedEntity' => $selectedEntity,
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
        ]);
    }

    /**
     * Halaman Hub Sinkronisasi Database & Import Excel.
     */
    public function syncView(): View
    {
        $availablePeriods = $this->reportService->getAvailablePeriods();
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();
        $logs = GlobalIngestionLog::query()->orderBy('created_at', 'desc')->limit(50)->get();

        $stats = [
            'total_records' => GlobalFinancialRecord::count(),
            'total_entities' => GlobalEntity::count(),
            'total_business_units' => GlobalBusinessUnit::count(),
            'last_sync' => GlobalFinancialRecord::max('updated_at'),
        ];

        return view('finance.sync', [
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * Trigger Sinkronisasi Database Otomatis (SPBU & IQOS).
     */
    public function triggerDbSync(Request $request): RedirectResponse
    {
        $target = $request->input('target', 'all');
        $period = $request->input('period', '2026-07');
        $results = [];

        try {
            if ($target === 'all' || $target === 'iqos') {
                $iqos = new IqosSyncService;
                $resIqos = $iqos->sync();
                $results[] = 'Retail Store: '.($resIqos['count'] ?? 0).' periode disinkronkan.';

                GlobalIngestionLog::create([
                    'business_unit_code' => 'iqos',
                    'source_type' => 'database_sync',
                    'filename' => 'sir_iqos.iqos_monthly_reports',
                    'records_count' => $resIqos['count'] ?? 0,
                    'status' => 'success',
                    'details' => json_encode($resIqos),
                ]);
            }

            if ($target === 'all' || $target === 'spbu') {
                $spbu = new SpbuSyncService;
                $resSpbu = $spbu->sync($period);
                $isReconciled = $resSpbu['reconciled'] ?? true;
                $statusLog = $isReconciled ? 'success' : 'warning';
                $reconcileNote = $isReconciled ? ' (Rekonsiliasi 100% Cocok)' : ' (Perhatian: Selisih Rekonsiliasi Rp '.number_format((float) ($resSpbu['variance'] ?? 0), 2, ',', '.').')';
                $results[] = "SPBU ({$period}): {$resSpbu['records_count']} akun finansial diserap (Omzet: Rp ".number_format($resSpbu['revenue_total'], 0, ',', '.')."){$reconcileNote}.";

                GlobalIngestionLog::create([
                    'business_unit_code' => 'spbu',
                    'source_type' => 'database_sync',
                    'filename' => "api_air.shift_sales ({$period})",
                    'records_count' => $resSpbu['records_count'] ?? 0,
                    'status' => $statusLog,
                    'details' => json_encode($resSpbu),
                ]);
            }

            return back()->with('success', 'Sinkronisasi Database Berhasil: '.implode(' | ', $results));
        } catch (\Throwable $e) {
            GlobalIngestionLog::create([
                'business_unit_code' => $target,
                'source_type' => 'database_sync',
                'filename' => 'database_error',
                'records_count' => 0,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);

            return back()->with('error', 'Gagal sinkronisasi: '.$e->getMessage());
        }
    }

    /**
     * Pratinjau dan analisis file Excel sebelum konfirmasi import (mirip SIR-IQOS).
     */
    public function previewExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'type' => 'required|string|exists:global_business_units,code',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        try {
            $service = new ExcelIngestionService;
            $preview = $service->previewFile($file->getRealPath(), $type, $file->getClientOriginalName());

            return response()->json($preview);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload & Ingest File Excel (FMCG 9 Depo atau Ekspedisi Xtrans).
     */
    public function uploadExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200', // max 50MB
            'type' => 'required|string|exists:global_business_units,code',
            'overwrite' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $filename = $file->getClientOriginalName();
        $overwrite = $request->boolean('overwrite');
        $fileHash = hash_file('sha256', $file->getRealPath());

        // 1. Deteksi apakah file Excel ini sudah pernah diimpor sebelumnya
        if (! $overwrite) {
            $existingLog = GlobalIngestionLog::query()
                ->where('business_unit_code', $type)
                ->where('source_type', 'excel_upload')
                ->where('status', 'success')
                ->where(function ($q) use ($filename, $fileHash) {
                    $q->where('filename', $filename)
                        ->orWhere('details', 'like', '%"file_hash":"'.$fileHash.'"%');
                })
                ->latest()
                ->first();

            if ($existingLog) {
                $timeStr = $existingLog->created_at->translatedFormat('d M Y H:i');

                return back()->with('error', "Peringatan Duplikasi: File '{$filename}' sudah pernah diimpor pada {$timeStr} (tercatat {$existingLog->records_count} data). Jika Anda bermaksud memperbarui atau mengunggah ulang data file ini, silakan centang opsi 'Timpa data yang sudah ada (Overwrite)'.");
            }
        }

        $path = $file->storeAs('imports', time().'_'.$filename);
        $fullPath = Storage::path($path);

        $service = new ExcelIngestionService;

        try {
            // 2. Deteksi & Validasi apakah file Excel sesuai dengan tipe usaha yang dipilih
            $service->validateBusinessUnit($fullPath, $type);

            // 3. Proses Ingest Data
            $detected = $service->detectFileType($fullPath);
            if ($detected['type'] === 'universal_template') {
                $res = $service->ingestUniversalTemplate($fullPath, $type, $filename);
                $unitName = GlobalBusinessUnit::where('code', $type)->value('name') ?? strtoupper($type);
                $msg = "{$unitName}: Berhasil mengimpor {$res['records_inserted']} data transaksi via Template Universal Holding.";
            } elseif ($type === 'fmcg') {
                $res = $service->ingestFmcgFile($fullPath, $filename);
                $msg = "Unilever: Berhasil mengimpor {$res['records_inserted']} data transaksi dari ".count($res['sheets_processed'] ?? []).' lembar kerja (sheets).';
            } elseif ($type === 'aqua') {
                $res = $service->ingestAquaFile($fullPath, $filename);
                $msg = "AQUA: Berhasil mengimpor {$res['records_inserted']} data transaksi P&L dan Neraca Danone AQUA.";
            } elseif ($type === 'ccod') {
                $res = $service->ingestFmcgDepartmentFile($fullPath, $filename);
                $msg = "CCOD: Berhasil mengimpor {$res['records_inserted']} data transaksi dari ".($res['departments_count'] ?? 12).' cabang.';
            } elseif ($type === 'xtrans') {
                $res = $service->ingestXtransFile($fullPath, $filename);
                $msg = "Xtrans: Berhasil mengimpor {$res['records_inserted']} data P&L dan Neraca armada Xtrans.";
            } else {
                $res = $service->ingestUniversalTemplate($fullPath, $type, $filename);
                $unitName = GlobalBusinessUnit::where('code', $type)->value('name') ?? strtoupper($type);
                $msg = "{$unitName}: Berhasil mengimpor {$res['records_inserted']} data transaksi finansial.";
            }

            GlobalIngestionLog::create([
                'business_unit_code' => $type,
                'source_type' => 'excel_upload',
                'filename' => $filename,
                'records_count' => $res['records_inserted'] ?? 0,
                'status' => 'success',
                'details' => json_encode(array_merge($res, [
                    'file_hash' => $fileHash,
                    'overwrite' => $overwrite,
                ])),
            ]);

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            GlobalIngestionLog::create([
                'business_unit_code' => $type,
                'source_type' => 'excel_upload',
                'filename' => $filename,
                'records_count' => 0,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus beberapa riwayat import/sinkronisasi sekaligus beserta data finansial terkait.
     */
    public function bulkDeleteIngestionLogs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:global_ingestion_logs,id',
        ]);

        $ids = $validated['ids'];
        $logs = GlobalIngestionLog::whereIn('id', $ids)->get();

        if ($logs->isEmpty()) {
            return back()->with('error', 'Tidak ada riwayat yang dipilih untuk dihapus.');
        }

        $totalDeletedRecords = 0;
        $deletedLogsCount = $logs->count();

        DB::transaction(function () use ($logs, &$totalDeletedRecords) {
            foreach ($logs as $log) {
                if ($log->source_type === 'excel_upload') {
                    $deleted = GlobalFinancialRecord::query()
                        ->where('business_unit_code', $log->business_unit_code)
                        ->where('source_type', 'excel_upload')
                        ->where(function ($q) use ($log) {
                            $q->where('notes', $log->filename)
                                ->orWhereNull('notes');
                        })
                        ->delete();
                    $totalDeletedRecords += $deleted;
                }
                $log->delete();
            }
        });

        return back()->with('success', "Berhasil menghapus {$deletedLogsCount} riwayat import/sinkronisasi dan membersihkan ".number_format($totalDeletedRecords, 0, ',', '.').' baris data transaksi finansial terkait dari sistem.');
    }

    /**
     * Hapus riwayat import / sinkronisasi beserta data finansial terkait.
     */
    public function deleteIngestionLog(int $id): RedirectResponse
    {
        $log = GlobalIngestionLog::findOrFail($id);
        $businessUnit = strtoupper($log->business_unit_code);
        $filename = $log->filename;
        $deletedRecordsCount = 0;

        DB::transaction(function () use ($log, &$deletedRecordsCount) {
            if ($log->source_type === 'excel_upload') {
                $deletedRecordsCount = GlobalFinancialRecord::query()
                    ->where('business_unit_code', $log->business_unit_code)
                    ->where('source_type', 'excel_upload')
                    ->where(function ($q) use ($log) {
                        $q->where('notes', $log->filename)
                            ->orWhereNull('notes');
                    })
                    ->delete();
            }

            $log->delete();
        });

        if ($log->source_type === 'excel_upload') {
            return back()->with('success', "Riwayat import '{$filename}' ({$businessUnit}) berhasil dihapus beserta ".number_format($deletedRecordsCount, 0, ',', '.').' baris data finansial terkait.');
        }

        return back()->with('success', "Riwayat sinkronisasi database untuk unit {$businessUnit} ({$filename}) berhasil dihapus.");
    }
}
