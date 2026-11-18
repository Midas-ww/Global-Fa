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

        $reports = $query->get();
        $syncedCount = 0;
        $periods = [];

        foreach ($reports as $rep) {
            $periodDate = Carbon::createFromFormat('Y-m', $rep->period)->startOfMonth()->format('Y-m-d');
            $periods[] = $rep->period;

            // Hapus data lama untuk periode & entitas ini agar idempoten
            GlobalFinancialRecord::query()
                ->where('entity_code', 'IQOS_RETAIL')
                ->where('period', $periodDate)
                ->delete();

            $records = [];
            $status = $rep->is_finalized ? 'final' : 'running';

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
                'amount' => (float) $rep->sales_regular,
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Promo items revenue
            $promoItems = json_decode($rep->sales_promo_items ?? '[]', true) ?: [];
            foreach ($promoItems as $item) {
                if (! empty($item['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'revenue',
                        'sub_category' => 'promo_sales',
                        'account_code' => '4002',
                        'account_name' => $item['name'] ?? 'Penjualan Promo Exclusive',
                        'amount' => (float) $item['amount'],
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
                'amount' => (float) $rep->cogs,
                'status' => $status,
                'source_type' => 'database_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // --- 3. PNL: OPEX ---
            $opexDetails = json_decode($rep->operating_expenses_detail ?? '[]', true) ?: [];
            if (! empty($opexDetails)) {
                foreach ($opexDetails as $idx => $exp) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'opex',
                        'sub_category' => $exp['category'] ?? 'Operasional Toko',
                        'account_code' => '60'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $exp['name'] ?? 'Beban Operasional',
                        'amount' => (float) ($exp['amount'] ?? 0),
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
                    'amount' => (float) $rep->operating_expenses,
                    'status' => $status,
                    'source_type' => 'database_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // --- 4. PNL: OTHER INCOME (PROGRAM PRINCIPAL) ---
            $programIncomes = json_decode($rep->program_incomes ?? '[]', true) ?: [];
            foreach ($programIncomes as $idx => $inc) {
                if (! empty($inc['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'pnl',
                        'category' => 'other_income',
                        'sub_category' => 'principal_support',
                        'account_code' => '90'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $inc['name'] ?? 'Pendapatan Program Principal',
                        'amount' => (float) $inc['amount'],
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
            $inflows = json_decode($rep->inflow_pending_items ?? '[]', true) ?: [];
            foreach ($inflows as $idx => $inf) {
                if (! empty($inf['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'cash_and_bank',
                        'account_code' => '111'.($idx + 1),
                        'account_name' => $inf['name'] ?? 'Inflow Pending',
                        'amount' => (float) $inf['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Selisih Kasir Bobi (Bisa bernilai negatif atau positif)
            if ((float) $rep->selisih_bobi != 0) {
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
            if ((float) $rep->stock_value != 0) {
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
            $recPrograms = json_decode($rep->receivables_program_items ?? '[]', true) ?: [];
            foreach ($recPrograms as $idx => $pItem) {
                if (! empty($pItem['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'accounts_receivable',
                        'account_code' => '12'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $pItem['name'] ?? 'Piutang Program',
                        'amount' => (float) $pItem['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Piutang Personel / Karyawan
            $recPersonnel = json_decode($rep->receivables_personnel_items ?? '[]', true) ?: [];
            foreach ($recPersonnel as $idx => $personItem) {
                if (! empty($personItem['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'accounts_receivable',
                        'account_code' => '125'.$idx,
                        'account_name' => 'Piutang: '.($personItem['name'] ?? 'Personel'),
                        'amount' => (float) $personItem['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // 5.4 Aktiva Tetap & Investasi Awal
            if ((float) $rep->initial_investment_asset != 0) {
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
            $otherAssets = json_decode($rep->other_assets_items ?? '[]', true) ?: [];
            foreach ($otherAssets as $idx => $oAsset) {
                if (! empty($oAsset['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'asset',
                        'sub_category' => 'other_assets',
                        'account_code' => '17'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $oAsset['name'] ?? 'Aset Lainnya',
                        'amount' => (float) $oAsset['amount'],
                        'status' => $status,
                        'source_type' => 'database_sync',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- 6. NERACA: LIABILITIES ---
            if ((float) $rep->debt_iqos != 0) {
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

            if ((float) $rep->debt_payment_iqos != 0) {
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
            $otherLiabs = json_decode($rep->other_liabilities_items ?? '[]', true) ?: [];
            foreach ($otherLiabs as $idx => $oLiab) {
                if (! empty($oLiab['amount'])) {
                    $records[] = [
                        'business_unit_code' => 'iqos',
                        'entity_code' => 'IQOS_RETAIL',
                        'period' => $periodDate,
                        'report_type' => 'balance_sheet',
                        'category' => 'liability',
                        'sub_category' => 'other_liabilities',
                        'account_code' => '22'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'account_name' => $oLiab['name'] ?? 'Kewajiban Lain / Titipan',
                        'amount' => (float) $oLiab['amount'],
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
            $retainedItems = json_decode($rep->retained_earnings_items ?? '[]', true) ?: [];
            if (! empty($retainedItems)) {
                foreach ($retainedItems as $idx => $rItem) {
                    if (isset($rItem['amount']) && (float) $rItem['amount'] != 0) {
                        $records[] = [
                            'business_unit_code' => 'iqos',
                            'entity_code' => 'IQOS_RETAIL',
                            'period' => $periodDate,
                            'report_type' => 'balance_sheet',
                            'category' => 'equity',
                            'sub_category' => 'retained_earnings',
                            'account_code' => '32'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                            'account_name' => $rItem['name'] ?? ('Laba Ditahan #'.($idx + 1)),
                            'amount' => (float) $rItem['amount'],
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
                    'amount' => (float) $rep->total_retained_earnings,
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
}
