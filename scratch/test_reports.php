<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Services\GlobalFinance\ConsolidationReportService::class);
$sum = $svc->getDashboardSummary('2026-07-01');
echo "KPI Revenue: Rp " . number_format($sum['kpis']['revenue'], 0, ',', '.') . "\n";
echo "KPI Gross Profit: Rp " . number_format($sum['kpis']['gross_profit'], 0, ',', '.') . "\n";
echo "KPI Net Profit: Rp " . number_format($sum['kpis']['net_profit'], 0, ',', '.') . "\n";

$pnl = $svc->getConsolidatedPnl('2026-07-01');
echo "PnL Current Revenue: Rp " . number_format($pnl['sections']['revenue']['subtotal_current'], 0, ',', '.') . "\n";
echo "PnL Net Profit Current: Rp " . number_format($pnl['summary']['net_profit_current'], 0, ',', '.') . "\n";

$bs = $svc->getConsolidatedBalanceSheet('2026-07-01');
echo "BS Total Assets: Rp " . number_format($bs['assets']['total'], 0, ',', '.') . "\n";
echo "BS Total Liab & Eq: Rp " . number_format($bs['total_liabilities_and_equity'], 0, ',', '.') . "\n";
echo "BS Balanced? " . ($bs['is_balanced'] ? 'YES' : 'NO') . "\n";
