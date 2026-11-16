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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if (!$selectedPeriod || !in_array($selectedPeriod, $availablePeriods)) {
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

        if (!$selectedPeriod || !in_array($selectedPeriod, $availablePeriods)) {
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

        if (!$selectedPeriod || !in_array($selectedPeriod, $availablePeriods)) {
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
        $logs = GlobalIngestionLog::query()->orderBy('created_at', 'desc')->limit(20)->get();

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
                $iqos = new IqosSyncService();
                $resIqos = $iqos->sync();
                $results[] = "IQOS: " . ($resIqos['count'] ?? 0) . " periode disinkronkan.";

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
                $spbu = new SpbuSyncService();
                $resSpbu = $spbu->sync($period);
                $results[] = "SPBU ({$period}): {$resSpbu['records_count']} akun finansial diserap (Omzet: Rp " . number_format($resSpbu['revenue_total'], 0, ',', '.') . ").";

                GlobalIngestionLog::create([
                    'business_unit_code' => 'spbu',
                    'source_type' => 'database_sync',
                    'filename' => "api_air.shift_sales ({$period})",
                    'records_count' => $resSpbu['records_count'] ?? 0,
                    'status' => 'success',
                    'details' => json_encode($resSpbu),
                ]);
            }

            return back()->with('success', 'Sinkronisasi Database Berhasil: ' . implode(' | ', $results));
        } catch (\Throwable $e) {
            GlobalIngestionLog::create([
                'business_unit_code' => $target,
                'source_type' => 'database_sync',
                'filename' => 'database_error',
                'records_count' => 0,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);

            return back()->with('error', 'Gagal sinkronisasi: ' . $e->getMessage());
        }
    }

    /**
     * Upload & Ingest File Excel (FMCG 9 Depo atau Ekspedisi Xtrans).
     */
    public function uploadExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200', // max 50MB
            'type' => 'required|in:fmcg,xtrans',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $filename = $file->getClientOriginalName();
        $path = $file->storeAs('imports', time() . '_' . $filename);
        $fullPath = storage_path('app/' . $path);

        $service = new ExcelIngestionService();

        try {
            if ($type === 'fmcg') {
                $res = $service->ingestFmcgFile($fullPath);
                $msg = "FMCG: Berhasil mengimpor {$res['records_inserted']} data transaksi dari " . count($res['sheets_processed']) . " cabang depo.";
            } else {
                $res = $service->ingestXtransFile($fullPath);
                $msg = "Ekspedisi: Berhasil mengimpor {$res['records_inserted']} data P&L dan Neraca PT Mekar Jaya Xtrans Jaya.";
            }

            GlobalIngestionLog::create([
                'business_unit_code' => $type,
                'source_type' => 'excel_upload',
                'filename' => $filename,
                'records_count' => $res['records_inserted'] ?? 0,
                'status' => 'success',
                'details' => json_encode($res),
            ]);

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            GlobalIngestionLog::create([
                'business_unit_code' => $type,
                'source_type' => 'excel_upload',
                'filename' => $filename,
                'records_count' => 0,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);

            return back()->with('error', 'Gagal memproses file Excel: ' . $e->getMessage());
        }
    }
}
