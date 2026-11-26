<?php

namespace App\Http\Controllers;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIngestionLog;
use App\Models\GlobalPeriodNote;
use App\Services\GlobalFinance\ConsolidationReportService;
use App\Services\GlobalFinance\ExcelIngestionService;
use App\Services\GlobalFinance\IntercompanyEliminationService;
use App\Services\GlobalFinance\IqosSyncService;
use App\Services\GlobalFinance\OnBoldSyncService;
use App\Services\GlobalFinance\SpbuSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GlobalFinanceController extends Controller
{
    public function __construct(
        protected ConsolidationReportService $reportService,
        protected IntercompanyEliminationService $eliminationService
    ) {}

    /**
     * Dashboard Konsolidasi Finansial Holding.
     */
    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $allowedCodes = $user?->accessibleBusinessUnitCodes();

        // Jika user memiliki akses terbatas ke unit tertentu, arahkan langsung ke halaman P&L unit utamanya
        if ($allowedCodes !== null) {
            $defaultBu = $allowedCodes[0] ?? null;

            return redirect()->route('finance.pnl', array_filter(['business_unit' => $defaultBu]));
        }

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
        $user = $request->user();
        $allowedCodes = $user?->accessibleBusinessUnitCodes();
        $isUnrestricted = $user?->isUnrestrictedFinance() ?? true;

        $availablePeriods = $this->reportService->getAvailablePeriods();
        $selectedPeriod = $request->query('period');

        if (! $selectedPeriod || ! in_array($selectedPeriod, $availablePeriods)) {
            $selectedPeriod = $availablePeriods[0] ?? '2026-07-01';
        }

        $selectedBu = $request->query('business_unit');
        $selectedEntity = $request->query('entity');

        // Scoping unit usaha sesuai hak akses user
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();
        if ($allowedCodes !== null) {
            $businessUnits = array_values(array_filter($businessUnits, fn ($b) => in_array($b['code'], $allowedCodes)));
            if (empty($selectedBu) || ! in_array($selectedBu, $allowedCodes)) {
                $selectedBu = $allowedCodes[0] ?? null;
            }
        }

        $canEdit = $selectedBu ? ($user?->canEditBusinessUnit($selectedBu) ?? false) : false;

        $pnlData = $this->reportService->getConsolidatedPnl($selectedPeriod, $selectedBu, $selectedEntity);

        $buCode = $selectedBu ?: 'all';
        $periodDate = Carbon::parse($selectedPeriod)->format('Y-m-d');
        $periodNote = GlobalPeriodNote::whereDate('period', $periodDate)
            ->where('business_unit_code', $buCode)
            ->whereIn('report_type', ['pnl', 'all'])
            ->orderByRaw("CASE WHEN report_type = 'pnl' THEN 1 ELSE 2 END")
            ->first();

        return view('finance.pnl', [
            'pnl' => $pnlData,
            'selectedPeriod' => $selectedPeriod,
            'selectedBu' => $selectedBu,
            'selectedEntity' => $selectedEntity,
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
            'canEdit' => $canEdit,
            'isUnrestricted' => $isUnrestricted,
            'periodNote' => $periodNote,
            'canEditNote' => $user?->canEditPeriodNotes() ?? false,
        ]);
    }

    /**
     * Laporan Neraca (Balance Sheet) Terkonsolidasi Holding.
     */
    public function balanceSheet(Request $request): View
    {
        $user = $request->user();
        $allowedCodes = $user?->accessibleBusinessUnitCodes();
        $isUnrestricted = $user?->isUnrestrictedFinance() ?? true;

        $availablePeriods = $this->reportService->getAvailablePeriods();
        $selectedPeriod = $request->query('period');

        if (! $selectedPeriod || ! in_array($selectedPeriod, $availablePeriods)) {
            $selectedPeriod = $availablePeriods[0] ?? '2026-07-01';
        }

        $selectedBu = $request->query('business_unit');
        $selectedEntity = $request->query('entity');

        // Jika user memilih ccod pada halaman neraca, arahkan ke abc (CCOD x ABC)
        if ($selectedBu === 'ccod') {
            $selectedBu = 'abc';
        }

        // Scoping unit usaha sesuai hak akses user
        $rawBusinessUnits = $this->reportService->getBusinessUnitsWithEntities();
        if ($allowedCodes !== null) {
            $rawBusinessUnits = array_values(array_filter($rawBusinessUnits, fn ($b) => in_array($b['code'], $allowedCodes)));
            if (empty($selectedBu) || ! in_array($selectedBu, $allowedCodes)) {
                $selectedBu = $allowedCodes[0] ?? null;
            }
        }

        // Khusus Neraca: CCOD dihapus (digabung) dan ABC diganti nama menjadi CCOD x ABC
        $businessUnits = [];
        foreach ($rawBusinessUnits as $bu) {
            if ($bu['code'] === 'ccod') {
                continue; // Hapus CCOD dari tab Neraca
            }
            if ($bu['code'] === 'abc') {
                $bu['name'] = 'CCOD x ABC';
                $bu['description'] = 'PT Mekar Jaya Sentosa Multikarya - Neraca Gabungan CCOD x ABC';
                $bu['entities'] = []; // Neraca digabung satu kesatuan tingkat PT, tidak dipecah per cabang
            }
            $businessUnits[] = $bu;
        }

        $canEdit = $selectedBu ? ($user?->canEditBusinessUnit($selectedBu) ?? false) : false;

        $balanceSheetData = $this->reportService->getConsolidatedBalanceSheet($selectedPeriod, $selectedBu, $selectedEntity);

        $hasEntitySpecificBs = false;
        if ($selectedBu && ! empty($businessUnits)) {
            $curBu = collect($businessUnits)->firstWhere('code', $selectedBu);
            $codes = collect($curBu['entities'] ?? [])->pluck('code');
            $hasEntitySpecificBs = GlobalFinancialRecord::where('business_unit_code', $selectedBu)
                ->whereIn('entity_code', $codes)
                ->where('report_type', 'balance_sheet')
                ->exists();
        }

        $buCode = $selectedBu ?: 'all';
        $periodDate = Carbon::parse($selectedPeriod)->format('Y-m-d');
        $periodNote = GlobalPeriodNote::whereDate('period', $periodDate)
            ->where('business_unit_code', $buCode)
            ->whereIn('report_type', ['balance_sheet', 'all'])
            ->orderByRaw("CASE WHEN report_type = 'balance_sheet' THEN 1 ELSE 2 END")
            ->first();

        return view('finance.balance-sheet', [
            'bs' => $balanceSheetData,
            'selectedPeriod' => $selectedPeriod,
            'selectedBu' => $selectedBu,
            'selectedEntity' => $selectedEntity,
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
            'hasEntitySpecificBs' => $hasEntitySpecificBs,
            'canEdit' => $canEdit,
            'isUnrestricted' => $isUnrestricted,
            'periodNote' => $periodNote,
            'canEditNote' => $user?->canEditPeriodNotes() ?? false,
        ]);
    }

    /**
     * Halaman Hub Sinkronisasi Database & Import Excel.
     */
    public function syncView(Request $request): View
    {
        $user = $request->user();

        if (! ($user?->canAccessSyncMenu() ?? true)) {
            abort(403, 'Akses Ditolak: Menu Riwayat & Import Data tidak diperuntukkan bagi Direksi / Management.');
        }

        $allowedCodes = $user?->accessibleBusinessUnitCodes();
        $isUnrestricted = $user?->isUnrestrictedFinance() ?? true;

        $availablePeriods = $this->reportService->getAvailablePeriods();
        $businessUnits = $this->reportService->getBusinessUnitsWithEntities();

        if ($allowedCodes !== null) {
            $businessUnits = array_values(array_filter($businessUnits, fn ($b) => in_array($b['code'], $allowedCodes)));
        }

        $logsQuery = GlobalIngestionLog::query()->orderBy('created_at', 'desc');
        if ($allowedCodes !== null) {
            $logsQuery->whereIn('business_unit_code', $allowedCodes);
        }
        $logs = $logsQuery->limit(50)->get();

        $stats = [
            'total_records' => $allowedCodes !== null ? GlobalFinancialRecord::whereIn('business_unit_code', $allowedCodes)->count() : GlobalFinancialRecord::count(),
            'total_entities' => GlobalEntity::count(),
            'total_business_units' => count($businessUnits),
            'last_sync' => $allowedCodes !== null ? GlobalFinancialRecord::whereIn('business_unit_code', $allowedCodes)->max('updated_at') : GlobalFinancialRecord::max('updated_at'),
        ];

        return view('finance.sync', [
            'availablePeriods' => $availablePeriods,
            'businessUnits' => $businessUnits,
            'logs' => $logs,
            'stats' => $stats,
            'isUnrestricted' => $isUnrestricted,
        ]);
    }

    /**
     * Trigger Sinkronisasi Database Otomatis (SPBU & IQOS).
     */
    public function triggerDbSync(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! ($user?->canAccessSyncMenu() ?? true)) {
            abort(403, 'Akses Ditolak: Fitur sinkronisasi tidak diperuntukkan bagi Direksi / Management.');
        }

        $target = $request->input('target', 'all');
        $period = $request->input('period', '2026-07');

        if ($target === 'all') {
            if (! $user?->isUnrestrictedFinance()) {
                return back()->with('warning', 'Hanya administrator atau direksi yang dapat menjalankan sinkronisasi seluruh database unit holding.');
            }
        } elseif (! ($user?->canAccessBusinessUnit($target) ?? false)) {
            return back()->with('warning', 'Anda tidak memiliki hak akses untuk sinkronisasi database unit '.$target);
        }

        $results = [];
        $errors = [];

        if ($target === 'all' || $target === 'iqos') {
            try {
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
            } catch (\Throwable $e) {
                $errors[] = 'IQOS: '.$e->getMessage();
                GlobalIngestionLog::create([
                    'business_unit_code' => 'iqos',
                    'source_type' => 'database_sync',
                    'filename' => 'sir_iqos_error',
                    'records_count' => 0,
                    'status' => 'failed',
                    'details' => $e->getMessage(),
                ]);
            }
        }

        if ($target === 'all' || $target === 'spbu') {
            try {
                $spbu = new SpbuSyncService;
                if ($period === 'all') {
                    $resSpbu = $spbu->syncAll();
                    $results[] = "SPBU: Seluruh periode ({$resSpbu['periods_synced']} bulan) disinkronkan ({$resSpbu['total_records']} record tersimpan).";
                    GlobalIngestionLog::create([
                        'business_unit_code' => 'spbu',
                        'source_type' => 'database_sync',
                        'filename' => 'api_air.shift_sales (all)',
                        'records_count' => $resSpbu['total_records'] ?? 0,
                        'status' => 'success',
                        'details' => json_encode($resSpbu),
                    ]);
                } else {
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
            } catch (\Throwable $e) {
                $errors[] = 'SPBU: '.$e->getMessage();
                GlobalIngestionLog::create([
                    'business_unit_code' => 'spbu',
                    'source_type' => 'database_sync',
                    'filename' => 'spbu_error',
                    'records_count' => 0,
                    'status' => 'failed',
                    'details' => $e->getMessage(),
                ]);
            }
        }

        if ($target === 'all' || $target === 'onbold') {
            try {
                $onBold = new OnBoldSyncService;
                $resOnBold = $onBold->sync();
                $results[] = 'On Bold: '.($resOnBold['count'] ?? 0).' periode disinkronkan ('.($resOnBold['records_count'] ?? 0).' akun tersimpan).';

                GlobalIngestionLog::create([
                    'business_unit_code' => 'onbold',
                    'source_type' => 'database_sync',
                    'filename' => 'dash_onbold_db.cost_pnl_records & balance_sheet_records',
                    'records_count' => $resOnBold['records_count'] ?? 0,
                    'status' => 'success',
                    'details' => json_encode($resOnBold),
                ]);
            } catch (\Throwable $e) {
                $errors[] = 'On Bold: '.$e->getMessage();
                GlobalIngestionLog::create([
                    'business_unit_code' => 'onbold',
                    'source_type' => 'database_sync',
                    'filename' => 'onbold_error',
                    'records_count' => 0,
                    'status' => 'failed',
                    'details' => $e->getMessage(),
                ]);
            }
        }

        // Otomatis sinkronkan eliminasi intercompany setelah sync database
        try {
            $this->eliminationService->syncAllPeriods();
        } catch (\Throwable) {
        }

        if (! empty($errors) && empty($results)) {
            return back()->with('error', 'Gagal sinkronisasi: '.implode(' | ', $errors));
        }

        if (! empty($errors)) {
            return back()->with('warning', 'Sinkronisasi sebagian berhasil: '.implode(' | ', $results).'. Namun ada kendala pada: '.implode(' | ', $errors));
        }

        return back()->with('success', 'Sinkronisasi Database Berhasil: '.implode(' | ', $results));
    }

    /**
     * Pratinjau dan analisis file Excel sebelum konfirmasi import (mirip SIR-IQOS).
     */
    public function previewExcel(Request $request): JsonResponse
    {
        if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Fitur import data tidak diperuntukkan bagi Direksi / Management.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'type' => 'required|string',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        if ($type === 'auto') {
            if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Anda tidak memiliki hak akses untuk fitur import data.',
                ], 403);
            }
        } elseif ($type === 'abc' || $type === 'ccod') {
            $hasAccess = ($request->user()?->canAccessBusinessUnit('abc') ?? false) || ($request->user()?->canAccessBusinessUnit('ccod') ?? false);
            if (! $hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk unit bisnis ini.',
                ], 403);
            }
        } elseif (! ($request->user()?->canAccessBusinessUnit($type) ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk unit bisnis ini.',
            ], 403);
        }

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
        if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
            abort(403, 'Akses Ditolak: Fitur import data tidak diperuntukkan bagi Direksi / Management.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200', // max 50MB
            'type' => 'required|string',
            'overwrite' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        if ($type === 'auto') {
            if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
                return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk fitur import data.');
            }
        } elseif ($type === 'abc' || $type === 'ccod') {
            $hasAccess = ($request->user()?->canEditBusinessUnit('abc') ?? false) || ($request->user()?->canEditBusinessUnit('ccod') ?? false);
            if (! $hasAccess) {
                return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin edit untuk unit CCOD x ABC.');
            }
        } elseif (! ($request->user()?->canEditBusinessUnit($type) ?? false)) {
            return back()->with('error', 'Akses ditolak: Unit usaha ini bersumber dari database otomatis atau Anda tidak memiliki izin edit.');
        }

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
            if (($detected['type'] ?? '') === 'unified_multi_unit' || $type === 'auto') {
                $res = $service->ingestUnifiedFile($fullPath, $filename, $overwrite);
                $msg = $res['summary'] ?? "Satu Pintu: Berhasil mengimpor {$res['records_inserted']} data transaksi dari berkas gabungan multi-unit.";
            } elseif ($detected['type'] === 'universal_template') {
                $res = $service->ingestUniversalTemplate($fullPath, $type, $filename);
                $unitName = GlobalBusinessUnit::where('code', $type)->value('name') ?? strtoupper($type);
                $msg = "{$unitName}: Berhasil mengimpor {$res['records_inserted']} data transaksi via Template Universal Holding.";
            } elseif ($type === 'fmcg') {
                if (($detected['type'] ?? '') === 'fmcg_bs') {
                    $res = $service->ingestFmcgBalanceSheetFile($fullPath, $filename);
                    $msg = "Unilever: Berhasil mengimpor {$res['records_inserted']} data Neraca Konsolidasi CV Mekar Jaya Perkasa ({$res['periods_count']} periode).";
                } else {
                    $res = $service->ingestFmcgFile($fullPath, $filename);
                    $msg = "Unilever: Berhasil mengimpor {$res['records_inserted']} data transaksi dari ".count($res['sheets_processed'] ?? []).' lembar kerja (sheets).';
                }
            } elseif ($type === 'aqua') {
                $res = $service->ingestAquaFile($fullPath, $filename);
                $msg = "AQUA: Berhasil mengimpor {$res['records_inserted']} data transaksi P&L dan Neraca Danone AQUA.";
            } elseif ($type === 'ccod' || $type === 'abc') {
                if (($detected['type'] ?? '') === 'mjsm_ccod' || str_contains(strtoupper($filename), 'MJSM')) {
                    $res = $service->ingestMjsmCcodFile($fullPath, $filename);
                    $msg = "MJSM (ABC & CCOD): Berhasil mengimpor {$res['records_inserted']} data P&L terpisah dan Neraca Gabungan PT MJSM ({$res['periods_count']} periode).";
                } elseif (($detected['type'] ?? '') === 'ccod' || $type === 'ccod') {
                    $res = $service->ingestFmcgDepartmentFile($fullPath, $filename);
                    $msg = "CCOD: Berhasil mengimpor {$res['records_inserted']} data transaksi dari ".($res['departments_count'] ?? 12).' cabang.';
                } else {
                    $res = $service->ingestUniversalTemplate($fullPath, $type, $filename);
                    $msg = "ABC: Berhasil mengimpor {$res['records_inserted']} data transaksi.";
                }
            } elseif ($type === 'xtrans') {
                $res = $service->ingestXtransFile($fullPath, $filename);
                $msg = "Xtrans: Berhasil mengimpor {$res['records_inserted']} data P&L dan Neraca armada Xtrans.";
            } elseif ($type === 'kandang') {
                $res = $service->ingestKandangFile($fullPath, $filename);
                $msg = "Kandang: Berhasil mengimpor {$res['records_inserted']} data P&L dan Neraca PT Mekar Jaya Kurnia Bersama.";
            } else {
                $res = $service->ingestUniversalTemplate($fullPath, $type, $filename);
                $unitName = GlobalBusinessUnit::where('code', $type)->value('name') ?? strtoupper($type);
                $msg = "{$unitName}: Berhasil mengimpor {$res['records_inserted']} data transaksi finansial.";
            }

            $loggedUnits = ! empty($res['units_processed']) && is_array($res['units_processed'])
                ? array_keys($res['units_processed'])
                : [$type];

            foreach ($loggedUnits as $lu) {
                GlobalIngestionLog::create([
                    'business_unit_code' => $lu,
                    'source_type' => 'excel_upload',
                    'filename' => $filename,
                    'records_count' => $res['units_processed'][$lu]['records_inserted'] ?? ($res['records_inserted'] ?? 0),
                    'status' => 'success',
                    'details' => json_encode(array_merge($res, [
                        'file_hash' => $fileHash,
                        'overwrite' => $overwrite,
                    ])),
                ]);
            }

            // Otomatis bersihkan data manual draft sebelumnya untuk unit dan periode yang tercover di file ini
            $insertedPeriods = $res['periods'] ?? [];
            if (empty($insertedPeriods) && ! empty($res['period'])) {
                $insertedPeriods = [$res['period']];
            }

            if (! empty($insertedPeriods)) {
                foreach ($loggedUnits as $lu) {
                    GlobalFinancialRecord::query()
                        ->where('business_unit_code', $lu)
                        ->whereIn('period', $insertedPeriods)
                        ->where('source_type', 'manual')
                        ->delete();
                }
            }

            // Otomatis sinkronkan eliminasi intercompany setelah import baru
            $this->eliminationService->syncAllPeriods();

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
        if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
            abort(403, 'Akses Ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:global_ingestion_logs,id',
        ]);

        $ids = $validated['ids'];
        $logs = GlobalIngestionLog::whereIn('id', $ids)->get();

        if ($logs->isEmpty()) {
            return back()->with('error', 'Tidak ada riwayat yang dipilih untuk dihapus.');
        }

        foreach ($logs as $log) {
            if (! ($request->user()?->canAccessBusinessUnit($log->business_unit_code) ?? false)) {
                abort(403, 'Akses Ditolak: Anda tidak memiliki wewenang untuk menghapus riwayat unit '.$log->business_unit_code);
            }
        }

        $totalDeletedRecords = 0;
        $deletedLogsCount = $logs->count();

        $deletedIds = $logs->pluck('id')->all();
        DB::transaction(function () use ($logs, $deletedIds, &$totalDeletedRecords) {
            foreach ($logs as $log) {
                $hasOtherActiveLog = GlobalIngestionLog::whereNotIn('id', $deletedIds)
                    ->where('business_unit_code', $log->business_unit_code)
                    ->where('filename', $log->filename)
                    ->where('status', 'success')
                    ->exists();

                if (! $hasOtherActiveLog && $log->source_type === 'excel_upload') {
                    $query = GlobalFinancialRecord::query()
                        ->where('source_type', 'excel_upload');

                    if ($log->business_unit_code === 'abc') {
                        $query->whereIn('business_unit_code', ['abc', 'ccod'])
                            ->where(function ($q) use ($log) {
                                $q->where('notes', $log->filename)
                                    ->orWhere('notes', 'like', '%PL Depo ABC%')
                                    ->orWhere('notes', 'like', '%PL Depo CCOD%')
                                    ->orWhere('notes', 'like', '%Neraca Gabungan PT MJSM%');
                            });
                    } else {
                        $query->where('business_unit_code', $log->business_unit_code)
                            ->where(function ($q) use ($log) {
                                $q->where('notes', $log->filename)
                                    ->orWhereNull('notes');
                            });
                    }

                    $totalDeletedRecords += $query->delete();
                }
                $log->delete();
            }
        });

        return back()->with('success', "Berhasil menghapus {$deletedLogsCount} riwayat import/sinkronisasi dan membersihkan ".number_format($totalDeletedRecords, 0, ',', '.').' baris data transaksi finansial terkait dari sistem.');
    }

    /**
     * Hapus riwayat import / sinkronisasi beserta data finansial terkait.
     */
    public function deleteIngestionLog(Request $request, int $id): RedirectResponse
    {
        if (! ($request->user()?->canAccessSyncMenu() ?? true)) {
            abort(403, 'Akses Ditolak.');
        }

        $log = GlobalIngestionLog::findOrFail($id);

        if (! ($request->user()?->canAccessBusinessUnit($log->business_unit_code) ?? false)) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki wewenang untuk menghapus log unit ini.');
        }

        $businessUnit = strtoupper($log->business_unit_code);
        $filename = $log->filename;
        $deletedRecordsCount = 0;

        DB::transaction(function () use ($log, &$deletedRecordsCount) {
            $hasOtherActiveLog = GlobalIngestionLog::where('id', '!=', $log->id)
                ->where('business_unit_code', $log->business_unit_code)
                ->where('filename', $log->filename)
                ->where('status', 'success')
                ->exists();

            if (! $hasOtherActiveLog && $log->source_type === 'excel_upload') {
                $query = GlobalFinancialRecord::query()
                    ->where('source_type', 'excel_upload');

                if ($log->business_unit_code === 'abc') {
                    $query->whereIn('business_unit_code', ['abc', 'ccod'])
                        ->where(function ($q) use ($log) {
                            $q->where('notes', $log->filename)
                                ->orWhere('notes', 'like', '%PL Depo ABC%')
                                ->orWhere('notes', 'like', '%PL Depo CCOD%')
                                ->orWhere('notes', 'like', '%Neraca Gabungan PT MJSM%');
                        });
                } else {
                    $query->where('business_unit_code', $log->business_unit_code)
                        ->where(function ($q) use ($log) {
                            $q->where('notes', $log->filename)
                                ->orWhereNull('notes');
                        });
                }

                $deletedRecordsCount = $query->delete();
            }

            $log->delete();
        });

        if ($log->source_type === 'excel_upload') {
            return back()->with('success', "Riwayat import '{$filename}' ({$businessUnit}) berhasil dihapus beserta ".number_format($deletedRecordsCount, 0, ',', '.').' baris data finansial terkait.');
        }

        return back()->with('success', "Riwayat sinkronisasi database untuk unit {$businessUnit} ({$filename}) berhasil dihapus.");
    }

    /**
     * Jalankan sinkronisasi ulang seluruh jurnal eliminasi intercompany otomatis.
     */
    public function syncEliminations(): RedirectResponse
    {
        $res = $this->eliminationService->syncAllPeriods();

        return back()->with('success', "Berhasil mensinkronkan {$res['total_eliminations_active']} transaksi eliminasi otomatis di {$res['periods_processed']} periode finansial.");
    }

    /**
     * Aktifkan atau nonaktifkan aturan eliminasi intercompany tertentu.
     */
    public function toggleEliminationRule(int $id): RedirectResponse
    {
        $isActive = $this->eliminationService->toggleRule($id);
        $statusText = $isActive ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Aturan eliminasi intercompany berhasil {$statusText} dan seluruh periode telah disinkronkan ulang.");
    }

    /**
     * Simpan / Tambah / Update data akun finansial manual (Hanya unit bersumber Excel).
     */
    public function saveFinancialRecord(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:global_financial_records,id',
            'business_unit_code' => 'required|string|exists:global_business_units,code',
            'period' => 'required|date',
            'report_type' => 'required|string|in:pnl,balance_sheet',
            'category' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
            'account_code' => 'nullable|string|max:50',
            'amount' => 'required|numeric',
            'entity_code' => 'nullable|string|max:50',
            'sub_category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $buCode = $validated['business_unit_code'];
        if (! ($request->user()?->canEditBusinessUnit($buCode) ?? false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Unit usaha ini bersumber dari database otomatis atau Anda tidak memiliki izin edit.',
                ], 403);
            }

            return back()->with('error', 'Akses ditolak: Unit usaha ini bersumber dari database otomatis atau Anda tidak memiliki izin edit.');
        }

        $formattedPeriod = Carbon::parse($validated['period'])->startOfMonth()->format('Y-m-d');

        if (! empty($validated['id'])) {
            $record = GlobalFinancialRecord::findOrFail($validated['id']);
            if (! ($request->user()?->canEditBusinessUnit($record->business_unit_code) ?? false)) {
                abort(403, 'Akses ditolak.');
            }

            $record->update([
                'amount' => $validated['amount'],
                'account_name' => trim($validated['account_name']),
                'account_code' => $validated['account_code'] ?? $record->account_code,
                'category' => $validated['category'] ?? $record->category,
                'sub_category' => $validated['sub_category'] ?? $record->sub_category,
                'notes' => $validated['notes'] ?? $record->notes,
                'status' => 'active',
            ]);
        } else {
            $entityCode = $validated['entity_code'] ?? null;
            if (! $entityCode) {
                $bu = GlobalBusinessUnit::with('entities')->where('code', $buCode)->first();
                $entityCode = $bu?->entities->first()?->code ?? strtoupper($buCode);
            }

            $record = GlobalFinancialRecord::updateOrCreate(
                [
                    'business_unit_code' => $buCode,
                    'entity_code' => $entityCode,
                    'period' => $formattedPeriod,
                    'report_type' => $validated['report_type'],
                    'category' => $validated['category'],
                    'account_name' => trim($validated['account_name']),
                ],
                [
                    'account_code' => $validated['account_code'] ?? null,
                    'amount' => $validated['amount'],
                    'sub_category' => $validated['sub_category'] ?? null,
                    'source_type' => 'manual',
                    'status' => 'active',
                    'notes' => $validated['notes'] ?? 'Input manual staff FA',
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Akun finansial berhasil disimpan.',
                'record' => $record,
            ]);
        }

        return back()->with('success', "Akun '{$record->account_name}' berhasil disimpan.");
    }

    /**
     * Hapus baris data akun finansial manual.
     */
    public function deleteFinancialRecord(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $record = GlobalFinancialRecord::findOrFail($id);

        if (! ($request->user()?->canEditBusinessUnit($record->business_unit_code) ?? false)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            return back()->with('error', 'Akses ditolak.');
        }

        $accName = $record->account_name;
        $record->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Akun '{$accName}' berhasil dihapus.",
            ]);
        }

        return back()->with('success', "Akun '{$accName}' berhasil dihapus.");
    }

    /**
     * Simpan / Perbarui status penutupan buku (closing) dan catatan periode.
     */
    public function savePeriodNote(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! ($user?->canEditPeriodNotes() ?? false)) {
            $msg = 'Akses ditolak: Akun Anda hanya memiliki hak akses untuk melihat catatan.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $validated = $request->validate([
            'period' => 'required|date',
            'business_unit_code' => 'nullable|string',
            'report_type' => 'required|in:all,pnl,balance_sheet',
            'closing_status' => 'required|in:unclosed,closed,draft',
            'notes' => 'nullable|string|max:5000',
        ]);

        $buCode = $validated['business_unit_code'] ?: 'all';
        if ($buCode !== 'all' && ! ($user?->canEditBusinessUnit($buCode) ?? true)) {
            $msg = 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah catatan unit ini.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $periodDate = Carbon::parse($validated['period'])->format('Y-m-d');
        $note = GlobalPeriodNote::whereDate('period', $periodDate)
            ->where('business_unit_code', $buCode)
            ->where('report_type', $validated['report_type'])
            ->first();

        if (! $note) {
            $note = new GlobalPeriodNote;
            $note->period = $periodDate;
            $note->business_unit_code = $buCode;
            $note->report_type = $validated['report_type'];
        }

        $note->closing_status = $validated['closing_status'];
        $note->notes = $validated['notes'];
        $note->user_id = $user?->id;
        $note->user_name = $user?->name ?? 'Pengguna';
        $note->save();

        $statusLabel = $note->closing_status_label;
        $msg = "Catatan & status closing periode ({$statusLabel}) berhasil disimpan.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'note' => $note,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Hapus catatan closing periode dan kembalikan ke default strip (-).
     */
    public function deletePeriodNote(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! ($user?->canEditPeriodNotes() ?? false)) {
            $msg = 'Akses ditolak: Akun Anda hanya memiliki hak akses untuk melihat catatan.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $note = GlobalPeriodNote::findOrFail($id);

        if ($note->business_unit_code !== 'all' && ! ($user?->canEditBusinessUnit($note->business_unit_code) ?? true)) {
            $msg = 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus catatan unit ini.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $note->delete();
        $msg = 'Catatan periode berhasil dihapus dan dikembalikan ke default (-).';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }
}
