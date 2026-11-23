<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IqosSyncService
{
    /**
     * Sinkronisasi data PnL dan Neraca dari database SIR-IQOS ke Global Financial Records.
     *
     * @param  string|null  $targetPeriod  Format YYYY-MM (misal: '2026-07') atau null untuk semua periode
     */
    public function sync(?string $targetPeriod = null): array
    {
        $query = DB::connection('mysql_iqos')
            ->table('iqos_monthly_reports')
            ->orderBy('period');

        if ($targetPeriod) {
            $query->where('period', $targetPeriod);
        }

        $reports = $query->get()->all();

        // Sertakan periode berjalan (live unfinalized) dari SIR-IQOS jika belum tercatat di iqos_monthly_reports
        $existingPeriods = array_map(fn ($r) => $r->period, $reports);
        $candidatePeriods = $targetPeriod ? [$targetPeriod] : ['2026-09', '2026-10', '2026-11'];

        foreach ($candidatePeriods as $cp) {
            if (! in_array($cp, $existingPeriods)) {
                $liveRep = $this->fetchLiveReport($cp);
                if ($liveRep) {
                    $reports[] = $liveRep;
                }
            }
        }

        $syncedCount = 0;
        $periods = [];

        $parseItems = function ($val) {
            if (is_array($val)) {
                return $val;
            }
            if (is_string($val)) {
                return json_decode($val, true) ?: [];
            }

            return [];
        };

        foreach ($reports as $rep) {
            $periodDate = Carbon::createFromFormat('Y-m', $rep->period)->startOfMonth()->format('Y-m-d');
            $periods[] = $rep->period;

            // Hapus data lama untuk periode & entitas ini agar idempoten
            GlobalFinancialRecord::query()
                ->where('entity_code', 'IQOS_RETAIL')
                ->where('period', $periodDate)
                ->delete();

            $records = [];
            $status = ($rep->is_finalized ?? false) ? 'final' : 'running';

            // --- 1. PNL: REVENUE ---
            $records[] = [
                'business_unit_code' => 'iqos',
                'entity_code' => 'IQOS_RETAIL',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'revenue',
                'sub_category' => 'regular_sales',
                'account_code' => '4001',
                'account_name' => 'Penjualan Reguler Retail Store',
                'amount' => (float) ($rep->sales_regular ?? 0),
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Promo items revenue
            $promoItems = $parseItems($rep->sales_promo_items ?? '[]');
            foreach ($promoItems as $item) {
                $itemArr = (array) $item;
                if (! empty($itemArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'revenue',
                        'sub_category' => 'promo_sales',
                        'account_code' => '4002',
                        'account_name' => $itemArr['name'] ?? 'Penjualan Promo Exclusive',
                        'amount' => (float) $itemArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- 2. PNL: COGS ---
            $records[] = [
                'business_unit_code' => 'iqos',
                'entity_code' => 'IQOS_RETAIL',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'cogs',
                'sub_category' => 'product_cost',
                'account_code' => '5001',
                'account_name' => 'HPP Produk Retail (Consumable & Goods)',
                'amount' => (float) ($rep->cogs ?? 0),
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // --- 3. PNL: OPEX ---
            $opexDetails = $parseItems($rep->operating_expenses_detail ?? '[]');
            if (! empty($opexDetails)) {
                foreach ($opexDetails as $idx => $exp) {
                    $expArr = (array) $exp;
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'opex',
                        'sub_category' => $expArr['category'] ?? 'Operasional Toko',
                        'account_code' => '60'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $expArr['name'] ?? 'Beban Operasional',
                        'amount' => (float) ($expArr['amount'] ?? 0),
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            } else {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'opex',
                    'sub_category' => 'Operasional Toko',
                    'account_code' => '6001',
                    'account_name' => 'Beban Operasional Retail Store',
                    'amount' => (float) ($rep->operating_expenses ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // --- 4. PNL: OTHER INCOME (PROGRAM PRINCIPAL) ---
            $programIncomes = $parseItems($rep->program_incomes ?? '[]');
            foreach ($programIncomes as $idx => $inc) {
                $incArr = (array) $inc;
                if (! empty($incArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'other_income',
                        'sub_category' => 'principal_support',
                        'account_code' => '90'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $incArr['name'] ?? 'Pendapatan Program Principal',
                        'amount' => (float) $incArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- 5. NERACA: ASSETS ---
            // 5.1 Kas & Bank
            if ((float) $rep->bank_bri != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'cash_and_bank',
                    'account_code' => '1101',
                    'account_name' => 'Bank BRI (Buku Bank Riil)',
                    'amount' => (float) $rep->bank_bri,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ((float) ($rep->bank_bca ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'cash_and_bank',
                    'account_code' => '1102',
                    'account_name' => 'Kas di Bank BCA Retail',
                    'amount' => (float) $rep->bank_bca,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ((float) ($rep->kas_cash ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'cash_and_bank',
                    'account_code' => '1103',
                    'account_name' => 'Kas Tunai Toko Retail',
                    'amount' => (float) $rep->kas_cash,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Inflow Pending Items (Kas Masuk Tertunda)
            $inflows = $parseItems($rep->inflow_pending_items ?? '[]');
            foreach ($inflows as $idx => $inf) {
                $infArr = (array) $inf;
                if (! empty($infArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'cash_and_bank',
                        'account_code' => '111'.($idx + 1),
                        'account_name' => $infArr['name'] ?? 'Inflow Pending',
                        'amount' => (float) $infArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Selisih Kasir Bobi (Bisa bernilai negatif atau positif)
            if ((float) ($rep->selisih_bobi ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'cash_and_bank',
                    'account_code' => '1199',
                    'account_name' => 'Selisih Bobi',
                    'amount' => (float) $rep->selisih_bobi,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 5.2 Persediaan Stok
            if ((float) ($rep->stock_value ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'inventory',
                    'account_code' => '1301',
                    'account_name' => 'Nilai Fisik Stok IQOS',
                    'amount' => (float) $rep->stock_value,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 5.3 Piutang Usaha & Insentif Resmi (Program Principal)
            $recPrograms = $parseItems($rep->receivables_program_items ?? '[]');
            foreach ($recPrograms as $idx => $pItem) {
                $pItemArr = (array) $pItem;
                if (! empty($pItemArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'accounts_receivable',
                        'account_code' => '12'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $pItemArr['name'] ?? 'Piutang Program',
                        'amount' => (float) $pItemArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Piutang Personel / Karyawan
            $recPersonnel = $parseItems($rep->receivables_personnel_items ?? '[]');
            foreach ($recPersonnel as $idx => $personItem) {
                $personItemArr = (array) $personItem;
                if (! empty($personItemArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'accounts_receivable',
                        'account_code' => '125'.$idx,
                        'account_name' => 'Piutang: '.($personItemArr['name'] ?? 'Personel'),
                        'amount' => (float) $personItemArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // 5.4 Aktiva Tetap & Investasi Awal
            if ((float) ($rep->initial_investment_asset ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'fixed_assets',
                    'account_code' => '1601',
                    'account_name' => 'Investasi Awal (Program IQOS)',
                    'amount' => (float) $rep->initial_investment_asset,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Aset Lain-lain
            $otherAssets = $parseItems($rep->other_assets_items ?? '[]');
            foreach ($otherAssets as $idx => $oAsset) {
                $oAssetArr = (array) $oAsset;
                if (! empty($oAssetArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'other_assets',
                        'account_code' => '17'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $oAssetArr['name'] ?? 'Aset Lainnya',
                        'amount' => (float) $oAssetArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- 6. NERACA: LIABILITIES ---
            if ((float) ($rep->debt_iqos ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'liability',
                    'sub_category' => 'accounts_payable',
                    'account_code' => '2101',
                    'account_name' => 'Hutang IQOS (Tagihan Official)',
                    'amount' => (float) $rep->debt_iqos,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ((float) ($rep->debt_payment_iqos ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'liability',
                    'sub_category' => 'accounts_payable',
                    'account_code' => '2102',
                    'account_name' => 'Hutang Payment ke IQOS',
                    'amount' => (float) $rep->debt_payment_iqos,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Kewajiban Lain / Titipan
            $otherLiabs = $parseItems($rep->other_liabilities_items ?? '[]');
            foreach ($otherLiabs as $idx => $oLiab) {
                $oLiabArr = (array) $oLiab;
                if (! empty($oLiabArr['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'liability',
                        'sub_category' => 'other_liabilities',
                        'account_code' => '22'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $oLiabArr['name'] ?? 'Kewajiban Lain / Titipan',
                        'amount' => (float) $oLiabArr['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- 7. NERACA: EQUITY ---
            if ((float) ($rep->initial_investment_equity ?? 0) != 0) {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'equity',
                    'sub_category' => 'capital',
                    'account_code' => '3101',
                    'account_name' => 'Modal Investasi Awal',
                    'amount' => (float) $rep->initial_investment_equity,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Akumulasi Laba Ditahan (Detail per bulan sesuai SIR-IQOS jika ada)
            $retainedItems = $parseItems($rep->retained_earnings_items ?? '[]');
            if (! empty($retainedItems)) {
                foreach ($retainedItems as $idx => $rItem) {
                    $rItemArr = (array) $rItem;
                    if (isset($rItemArr['amount']) && (float) $rItemArr['amount'] != 0) {
                        $records[] = [
                            'business_unit_code' => 'iqos',
                            'entity_code' => 'IQOS_RETAIL',
                            'period' => $periodDate,
                            'report_type' => 'balance_sheet',
                            'category' => 'equity',
                            'sub_category' => 'retained_earnings',
                            'account_code' => '32'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                            'account_name' => $rItemArr['name'] ?? ('Laba Ditahan #'.($idx + 1)),
                            'amount' => (float) $rItemArr['amount'],
                            'status' => $status,
                            'source_type' => 'database_sync',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            } else {
                $records[] = [
                    'business_unit_code' => 'iqos',
                    'entity_code' => 'IQOS_RETAIL',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'equity',
                    'sub_category' => 'retained_earnings',
                    'account_code' => '3201',
                    'account_name' => 'Akumulasi Laba Ditahan Retail',
                    'amount' => (float) ($rep->total_retained_earnings ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $defaultRecord = [
                'business_unit_code' => 'iqos',
                'entity_code' => 'IQOS_RETAIL',
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
            $syncedCount++;
        }

        return [
            'success' => true,
            'business_unit' => 'iqos',
            'synced_periods' => $periods,
            'count' => $syncedCount,
        ];
    }

    /**
     * Ambil kalkulasi live PnL & Neraca dari project SIR-IQOS untuk periode yang belum difinalisasi.
     */
    public function fetchLiveReport(string $period): ?object
    {
        $iqosPath = env('IQOS_PROJECT_PATH', 'D:/Mekar Jaya/SIR-IQOS');
        if (! file_exists($iqosPath.'/vendor/autoload.php')) {
            $fallbacks = [
                base_path('../SIR-IQOS'),
                base_path('../sir-iqos'),
                '/var/www/sir-iqos',
                'C:/Mekar Jaya/SIR-IQOS',
            ];
            foreach ($fallbacks as $fb) {
                if (file_exists($fb.'/vendor/autoload.php')) {
                    $iqosPath = $fb;
                    break;
                }
            }
        }

        $iqosPathNormalized = str_replace('\\', '/', $iqosPath);
        if (! file_exists($iqosPathNormalized.'/vendor/autoload.php')) {
            return null;
        }

        $iqosDb = config('database.connections.mysql_iqos.database', 'sir_iqos');

        $script = <<<'PHP'
require '%s/vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    $dotenv = Dotenv\Dotenv::createMutable('%s');
    $dotenv->load();
}

putenv('DB_DATABASE=%s');
$_ENV['DB_DATABASE'] = '%s';
$_SERVER['DB_DATABASE'] = '%s';

$app = require_once '%s/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.mysql.database' => '%s']);
\Illuminate\Support\Facades\DB::purge('mysql');

$period = '%s';
$ctrl = app(App\Http\Controllers\FinancialReportController::class);
$ref = new ReflectionClass($ctrl);

$mPnl = $ref->getMethod('calculateSinglePeriodPnl');
$mPnl->setAccessible(true);
$pnl = $mPnl->invoke($ctrl, $period, ['period' => $period], null, true);

$neraca = $ctrl->getNeracaData($period);

$neracaFlat = [
    'bank_bri' => $neraca['bank_bri'] ?? 0,
    'bank_bca' => $neraca['bank_bca'] ?? 0,
    'kas_cash' => $neraca['kas_cash'] ?? 0,
    'stock_value' => $neraca['stock_value'] ?? 0,
    'debt_iqos' => $neraca['debt_iqos'] ?? 0,
    'debt_payment_iqos' => 0,
    'total_assets' => $neraca['total_assets'] ?? 0,
    'total_liabilities' => $neraca['total_liabilities'] ?? 0,
    'inflow_pending_items' => $neraca['aktiva']['penerimaan_berjalan']['items'] ?? [],
    'selisih_bobi' => $neraca['aktiva']['rekonsiliasi']['items'][0]['amount'] ?? 0,
    'receivables_program_items' => $neraca['aktiva']['piutang_program']['items'] ?? [],
    'receivables_personnel_items' => $neraca['aktiva']['piutang_personil']['items'] ?? [],
    'initial_investment_asset' => $neraca['aktiva']['investasi_aset']['items'][0]['amount'] ?? 0,
    'other_assets_items' => $neraca['aktiva']['aset_lain']['items'] ?? [],
    'other_liabilities_items' => $neraca['pasiva']['kewajiban_lain']['items'] ?? [],
    'retained_earnings_items' => $neraca['pasiva']['ekuitas_laba']['items'] ?? [],
    'total_retained_earnings' => $neraca['total_equity'] ?? 0,
];

$report = array_merge($pnl, $neracaFlat);
echo json_encode($report);
PHP;

        $scriptFormatted = sprintf(
            $script,
            $iqosPathNormalized,
            $iqosPathNormalized,
            $iqosDb,
            $iqosDb,
            $iqosDb,
            $iqosPathNormalized,
            $iqosDb,
            $period
        );

        $tempFile = storage_path('temp_iqos_calc_'.time().'_'.mt_rand(1000, 9999).'.php');
        file_put_contents($tempFile, "<?php\n".$scriptFormatted);

        $phpBinary = PHP_BINARY ?: 'php';
        $output = $this->executeCommand(escapeshellarg($phpBinary).' '.escapeshellarg($tempFile).' 2>&1');
        @unlink($tempFile);

        if (! $output) {
            return null;
        }

        $data = json_decode($output);
        if (! $data || empty($data->period)) {
            return null;
        }

        return $data;
    }

    /**
     * Jalankan perintah CLI dengan dukungan fallback multi-eksekutor.
     */
    private function executeCommand(string $command): ?string
    {
        if (function_exists('shell_exec')) {
            $out = @\shell_exec($command);
            if ($out !== null && $out !== false && $out !== '') {
                return (string) $out;
            }
        }

        if (function_exists('exec')) {
            $lines = [];
            $status = 0;
            @\exec($command, $lines, $status);
            if (! empty($lines)) {
                return implode("\n", $lines);
            }
        }

        if (function_exists('proc_open')) {
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @\proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                if ($output) {
                    return (string) $output;
                }
            }
        }

        if (function_exists('popen')) {
            $handle = @\popen($command, 'r');
            if ($handle) {
                $output = '';
                while (! feof($handle)) {
                    $output .= fread($handle, 4096);
                }
                pclose($handle);
                if ($output) {
                    return $output;
                }
            }
        }

        return null;
    }
}
