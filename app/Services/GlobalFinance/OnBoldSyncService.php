<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnBoldSyncService
{
    /**
     * Sinkronisasi data Cost P&L dan Balance Sheet dari database Dash Onbold (`dash_onbold_db`).
     *
     * @param  string|null  $targetPeriod  Format YYYY-MM (opsional), atau null/'all' untuk seluruh periode.
     * @return array<string, mixed>
     */
    public function sync(?string $targetPeriod = null): array
    {
        $conn = DB::connection('mysql_onbold');

        $pnlQuery = $conn->table('cost_pnl_records')->orderBy('period_date')->orderBy('id');
        $bsQuery = $conn->table('balance_sheet_records')->orderBy('period_date')->orderBy('id');

        if ($targetPeriod && strtolower($targetPeriod) !== 'all') {
            $startOfMonth = Carbon::createFromFormat('Y-m', $targetPeriod)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromFormat('Y-m', $targetPeriod)->endOfMonth()->format('Y-m-d');
            $pnlQuery->whereBetween('period_date', [$startOfMonth, $endOfMonth]);
            $bsQuery->whereBetween('period_date', [$startOfMonth, $endOfMonth]);
        }

        $pnlRows = $pnlQuery->get();
        $bsRows = $bsQuery->get();

        // Pastikan Unit Bisnis On Bold terdaftar
        $buOnbold = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'onbold'],
            [
                'name' => 'On Bold',
                'description' => 'PT Mekar Jaya Multi Indo - Distributor Rokok (MJM Rokok) via Dash Onbold.',
                'icon' => 'package',
                'color' => '#7C3AED',
                'data_source' => 'database',
                'sort_order' => 8,
                'is_active' => true,
            ]
        );

        // Kelompokkan data berdasarkan periode (YYYY-MM-01) dan dist_name
        $groupedRecords = [];
        $discoveredEntities = [];

        foreach ($pnlRows as $row) {
            $amount = (float) ($row->amount ?? 0);
            if (abs($amount) < 0.01) {
                continue;
            }

            $periodDate = Carbon::parse($row->period_date)->startOfMonth()->format('Y-m-d');
            $distName = trim((string) ($row->dist_name ?? 'MJM ROKOK TEGAL')) ?: 'MJM ROKOK TEGAL';
            $entityCode = $this->normalizeEntityCode($distName);
            $discoveredEntities[$entityCode] = $distName;

            $category = $this->mapPnlCategory((string) ($row->category ?? ''));

            $groupedRecords[$periodDate][$entityCode][] = [
                'business_unit_code' => 'onbold',
                'entity_code' => $entityCode,
                'period' => $periodDate,
                'report_type' => 'pnl',
                'category' => $category,
                'sub_category' => $row->sub_category ?: null,
                'account_code' => $row->account_code ?: null,
                'account_name' => trim((string) ($row->account_name ?? '-')),
                'amount' => $amount,
                'status' => $this->resolveStatus($periodDate),
                'source_type' => 'database_sync',
                'notes' => $row->description ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($bsRows as $row) {
            $amount = (float) ($row->amount ?? 0);
            if (abs($amount) < 0.01) {
                continue;
            }

            $periodDate = Carbon::parse($row->period_date)->startOfMonth()->format('Y-m-d');
            $distName = trim((string) ($row->dist_name ?? 'MJM ROKOK TEGAL')) ?: 'MJM ROKOK TEGAL';
            $entityCode = $this->normalizeEntityCode($distName);
            $discoveredEntities[$entityCode] = $distName;

            $category = $this->mapBalanceSheetCategory((string) ($row->category ?? ''));

            $groupedRecords[$periodDate][$entityCode][] = [
                'business_unit_code' => 'onbold',
                'entity_code' => $entityCode,
                'period' => $periodDate,
                'report_type' => 'balance_sheet',
                'category' => $category,
                'sub_category' => $row->sub_category ?: null,
                'account_code' => $row->account_code ?: null,
                'account_name' => trim((string) ($row->account_name ?? '-')),
                'amount' => $amount,
                'status' => $this->resolveStatus($periodDate),
                'source_type' => 'database_sync',
                'notes' => $row->description ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Daftarkan entitas cabang yang ditemukan dari database On Bold
        $sortOrder = 1;
        foreach ($discoveredEntities as $entityCode => $distName) {
            GlobalEntity::updateOrCreate(
                ['code' => $entityCode],
                [
                    'business_unit_id' => $buOnbold->id,
                    'name' => ucwords(strtolower($distName)),
                    'location' => str_contains(strtoupper($distName), 'TEGAL') ? 'Tegal' : null,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]
            );
        }

        // Simpan secara idempoten per periode & entitas
        $syncedPeriods = [];
        $totalInserted = 0;

        DB::transaction(function () use ($groupedRecords, &$syncedPeriods, &$totalInserted) {
            foreach ($groupedRecords as $periodDate => $byEntity) {
                $syncedPeriods[] = substr($periodDate, 0, 7);

                foreach ($byEntity as $entityCode => $records) {
                    GlobalFinancialRecord::query()
                        ->where('business_unit_code', 'onbold')
                        ->where('entity_code', $entityCode)
                        ->where('period', $periodDate)
                        ->delete();

                    foreach (array_chunk($records, 200) as $chunk) {
                        GlobalFinancialRecord::insert($chunk);
                    }

                    $totalInserted += count($records);
                }
            }
        });

        sort($syncedPeriods);

        return [
            'business_unit' => 'onbold',
            'synced_periods' => array_values(array_unique($syncedPeriods)),
            'count' => count(array_unique($syncedPeriods)),
            'records_count' => $totalInserted,
            'entities' => array_keys($discoveredEntities),
        ];
    }

    /**
     * Normalisasi nama distributor On Bold menjadi entity_code unik maksimal 50 karakter.
     */
    protected function normalizeEntityCode(string $distName): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', trim($distName)));
        $slug = trim($slug, '_');

        return substr($slug ?: 'ONBOLD_MAIN', 0, 50);
    }

    /**
     * Mapping kategori P&L dari `cost_pnl_records` On Bold ke standar Global FA.
     */
    protected function mapPnlCategory(string $rawCategory): string
    {
        $cat = strtolower(trim($rawCategory));

        return match ($cat) {
            'revenue', 'pendapatan' => 'revenue',
            'cogs', 'hpp' => 'cogs',
            'opex', 'beban', 'beban_operasional' => 'opex',
            'other_income', 'pendapatan_lain' => 'other_income',
            'other_expense', 'beban_lain' => 'other_expense',
            default => 'opex',
        };
    }

    /**
     * Mapping kategori Neraca dari `balance_sheet_records` On Bold ke standar Global FA.
     */
    protected function mapBalanceSheetCategory(string $rawCategory): string
    {
        $cat = strtolower(trim($rawCategory));

        if (str_contains($cat, 'aktiva') || str_contains($cat, 'asset')) {
            return 'asset';
        }
        if (str_contains($cat, 'hutang') || str_contains($cat, 'liability') || str_contains($cat, 'kewajiban')) {
            return 'liability';
        }
        if (str_contains($cat, 'modal') || str_contains($cat, 'ekuitas') || str_contains($cat, 'equity')) {
            return 'equity';
        }

        return 'asset';
    }

    /**
     * Tentukan status periode ('running' untuk bulan kalender berjalan, 'final' untuk bulan lampau).
     */
    protected function resolveStatus(string $periodDate): string
    {
        return $periodDate >= now()->startOfMonth()->format('Y-m-d') ? 'running' : 'final';
    }
}
