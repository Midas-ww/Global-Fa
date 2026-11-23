<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpbuSyncService
{
    /**
     * Jalankan kalkulasi dan sinkronisasi laporan SPBU untuk satu atau semua periode.
     *
     * @param  string  $yearMonth  Format YYYY-MM (contoh: '2026-07') atau 'all' untuk semua periode.
     */
    public function sync(string $yearMonth = '2026-07'): array
    {
        if (strtolower($yearMonth) === 'all') {
            return $this->syncAll();
        }

        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');
        $periodDate = $startDate;

        $spbuPath = env('SPBU_PROJECT_PATH');
        if (! $spbuPath || ! file_exists($spbuPath.'/vendor/autoload.php')) {
            $fallbacks = [
                // Relatif dari root Global FA
                base_path('../API-SPBU'),
                base_path('../api-spbu'),
                base_path('../spbu'),
                base_path('../spbu-api'),
                base_path('../api_spbu'),
                base_path('../../API-SPBU'),
                base_path('../../api-spbu'),

                // aaPanel default root (/www/wwwroot/...)
                '/www/wwwroot/API-SPBU',
                '/www/wwwroot/api-spbu',
                '/www/wwwroot/spbu',
                '/www/wwwroot/api_spbu',
                '/www/wwwroot/spbu.mekarjayagroup.biz.id',
                '/www/wwwroot/api-spbu.mekarjayagroup.biz.id',
                '/www/wwwroot/api.spbu.mekarjayagroup.biz.id',
                '/www/wwwroot/spbu-api.mekarjayagroup.biz.id',
                '/www/wwwroot/spbu44.mekarjayagroup.biz.id',
                '/www/wwwroot/spbu-44.mekarjayagroup.biz.id',

                // Linux standard paths (/var/www/...)
                '/var/www/API-SPBU',
                '/var/www/api-spbu',
                '/var/www/spbu',
                '/var/www/api_spbu',
                '/var/www/spbu.mekarjayagroup.biz.id',
                '/var/www/api-spbu.mekarjayagroup.biz.id',
                '/var/www/html/API-SPBU',
                '/var/www/html/api-spbu',
                '/home/ubuntu/API-SPBU',
                '/home/ubuntu/api-spbu',

                // Local Windows
                'D:/Mekar Jaya/API-SPBU',
                'C:/Mekar Jaya/API-SPBU',
            ];

            foreach ($fallbacks as $fb) {
                if (file_exists($fb.'/vendor/autoload.php')) {
                    $spbuPath = $fb;
                    break;
                }
            }

            // Jika masih belum ketemu, lakukan auto-discovery di folder induk dan /www/wwwroot
            if (! $spbuPath) {
                $searchRoots = array_filter([
                    dirname(base_path()),
                    '/www/wwwroot',
                    '/var/www',
                ], 'is_dir');

                foreach ($searchRoots as $root) {
                    $subdirs = @glob($root.'/*', GLOB_ONLYDIR) ?: [];
                    foreach ($subdirs as $dir) {
                        if (file_exists($dir.'/vendor/autoload.php') && file_exists($dir.'/bootstrap/app.php')) {
                            $dirLower = strtolower(basename($dir));
                            if (str_contains($dirLower, 'spbu') || file_exists($dir.'/app/Services/PnlService.php')) {
                                $spbuPath = $dir;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $spbuPathNormalized = $spbuPath ? str_replace('\\', '/', $spbuPath) : 'D:/Mekar Jaya/API-SPBU';
        if (! file_exists($spbuPathNormalized.'/vendor/autoload.php')) {
            $existingDirs = [];
            if (is_dir('/www/wwwroot')) {
                $existingDirs = array_map('basename', @glob('/www/wwwroot/*', GLOB_ONLYDIR) ?: []);
            }
            $hint = ! empty($existingDirs)
                ? ' Folder website yang terdeteksi di /www/wwwroot: ['.implode(', ', $existingDirs).'].'
                : '';
            throw new \RuntimeException("Path project API-SPBU tidak ditemukan di server.{$hint} Harap atur 'SPBU_PROJECT_PATH' di file .env server.");
        }

        $spbuDb = config('database.connections.mysql_spbu.database', 'api_air');

        $script = "
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', '0');
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

        $phpBinary = 'php';
        if (PHP_BINARY && ! str_contains(PHP_BINARY, 'fpm')) {
            $phpBinary = PHP_BINARY;
        } else {
            // Pada environment Nginx / aaPanel PHP-FPM, PHP_BINARY merujuk ke sbin/php-fpm
            $fpmDir = dirname(PHP_BINARY);
            $candidate = dirname($fpmDir).'/bin/php';
            if (@file_exists($candidate)) {
                $phpBinary = $candidate;
            } elseif (@file_exists('/usr/bin/php')) {
                $phpBinary = '/usr/bin/php';
            }
        }

        $output = $this->executeCommand(escapeshellarg($phpBinary).' '.escapeshellarg($tempFile).' 2>&1');
        @unlink($tempFile);

        if (! $output) {
            throw new \RuntimeException('Gagal menjalankan kalkulasi SPBU via CLI.');
        }

        $data = json_decode($output, true);
        if (! $data || ! isset($data['pnl']) || ! isset($data['bs'])) {
            if (preg_match('/\{.*"pnl".*"bs".*\}/s', $output, $matches)) {
                $data = json_decode($matches[0], true);
            }
        }

        if (! $data || ! isset($data['pnl']) || ! isset($data['bs'])) {
            $cleanSnippet = trim(strip_tags($output));
            $cleanSnippet = mb_substr($cleanSnippet, 0, 300);
            throw new \RuntimeException("Output kalkulasi SPBU tidak valid: [{$cleanSnippet}]");
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
        $sumProductRev = 0.0;
        if (! empty($pnl['revenue']['products'])) {
            foreach ($pnl['revenue']['products'] as $idx => $prod) {
                $pAmt = (float) ($prod['net_amount'] ?? $prod['amount'] ?? 0);
                $sumProductRev += $pAmt;
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'revenue',
                    'sub_category' => 'fuel_sales',
                    'account_code' => '40'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'Penjualan '.($prod['name'] ?? 'BBM'),
                    'amount' => $pAmt,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'notes' => 'Volume: '.number_format((float) ($prod['volume'] ?? 0), 0, ',', '.').' L',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Defensive Fallback: Bila ada selisih antara total omzet dan rincian produk
            $expectedRev = (float) ($pnl['revenue']['total'] ?? 0);
            $diffRev = round($expectedRev - $sumProductRev, 2);
            if (abs($diffRev) > 1.0) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'pnl',
                    'category' => 'revenue',
                    'sub_category' => 'fuel_sales',
                    'account_code' => '4090',
                    'account_name' => 'Penyesuaian Penjualan BBM Belum Terklasifikasi',
                    'amount' => $diffRev,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'notes' => 'Otomatis dibuat untuk menjaga konsistensi total pendapatan dengan sistem SPBU',
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

        // Losis & Tera BBM (Dipetakan ke pos Beban Operasional / Susut agar COGS & Gross Profit identik dengan sistem SPBU)
        if (! empty($pnl['cost_of_goods_sold']['bbm_losis'])) {
            $records[] = [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => 'opex',
                'sub_category' => 'susut_dan_losis',
                'account_code' => '6098',
                'account_name' => 'Biaya Losses BBM (Penguapan & Susut)',
                'amount' => (float) $pnl['cost_of_goods_sold']['bbm_losis'],
                'status' => $status,
                'source_type' => 'database_sync',
                'notes' => 'Tersinkronisasi dari pos Losis Sistem SPBU',
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
                'category' => 'opex',
                'sub_category' => 'susut_dan_losis',
                'account_code' => '6099',
                'account_name' => 'Biaya Tera BBM (Pengujian Takaran Dispenser)',
                'amount' => (float) $pnl['cost_of_goods_sold']['bbm_tera'],
                'status' => $status,
                'source_type' => 'database_sync',
                'notes' => 'Tersinkronisasi dari pos Tera Sistem SPBU',
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

        // --- 4. NERACA: AKTIVA (DIRINCI SESUAI DATA RIIL SISTEM SPBU) ---
        $summary = $bs['summary'] ?? [];

        // 4.1 Kas & Setara Kas
        if (! empty($bs['aktiva']['kas']['items'])) {
            foreach ($bs['aktiva']['kas']['items'] as $idx => $kItem) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'cash_and_bank',
                    'account_code' => '11'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => trim($kItem['name'] ?? 'Kas & Bank SPBU'),
                    'amount' => (float) ($kItem['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
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
        }

        // 4.2 Persediaan Stok BBM & Pelumas
        if (! empty($bs['aktiva']['stok']['items'])) {
            foreach ($bs['aktiva']['stok']['items'] as $idx => $sItem) {
                $notes = isset($sItem['qty']) && (float) $sItem['qty'] > 0
                    ? 'Volume: '.number_format((float) $sItem['qty'], 0, ',', '.').' L'
                    : null;
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'inventory',
                    'account_code' => '13'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => 'Stok '.trim($sItem['name'] ?? 'BBM'),
                    'amount' => (float) ($sItem['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
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
        }

        // 4.3 Piutang Usaha & Pertamina
        if (! empty($bs['aktiva']['piutang']['items'])) {
            foreach ($bs['aktiva']['piutang']['items'] as $idx => $pItem) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'accounts_receivable',
                    'account_code' => '12'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => trim($pItem['name'] ?? 'Piutang Usaha'),
                    'amount' => (float) ($pItem['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
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
        }

        // 4.4 Aktiva Tetap
        if (! empty($bs['aktiva']['aktiva_tetap']['items'])) {
            foreach ($bs['aktiva']['aktiva_tetap']['items'] as $idx => $aItem) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'asset',
                    'sub_category' => 'fixed_assets',
                    'account_code' => '16'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => trim($aItem['name'] ?? 'Aktiva Tetap'),
                    'amount' => (float) ($aItem['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
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
        }

        // --- 5. NERACA: PASIVA (DIRINCI SESUAI DATA RIIL SISTEM SPBU) ---
        // 5.1 Kewajiban / Hutang
        $hasHutangItems = ! empty($bs['pasiva']['hutang']['operasional']) || ! empty($bs['pasiva']['hutang']['dp']);
        if ($hasHutangItems) {
            $hIdx = 0;
            if (! empty($bs['pasiva']['hutang']['operasional'])) {
                foreach ($bs['pasiva']['hutang']['operasional'] as $hItem) {
                    $hIdx++;
                    $records[] = [
                        'business_unit_code' => 'spbu',
                        'entity_code' => 'SPBU_44',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'liability',
                        'sub_category' => 'accounts_payable',
                        'account_code' => '21'.str_pad((string) $hIdx, 2, '0', STR_PAD_LEFT),
                        'account_name' => 'Hutang '.trim($hItem['name'] ?? 'Operasional'),
                        'amount' => (float) ($hItem['amount'] ?? 0),
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (! empty($bs['pasiva']['hutang']['dp'])) {
                foreach ($bs['pasiva']['hutang']['dp'] as $dItem) {
                    $hIdx++;
                    $records[] = [
                        'business_unit_code' => 'spbu',
                        'entity_code' => 'SPBU_44',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'liability',
                        'sub_category' => 'customer_deposits',
                        'account_code' => '22'.str_pad((string) $hIdx, 2, '0', STR_PAD_LEFT),
                        'account_name' => 'DP / Uang Muka '.trim($dItem['name'] ?? 'Customer'),
                        'amount' => (float) ($dItem['amount'] ?? 0),
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        } else {
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
        }

        // 5.2 Modal & Ekuitas
        if (! empty($bs['pasiva']['modal']['items'])) {
            foreach ($bs['pasiva']['modal']['items'] as $idx => $mItem) {
                $records[] = [
                    'business_unit_code' => 'spbu',
                    'entity_code' => 'SPBU_44',
                    'period' => $periodDate,
                    'report_type' => 'balance_sheet',
                    'category' => 'equity',
                    'sub_category' => 'retained_earnings',
                    'account_code' => '31'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'account_name' => trim($mItem['name'] ?? 'Modal SPBU'),
                    'amount' => (float) ($mItem['amount'] ?? 0),
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
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
        }

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

    /**
     * Sinkronisasikan seluruh periode yang tersedia di database SPBU.
     */
    public function syncAll(): array
    {
        $months = [];
        try {
            $months = DB::connection('mysql_spbu')
                ->table('shift_sales')
                ->selectRaw('DISTINCT SUBSTRING(posted_at, 1, 7) as ym')
                ->whereNotNull('posted_at')
                ->where('posted_at', '!=', '')
                ->orderBy('ym')
                ->pluck('ym')
                ->toArray();
        } catch (\Throwable) {
        }

        if (empty($months)) {
            $months = ['2026-07', '2026-08', '2026-09', '2026-10', '2026-11'];
        }

        $results = [];
        $totalRecords = 0;
        foreach ($months as $m) {
            try {
                $res = $this->sync($m);
                $results[$m] = $res;
                $totalRecords += ($res['records_count'] ?? 0);
            } catch (\Throwable $e) {
                $results[$m] = ['error' => $e->getMessage()];
            }
        }

        return [
            'success' => true,
            'business_unit' => 'spbu',
            'periods_synced' => count($results),
            'months' => array_keys($results),
            'total_records' => $totalRecords,
            'details' => $results,
        ];
    }

    /**
     * Jalankan perintah CLI dengan dukungan fallback multi-eksekutor
     * untuk kompatibilitas penuh dengan lingkungan hosting/aaPanel.
     */
    private function executeCommand(string $command): string
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

        throw new \RuntimeException(
            'Fungsi CLI (shell_exec / exec / proc_open) dinonaktifkan di PHP server (disable_functions). '.
            "Di aaPanel: buka App Store -> Installed -> PHP-8.x -> Setting -> Disabled functions, lalu hapus 'shell_exec' dan 'exec'."
        );
    }
}
