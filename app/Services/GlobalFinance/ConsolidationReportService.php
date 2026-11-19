<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIntercompanyElimination;
use Carbon\Carbon;

class ConsolidationReportService
{
    /**
     * Ambil daftar semua periode yang tersedia dalam database, diurutkan dari terbaru.
     */
    public function getAvailablePeriods(): array
    {
        return GlobalFinancialRecord::query()
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->values()
            ->all();
    }

    /**
     * Ambil metadata semua Unit Bisnis & Entitas untuk filter UI.
     */
    public function getBusinessUnitsWithEntities(): array
    {
        return GlobalBusinessUnit::with(['entities' => function ($q) {
            $q->orderBy('sort_order');
        }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Dashboard Summary Eksekutif untuk periode tertentu.
     */
    public function getDashboardSummary(?string $period = null): array
    {
        if (! $period) {
            $latest = GlobalFinancialRecord::query()->max('period');
            $period = $latest ? Carbon::parse($latest)->format('Y-m-d') : '2026-07-01';
        }

        $records = GlobalFinancialRecord::query()
            ->where('period', $period)
            ->where('report_type', 'pnl')
            ->get();

        $revTotal = (float) $records->where('category', 'revenue')->sum('amount');
        $cogsTotal = (float) $records->where('category', 'cogs')->sum('amount');
        $gpTotal = $revTotal - $cogsTotal;
        $opexTotal = (float) $records->where('category', 'opex')->sum('amount');
        $opProfit = $gpTotal - $opexTotal;
        $otherInc = (float) $records->where('category', 'other_income')->sum('amount');
        $otherExp = (float) $records->where('category', 'other_expense')->sum('amount');
        $netProfit = $opProfit + $otherInc - $otherExp;

        $gpMargin = $revTotal > 0 ? round(($gpTotal / $revTotal) * 100, 2) : 0;
        $netMargin = $revTotal > 0 ? round(($netProfit / $revTotal) * 100, 2) : 0;
        $opexRatio = $revTotal > 0 ? round(($opexTotal / $revTotal) * 100, 2) : 0;

        // Breakdown per Unit Bisnis
        $bus = GlobalBusinessUnit::with('entities')->where('is_active', true)->orderBy('sort_order')->get();
        $byBusinessUnit = [];

        foreach ($bus as $bu) {
            $buRecs = $records->where('business_unit_code', $bu->code);
            $bRev = (float) $buRecs->where('category', 'revenue')->sum('amount');
            $bCogs = (float) $buRecs->where('category', 'cogs')->sum('amount');
            $bGp = $bRev - $bCogs;
            $bOpex = (float) $buRecs->where('category', 'opex')->sum('amount');
            $bNet = $bGp - $bOpex + (float) $buRecs->where('category', 'other_income')->sum('amount') - (float) $buRecs->where('category', 'other_expense')->sum('amount');

            $byBusinessUnit[] = [
                'code' => $bu->code,
                'name' => $bu->name,
                'color' => $bu->color,
                'icon' => $bu->icon,
                'revenue' => $bRev,
                'cogs' => $bCogs,
                'gross_profit' => $bGp,
                'gross_margin' => $bRev > 0 ? round(($bGp / $bRev) * 100, 2) : 0,
                'opex' => $bOpex,
                'net_profit' => $bNet,
                'net_margin' => $bRev > 0 ? round(($bNet / $bRev) * 100, 2) : 0,
            ];
        }

        // Tren 12 Bulan Terakhir untuk Grup
        $trendPeriods = GlobalFinancialRecord::query()
            ->where('report_type', 'pnl')
            ->where('period', '<=', $period)
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->limit(12)
            ->pluck('period')
            ->reverse()
            ->values();

        $monthlyTrends = [];
        foreach ($trendPeriods as $tp) {
            $tpStr = Carbon::parse($tp)->format('Y-m-d');
            $mRecs = GlobalFinancialRecord::query()
                ->where('period', $tpStr)
                ->where('report_type', 'pnl')
                ->get();

            $mRev = (float) $mRecs->where('category', 'revenue')->sum('amount');
            $mCogs = (float) $mRecs->where('category', 'cogs')->sum('amount');
            $mOpex = (float) $mRecs->where('category', 'opex')->sum('amount');
            $mOther = (float) $mRecs->where('category', 'other_income')->sum('amount') - (float) $mRecs->where('category', 'other_expense')->sum('amount');
            $mNet = ($mRev - $mCogs) - $mOpex + $mOther;

            $mGp = $mRev - $mCogs;

            $monthlyTrends[] = [
                'period' => $tpStr,
                'label' => Carbon::parse($tp)->translatedFormat('M Y'),
                'revenue' => $mRev,
                'cogs' => $mCogs,
                'gross_profit' => $mGp,
                'opex' => $mOpex,
                'net_profit' => $mNet,
            ];
        }

        // Ranking Entitas/Cabang
        $entityRankings = [];
        $entities = GlobalEntity::with('businessUnit')->where('is_active', true)->get();
        foreach ($entities as $ent) {
            $eRecs = $records->where('entity_code', $ent->code);
            $eRev = (float) $eRecs->where('category', 'revenue')->sum('amount');
            if ($eRev <= 0) {
                continue;
            }

            $eCogs = (float) $eRecs->where('category', 'cogs')->sum('amount');
            $eGp = $eRev - $eCogs;
            $eOpex = (float) $eRecs->where('category', 'opex')->sum('amount');
            $eNet = $eGp - $eOpex + (float) $eRecs->where('category', 'other_income')->sum('amount') - (float) $eRecs->where('category', 'other_expense')->sum('amount');

            $entityRankings[] = [
                'entity_code' => $ent->code,
                'name' => $ent->name,
                'business_unit' => $ent->businessUnit->name ?? '-',
                'revenue' => $eRev,
                'gross_profit' => $eGp,
                'gross_margin' => $eRev > 0 ? round(($eGp / $eRev) * 100, 2) : 0,
                'opex' => $eOpex,
                'net_profit' => $eNet,
                'net_margin' => $eRev > 0 ? round(($eNet / $eRev) * 100, 2) : 0,
            ];
        }

        usort($entityRankings, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return [
            'period' => $period,
            'period_label' => Carbon::parse($period)->translatedFormat('F Y'),
            'kpis' => [
                'revenue' => $revTotal,
                'cogs' => $cogsTotal,
                'gross_profit' => $gpTotal,
                'gross_margin_percentage' => $gpMargin,
                'opex' => $opexTotal,
                'opex_ratio' => $opexRatio,
                'operating_profit' => $opProfit,
                'net_profit' => $netProfit,
                'net_margin_percentage' => $netMargin,
            ],
            'by_business_unit' => $byBusinessUnit,
            'monthly_trends' => $monthlyTrends,
            'entity_rankings' => $entityRankings,
        ];
    }

    /**
     * Laporan Laba Rugi Komparatif Lengkap (Consolidated P&L Statement).
     */
    public function getConsolidatedPnl(string $period, ?string $buCode = null, ?string $entityCode = null): array
    {
        $prevPeriod = Carbon::parse($period)->subMonth()->format('Y-m-d');

        $queryCur = GlobalFinancialRecord::query()
            ->where('period', $period)
            ->where('report_type', 'pnl');

        $queryPrev = GlobalFinancialRecord::query()
            ->where('period', $prevPeriod)
            ->where('report_type', 'pnl');

        if ($buCode) {
            $queryCur->where('business_unit_code', $buCode);
            $queryPrev->where('business_unit_code', $buCode);
        }

        if ($entityCode) {
            $queryCur->where('entity_code', $entityCode);
            $queryPrev->where('entity_code', $entityCode);
        }

        $curRecs = $queryCur->get();
        $prevRecs = $queryPrev->get();

        // Bangun Laporan Terstruktur
        $categories = [
            'revenue' => 'PENDAPATAN USAHA (REVENUE)',
            'cogs' => 'HARGA POKOK PENJUALAN (COGS)',
            'opex' => 'BEBAN OPERASIONAL (OPERATING EXPENSES)',
            'other_income' => 'PENDAPATAN DILUAR USAHA',
            'other_expense' => 'BEBAN DILUAR USAHA',
        ];

        $reportSections = [];
        foreach ($categories as $catKey => $catTitle) {
            $items = [];
            // Ambil semua akun unik di kategori ini
            $accountNames = $curRecs->where('category', $catKey)->pluck('account_name')
                ->merge($prevRecs->where('category', $catKey)->pluck('account_name'))
                ->unique();

            $subtotalCur = 0;
            $subtotalPrev = 0;

            foreach ($accountNames as $accName) {
                $matchedCur = $curRecs->where('category', $catKey)->where('account_name', $accName);
                $matchedPrev = $prevRecs->where('category', $catKey)->where('account_name', $accName);
                $curAmount = (float) $matchedCur->sum('amount');
                $prevAmount = (float) $matchedPrev->sum('amount');

                $firstRec = $matchedCur->first() ?? $matchedPrev->first();
                $accCode = $firstRec->account_code ?? null;
                $buCodeOfItem = $firstRec->business_unit_code ?? null;

                $variance = $curAmount - $prevAmount;
                $growthPct = $prevAmount != 0 ? round(($variance / abs($prevAmount)) * 100, 2) : ($curAmount != 0 ? 100 : 0);

                $items[] = [
                    'account_code' => $accCode,
                    'account_name' => $accName,
                    'business_unit_code' => $buCodeOfItem,
                    'current_amount' => $curAmount,
                    'previous_amount' => $prevAmount,
                    'variance' => $variance,
                    'growth_pct' => $growthPct,
                ];

                $subtotalCur += $curAmount;
                $subtotalPrev += $prevAmount;
            }

            usort($items, fn ($a, $b) => $b['current_amount'] <=> $a['current_amount']);

            $subVar = $subtotalCur - $subtotalPrev;
            $subGrowth = $subtotalPrev != 0 ? round(($subVar / abs($subtotalPrev)) * 100, 2) : 0;

            $reportSections[$catKey] = [
                'title' => $catTitle,
                'items' => $items,
                'subtotal_current' => $subtotalCur,
                'subtotal_previous' => $subtotalPrev,
                'variance' => $subVar,
                'growth_pct' => $subGrowth,
            ];
        }

        // Kalkulasi Summary
        $revCur = $reportSections['revenue']['subtotal_current'];
        $revPrev = $reportSections['revenue']['subtotal_previous'];
        $cogsCur = $reportSections['cogs']['subtotal_current'];
        $cogsPrev = $reportSections['cogs']['subtotal_previous'];
        $gpCur = $revCur - $cogsCur;
        $gpPrev = $revPrev - $cogsPrev;
        $opexCur = $reportSections['opex']['subtotal_current'];
        $opexPrev = $reportSections['opex']['subtotal_previous'];
        $opProfitCur = $gpCur - $opexCur;
        $opProfitPrev = $gpPrev - $opexPrev;
        $otherIncCur = $reportSections['other_income']['subtotal_current'];
        $otherIncPrev = $reportSections['other_income']['subtotal_previous'];
        $otherExpCur = $reportSections['other_expense']['subtotal_current'];
        $otherExpPrev = $reportSections['other_expense']['subtotal_previous'];
        $netCur = $opProfitCur + $otherIncCur - $otherExpCur;
        $netPrev = $opProfitPrev + $otherIncPrev - $otherExpPrev;

        // Tren 12 Bulan Terakhir untuk Filter P&L
        $trendPeriods = GlobalFinancialRecord::query()
            ->where('report_type', 'pnl')
            ->where('period', '<=', $period)
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->limit(12)
            ->pluck('period')
            ->reverse()
            ->values();

        $pnlTrends = [];
        foreach ($trendPeriods as $tp) {
            $tpStr = Carbon::parse($tp)->format('Y-m-d');
            $tQuery = GlobalFinancialRecord::query()
                ->where('period', $tpStr)
                ->where('report_type', 'pnl');

            if ($buCode) {
                $tQuery->where('business_unit_code', $buCode);
            }
            if ($entityCode) {
                $tQuery->where('entity_code', $entityCode);
            }
            $tRecs = $tQuery->get();

            $tRev = (float) $tRecs->where('category', 'revenue')->sum('amount');
            $tCogs = (float) $tRecs->where('category', 'cogs')->sum('amount');
            $tOpex = (float) $tRecs->where('category', 'opex')->sum('amount');
            $tNet = ($tRev - $tCogs) - $tOpex;

            $pnlTrends[] = [
                'period' => $tpStr,
                'label' => Carbon::parse($tp)->translatedFormat('M Y'),
                'revenue' => $tRev,
                'cogs' => $tCogs,
                'gross_profit' => $tRev - $tCogs,
                'opex' => $tOpex,
                'net_profit' => $tNet,
            ];
        }

        // Breakdown Kontribusi per Unit Bisnis untuk Periode Ini
        $bus = GlobalBusinessUnit::with('entities')->where('is_active', true)->orderBy('sort_order')->get();
        $byBusinessUnit = [];

        foreach ($bus as $bu) {
            $buRecs = $curRecs->where('business_unit_code', $bu->code);
            $bRev = (float) $buRecs->where('category', 'revenue')->sum('amount');
            $bCogs = (float) $buRecs->where('category', 'cogs')->sum('amount');
            $bGp = $bRev - $bCogs;
            $bOpex = (float) $buRecs->where('category', 'opex')->sum('amount');
            $bOther = (float) $buRecs->where('category', 'other_income')->sum('amount') - (float) $buRecs->where('category', 'other_expense')->sum('amount');
            $bNet = $bGp - $bOpex + $bOther;

            $byBusinessUnit[] = [
                'code' => $bu->code,
                'name' => $bu->name,
                'color' => $bu->color,
                'icon' => $bu->icon,
                'revenue' => $bRev,
                'cogs' => $bCogs,
                'gross_profit' => $bGp,
                'gross_margin' => $bRev > 0 ? round(($bGp / $bRev) * 100, 2) : 0,
                'opex' => $bOpex,
                'net_profit' => $bNet,
                'net_margin' => $bRev > 0 ? round(($bNet / $bRev) * 100, 2) : 0,
            ];
        }

        // Breakdown Kontribusi per Entitas / Cabang jika Unit Bisnis dipilih
        $byEntity = [];
        if ($buCode && empty($entityCode)) {
            $buEntities = GlobalEntity::whereHas('businessUnit', fn ($q) => $q->where('code', $buCode))
                ->orderBy('sort_order')
                ->get();

            foreach ($buEntities as $ent) {
                $eRecs = $curRecs->where('entity_code', $ent->code);
                $eRev = (float) $eRecs->where('category', 'revenue')->sum('amount');
                $eCogs = (float) $eRecs->where('category', 'cogs')->sum('amount');
                $eGp = $eRev - $eCogs;
                $eOpex = (float) $eRecs->where('category', 'opex')->sum('amount');
                $eOther = (float) $eRecs->where('category', 'other_income')->sum('amount') - (float) $eRecs->where('category', 'other_expense')->sum('amount');
                $eNet = $eGp - $eOpex + $eOther;

                $byEntity[] = [
                    'code' => $ent->code,
                    'name' => $ent->name,
                    'location' => $ent->location,
                    'group' => str_starts_with($ent->code, 'CCOD') ? 'ccod' : 'depo',
                    'color' => $ent->businessUnit->color ?? '#d97706',
                    'revenue' => $eRev,
                    'cogs' => $eCogs,
                    'gross_profit' => $eGp,
                    'gross_margin' => $eRev > 0 ? round(($eGp / $eRev) * 100, 2) : 0,
                    'opex' => $eOpex,
                    'other' => $eOther,
                    'net_profit' => $eNet,
                    'net_margin' => $eRev > 0 ? round(($eNet / $eRev) * 100, 2) : 0,
                    'record_count' => $eRecs->count(),
                ];
            }
        }

        return [
            'period' => $period,
            'period_label' => Carbon::parse($period)->translatedFormat('F Y'),
            'previous_period_label' => Carbon::parse($prevPeriod)->translatedFormat('F Y'),
            'filters' => [
                'business_unit_code' => $buCode,
                'entity_code' => $entityCode,
            ],
            'sections' => $reportSections,
            'by_business_unit' => $byBusinessUnit,
            'by_entity' => $byEntity,
            'summary' => [
                'gross_profit_current' => $gpCur,
                'gross_profit_previous' => $gpPrev,
                'operating_profit_current' => $opProfitCur,
                'operating_profit_previous' => $opProfitPrev,
                'net_profit_current' => $netCur,
                'net_profit_previous' => $netPrev,
                'net_profit_variance' => $netCur - $netPrev,
                'net_profit_growth' => $netPrev != 0 ? round((($netCur - $netPrev) / abs($netPrev)) * 100, 2) : 0,
            ],
            'monthly_trends' => $pnlTrends,
        ];
    }

    /**
     * Laporan Neraca Konsolidasi (Consolidated Balance Sheet).
     */
    public function getConsolidatedBalanceSheet(string $period, ?string $buCode = null, ?string $entityCode = null): array
    {
        $query = GlobalFinancialRecord::query()
            ->where('period', $period)
            ->where('report_type', 'balance_sheet');

        if ($buCode) {
            $query->where('business_unit_code', $buCode);
        }
        if ($entityCode) {
            $query->where('entity_code', $entityCode);
        }

        $records = $query->get();

        $assetItems = $records->where('category', 'asset')->values();
        $liabilityItems = $records->where('category', 'liability')->values();
        $equityItems = $records->where('category', 'equity')->values();

        $totalAssets = (float) $assetItems->sum('amount');
        $totalLiab = (float) $liabilityItems->sum('amount');
        $totalEquity = (float) $equityItems->sum('amount');

        // Eliminasi Intercompany
        $eliminations = GlobalIntercompanyElimination::query()
            ->where('period', '<=', $period)
            ->where('is_active', true)
            ->get();

        $eliminationAmount = (float) $eliminations->sum('amount');

        return [
            'period' => $period,
            'period_label' => Carbon::parse($period)->translatedFormat('F Y'),
            'assets' => [
                'items' => $assetItems,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'items' => $liabilityItems,
                'total' => $totalLiab,
            ],
            'equity' => [
                'items' => $equityItems,
                'total' => $totalEquity,
            ],
            'eliminations' => [
                'items' => $eliminations,
                'total_elimination' => $eliminationAmount,
            ],
            'total_liabilities_and_equity' => $totalLiab + $totalEquity,
            'is_balanced' => abs($totalAssets - ($totalLiab + $totalEquity)) < 1.0,
        ];
    }
}
