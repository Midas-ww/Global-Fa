<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpbuSyncService
{
    /**
     * Jalankan kalkulasi dan sinkronisasi laporan SPBU untuk bulan tertentu.
     *
     * @param string $yearMonth Format YYYY-MM (contoh: '2026-07')
     * @return array
     */
    public function sync(string $yearMonth = '2026-07'): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');
        $periodDate = $startDate;

        $script = "
        require 'D:/Mekar Jaya/API-SPBU/vendor/autoload.php';
        if (class_exists(Dotenv\Dotenv::class)) {
            \$dotenv = Dotenv\Dotenv::createMutable('D:/Mekar Jaya/API-SPBU');
            \$dotenv->load();
        }

        putenv('DB_DATABASE=api_air');
        \$_ENV['DB_DATABASE'] = 'api_air';
        \$_SERVER['DB_DATABASE'] = 'api_air';

        \$app = require_once 'D:/Mekar Jaya/API-SPBU/bootstrap/app.php';
        \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
        \$kernel->bootstrap();

        config(['database.connections.mysql.database' => 'api_air']);
        \Illuminate\Support\Facades\DB::purge('mysql');

        \$pnlService = app(App\Services\PnlService::class);
        \$pnl = \$pnlService->monthly('$startDate', '$endDate', false);

        \$bsService = app(App\Services\BalanceSheetService::class);
        \$bs = \$bsService->generate('$endDate');

        echo json_encode(['pnl' => \$pnl, 'bs' => \$bs]);
        ";

        $tempFile = storage_path('temp_spbu_calc_' . time() . '.php');
        file_put_contents($tempFile, "<?php\n" . $script);

        $phpBinary = PHP_BINARY ?: 'php';
        $output = shell_exec(escapeshellarg($phpBinary) . ' ' . escapeshellarg($tempFile) . ' 2>&1');
        @unlink($tempFile);

        if (!$output) {
            throw new \RuntimeException("Gagal menjalankan kalkulasi SPBU via CLI.");
        }

        $data = json_decode($output, true);
        if (!$data || !isset($data['pnl']) || !isset($data['bs'])) {
            throw new \RuntimeException("Output kalkulasi SPBU tidak valid.");
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
        if (!empty($pnl['revenue']['products'])) {
            foreach ($pnl['revenue']['products'] as $idx => $prod) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'revenue',
                    'sub_category' => 'fuel_sales',
                    'account_code' => '40' . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'Penjualan ' . ($prod['name'] ?? 'BBM'),
                    'amount' => (float) ($prod['net_amount'] ?? $prod['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'notes' => 'Volume: ' . number_format((float)($prod['volume'] ?? 0), 0, ',', '.') . ' L',
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
        if (!empty($pnl['cost_of_goods_sold']['products'])) {
            foreach ($pnl['cost_of_goods_sold']['products'] as $idx => $cProd) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'cogs',
                    'sub_category' => 'fuel_cost',
                    'account_code' => '50' . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'HPP ' . ($cProd['name'] ?? 'BBM'),
                    'amount' => (float) ($cProd['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Losis & Tera HPP
        if (!empty($pnl['cost_of_goods_sold']['bbm_losis'])) {
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

        // --- 3. OPEX ---
        $opexBreakdown = $pnl['operating_expenses']['breakdown'] ?? [];
        foreach ($opexBreakdown as $opKey => $val) {
            if ($opKey === 'total' || $opKey === 'subtotal_kas_besar') continue;
            $readableName = ucwords(str_replace('_', ' ', $opKey));
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'opex',
                'sub_category' => 'operasional_spbu',
                'account_code' => '6000-' . substr(md5($opKey), 0, 4),
                'account_name' => 'Biaya ' . $readableName,
                'amount' => (float) $val,
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];
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

        return [
            'success' => true,
            'business_unit' => 'spbu',
            'period' => $yearMonth,
            'records_count' => count($records),
            'revenue_total' => (float) ($pnl['revenue']['total'] ?? 0),
            'net_profit' => (float) ($pnl['net_profit'] ?? 0),
            'total_aktiva' => (float) ($summary['total_aktiva'] ?? 0),
        ];
    }
}
