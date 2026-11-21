<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SpbuSyncService
{
    /**
     * Jalankan kalkulasi dan sinkronisasi laporan SPBU untuk bulan tertentu.
     *
     * @param  string  $yearMonth  Format YYYY-MM (contoh: '2026-07')
     */
    public function sync(string $yearMonth = '2026-07'): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');
        $periodDate = $startDate;

        $spbuPath = env('SPBU_PROJECT_PATH', 'D:/Mekar Jaya/API-SPBU');
        if (! file_exists($spbuPath.'/vendor/autoload.php')) {
            $fallbacks = [
                base_path('../API-SPBU'),
                base_path('../api-spbu'),
                '/var/www/api-spbu',
                'C:/Mekar Jaya/API-SPBU',
            ];
            foreach ($fallbacks as $fb) {
                if (file_exists($fb.'/vendor/autoload.php')) {
                    $spbuPath = $fb;
                    break;
                }
            }
        }

        $spbuPathNormalized = str_replace('\\', '/', $spbuPath);
        if (! file_exists($spbuPathNormalized.'/vendor/autoload.php')) {
            throw new \RuntimeException("Path project API-SPBU tidak ditemukan di: [{$spbuPathNormalized}]. Harap atur 'SPBU_PROJECT_PATH' di file .env server.");
        }

        $spbuDb = config('database.connections.mysql_spbu.database', 'api_air');

        $script = "
        require '{$spbuPathNormalized}/vendor/autoload.php';
        if (class_exists(Dotenv\Dotenv::class)) {
            \$dotenv = Dotenv\Dotenv::createMutable('{$spbuPathNormalized}');
            \$dotenv->load();
        }

        putenv('DB_DATABASE={$spbuDb}');
        \$_ENV['DB_DATABASE'] = '{$spbuDb}';
        \$_SERVER['DB_DATABASE'] = '{$spbuDb}';

        \$app = require_once '{$spbuPathNormalized}/bootstrap/app.php';
        \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
        \$kernel->bootstrap();

        config(['database.connections.mysql.database' => '{$spbuDb}']);
        \Illuminate\Support\Facades\DB::purge('mysql');

        \$pnlService = app(App\Services\PnlService::class);
        \$pnl = \$pnlService->monthly('{$startDate}', '{$endDate}', false);

        \$bsService = app(App\Services\BalanceSheetService::class);
        \$bs = \$bsService->generate('{$endDate}');

        echo json_encode(['pnl' => \$pnl, 'bs' => \$bs]);
        ";

        $tempFile = storage_path('temp_spbu_calc_'.time().'.php');
        file_put_contents($tempFile, "<?php\n".$script);

        $phpBinary = PHP_BINARY ?: 'php';
        $output = shell_exec(escapeshellarg($phpBinary).' '.escapeshellarg($tempFile).' 2>&1');
        @unlink($tempFile);

        if (! $output) {
            throw new \RuntimeException('Gagal menjalankan kalkulasi SPBU via CLI.');
        }

        $data = json_decode($output, true);
        if (! $data || ! isset($data['pnl']) || ! isset($data['bs'])) {
            throw new \RuntimeException('Output kalkulasi SPBU tidak valid.');
        }

        $pnl = $data['pnl'];
        $bs = $data['bs'];

        // Hapus data lama agar idempoten
        GlobalFinancialRecord::query()
            ->where('entity_code', 'SPBU_44')
            ->where('period', $periodDate)
            ->delete();

        $records = [];
        $status = 'final';

        // --- 1. REVENUE ---
        if (! empty($pnl['revenue']['products'])) {
            foreach ($pnl['revenue']['products'] as $idx => $prod) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'revenue',
                    'sub_category' => 'fuel_sales',
                    'account_code' => '40'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'Penjualan '.($prod['name'] ?? 'BBM'),
                    'amount' => (float) ($prod['net_amount'] ?? $prod['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'notes' => 'Volume: '.number_format((float) ($prod['volume'] ?? 0), 0, ',', '.').' L',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'revenue',
                'sub_category' => 'fuel_sales',
                'account_code' => '4001',
                'account_name' => 'Total Penjualan BBM',
                'amount' => (float) ($pnl['revenue']['total'] ?? 0),
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // --- 2. COGS (HPP) ---
        $sumProductHpp = 0.0;
        if (! empty($pnl['cost_of_goods_sold']['products'])) {
            foreach ($pnl['cost_of_goods_sold']['products'] as $idx => $cProd) {
                $cProdAmt = (float) ($cProd['amount'] ?? 0);
                $sumProductHpp += $cProdAmt;
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'cogs',
                    'sub_category' => 'fuel_cost',
                    'account_code' => '50'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'HPP '.($cProd['name'] ?? 'BBM'),
                    'amount' => $cProdAmt,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Defensive Fallback: Bila ada selisih antara HPP total dan rincian produk (misal ada produk baru belum terpetakan)
        $expectedBbmHpp = (float) ($pnl['cost_of_goods_sold']['bbm_hpp'] ?? 0);
        $diffHpp = round($expectedBbmHpp - $sumProductHpp, 2);
        if (abs($diffHpp) > 1.0) {
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'cogs',
                'sub_category' => 'fuel_cost',
                'account_code' => '5090',
                'account_name' => 'Penyesuaian HPP BBM Belum Terklasifikasi',
                'amount' => $diffHpp,
                'status' => $status,
                'source_type' => 'database_sync',
                'notes' => 'Otomatis dibuat untuk menjaga konsistensi total HPP BBM dengan sistem SPBU',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Losis & Tera HPP
        if (! empty($pnl['cost_of_goods_sold']['bbm_losis'])) {
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'cogs',
                'sub_category' => 'shrinkage_and_losses',
                'account_code' => '5098',
                'account_name' => 'Losses BBM (Penguapan & Susut)',
                'amount' => (float) $pnl['cost_of_goods_sold']['bbm_losis'],
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($pnl['cost_of_goods_sold']['bbm_tera'])) {
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'cogs',
                'sub_category' => 'shrinkage_and_losses',
                'account_code' => '5099',
                'account_name' => 'Biaya Tera BBM (Pengujian Takaran Dispenser)',
                'amount' => (float) $pnl['cost_of_goods_sold']['bbm_tera'],
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // --- 3. OPEX ---
        $opexBreakdown = $pnl['operating_expenses']['breakdown'] ?? [];
        foreach ($opexBreakdown as $opKey => $val) {
            if ($opKey === 'total' || $opKey === 'subtotal_kas_besar') {
                continue;
            }
            $readableName = ucwords(str_replace('_', ' ', $opKey));
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'opex',
                'sub_category' => 'operasional_spbu',
                'account_code' => '6000-'.substr(md5($opKey), 0, 4),
                'account_name' => 'Biaya '.$readableName,
                'amount' => (float) $val,
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // --- 4. PENDAPATAN & BEBAN DILUAR USAHA (OTHER INCOME / EXPENSE) ---
        if (! empty($pnl['other_income']['details'])) {
            foreach ($pnl['other_income']['details'] as $idx => $oItem) {
                $oAmt = (float) ($oItem['amount'] ?? 0);
                if ($oAmt == 0.0) {
                    continue;
                }

                $isPositive = $oAmt > 0;
                $oName = trim($oItem['name'] ?? 'Lain-Lain');

                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => $isPositive ? 'other_income' : 'other_expense',
                    'sub_category' => 'pendapatan_beban_lain',
                    'account_code' => $isPositive ? '90'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) : '80'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => $oName,
                    'amount' => abs($oAmt),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } elseif (! empty($pnl['other_income']['total'])) {
            $totOther = (float) $pnl['other_income']['total'];
            if ($totOther != 0.0) {
                $isPositive = $totOther > 0;
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => $isPositive ? 'other_income' : 'other_expense',
                    'sub_category' => 'pendapatan_beban_lain',
                    'account_code' => $isPositive ? '9001' : '8001',
                    'account_name' => $isPositive ? 'Pendapatan Lain-Lain' : 'Beban Lain-Lain',
                    'amount' => abs($totOther),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // --- 4. NERACA: AKTIVA ---
        $summary = $bs['summary'] ?? [];
        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'sub_category' => 'cash_and_bank',
            'account_code' => '1101',
            'account_name' => 'Kas & Saldo Bank SPBU',
            'amount' => (float) ($summary['total_kas'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'sub_category' => 'inventory',
            'account_code' => '1301',
            'account_name' => 'Persediaan Stok BBM & Pelumas',
            'amount' => (float) ($summary['total_stok'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'sub_category' => 'accounts_receivable',
            'account_code' => '1201',
            'account_name' => 'Piutang Customer & Pertamina',
            'amount' => (float) ($summary['total_piutang'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'sub_category' => 'fixed_assets',
            'account_code' => '1601',
            'account_name' => 'Aktiva Tetap (Bangunan, Tangki, Dispenser, ATG)',
            'amount' => (float) ($summary['total_aktiva_tetap'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // --- 5. NERACA: PASIVA ---
        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'liability',
            'sub_category' => 'accounts_payable',
            'account_code' => '2101',
            'account_name' => 'Hutang Lancar & Polair',
            'amount' => (float) ($summary['total_hutang'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $records[] = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'balance_sheet',
            'category' => 'equity',
            'sub_category' => 'retained_earnings',
            'account_code' => '3201',
            'account_name' => 'Modal & Akumulasi Laba Ditahan SPBU',
            'amount' => (float) ($summary['total_modal'] ?? 0),
            'status' => $status,
            'source_type' => 'database_sync',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $defaultRecord = [
            'business_unit_code' => 'spbu',
            'entity_code' => 'SPBU_44',
            'period' => $periodDate,
            'report_type' => 'pnl',
            'category' => 'revenue',
            'sub_category' => null,
            'account_code' => null,
            'account_name' => '',
            'amount' => 0.0,
            'status' => $status,
            'source_type' => 'database_sync',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $records = array_map(function ($r) use ($defaultRecord) {
            return array_merge($defaultRecord, $r);
        }, $records);

        GlobalFinancialRecord::insert($records);

        // Self-Reconciliation Check: Verifikasi Laba Bersih di Global FA vs API SPBU
        $sumRev = 0.0;
        $sumCogs = 0.0;
        $sumOpex = 0.0;
        $sumOtherInc = 0.0;
        $sumOtherExp = 0.0;
        foreach ($records as $r) {
            if ($r['report_type'] !== 'pnl') {
                continue;
            }
            match ($r['category']) {
                'revenue' => $sumRev += (float) $r['amount'],
                'cogs' => $sumCogs += (float) $r['amount'],
                'opex' => $sumOpex += (float) $r['amount'],
                'other_income' => $sumOtherInc += (float) $r['amount'],
                'other_expense' => $sumOtherExp += (float) $r['amount'],
                default => null,
            };
        }
        $calcNetProfit = round(($sumRev - $sumCogs) - $sumOpex + ($sumOtherInc - $sumOtherExp), 2);
        $expectedNetProfit = round((float) ($pnl['net_profit'] ?? 0), 2);
        $variance = round($calcNetProfit - $expectedNetProfit, 2);
        $isReconciled = abs($variance) < 1.0;

        if (! $isReconciled) {
            Log::warning("SPBU Sync Variance Mismatch for period [{$yearMonth}]: Calculated Net Profit = {$calcNetProfit}, Expected = {$expectedNetProfit}, Variance = {$variance}");
        }

        return [
            'success' => true,
            'business_unit' => 'spbu',
            'period' => $yearMonth,
            'records_count' => count($records),
            'revenue_total' => (float) ($pnl['revenue']['total'] ?? 0),
            'net_profit' => $calcNetProfit,
            'expected_net_profit' => $expectedNetProfit,
            'reconciled' => $isReconciled,
            'variance' => $variance,
            'total_aktiva' => (float) ($summary['total_aktiva'] ?? 0),
        ];
    }
}
