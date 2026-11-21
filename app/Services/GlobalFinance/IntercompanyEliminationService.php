<?php

namespace App\Services\GlobalFinance;

use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIntercompanyElimination;
use App\Models\GlobalIntercompanyRule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class IntercompanyEliminationService
{
    /**
     * Inisialisasi aturan baku eliminasi intercompany jika belum tersedia.
     */
    public function seedDefaultRules(): void
    {
        $defaultRules = [
            [
                'name' => 'Eliminasi Hutang-Piutang Solar Ritase Xtrans di SPBU No. 44.511.12',
                'from_entity_code' => 'XTRANS',
                'to_entity_code' => 'SPBU_44',
                'category' => 'hutang_piutang_spbu',
                'source_report_type' => 'balance_sheet',
                'source_category' => 'liability',
                'account_name_pattern' => 'Hutang SPBU',
                'description_template' => 'Eliminasi Saldo Kewajiban Hutang SPBU Armada Xtrans terhadap SPBU No. 44.511.12',
                'is_active' => true,
            ],
            [
                'name' => 'Eliminasi Hutang Jangka Panjang Armada Xtrans kepada MJS (AQUA)',
                'from_entity_code' => 'XTRANS',
                'to_entity_code' => 'MJS',
                'category' => 'hutang_piutang_mjs',
                'source_report_type' => 'balance_sheet',
                'source_category' => 'liability',
                'account_name_pattern' => 'Hutang Jangka Panjang Kepada MJS',
                'description_template' => 'Eliminasi Pinjaman & Hutang Jangka Panjang Armada Xtrans kepada CV Mekar Jaya Sentosa (MJS)',
                'is_active' => true,
            ],
            [
                'name' => 'Eliminasi Hutang Angsuran Mobil Xtrans kepada MJS (AQUA)',
                'from_entity_code' => 'XTRANS',
                'to_entity_code' => 'MJS',
                'category' => 'angsuran_armada_mjs',
                'source_report_type' => 'balance_sheet',
                'source_category' => 'liability',
                'account_name_pattern' => 'Hutang Angsuran Mobil kepada MJS',
                'description_template' => 'Eliminasi Tagihan Angsuran Kepemilikan Armada Truk Xtrans kepada CV Mekar Jaya Sentosa (MJS)',
                'is_active' => true,
            ],
            [
                'name' => 'Eliminasi Hutang Biaya Operasional Xtrans kepada MJS (AQUA)',
                'from_entity_code' => 'XTRANS',
                'to_entity_code' => 'MJS',
                'category' => 'hutang_biaya_mjs',
                'source_report_type' => 'balance_sheet',
                'source_category' => 'liability',
                'account_name_pattern' => 'Hutang Biaya Kepada MJS',
                'description_template' => 'Eliminasi Saldo Hutang Biaya Operasional Kendaraan Xtrans kepada CV Mekar Jaya Sentosa (MJS)',
                'is_active' => true,
            ],
        ];

        foreach ($defaultRules as $rule) {
            GlobalIntercompanyRule::updateOrCreate(
                [
                    'from_entity_code' => $rule['from_entity_code'],
                    'to_entity_code' => $rule['to_entity_code'],
                    'category' => $rule['category'],
                    'account_name_pattern' => $rule['account_name_pattern'],
                ],
                $rule
            );
        }
    }

    /**
     * Sinkronisasi otomatis eliminasi antar entitas untuk periode tertentu.
     * Mengambil saldo akun aktual dari global_financial_records dan mengeksekusi eliminasi.
     *
     * @return Collection<int, GlobalIntercompanyElimination>
     */
    public function syncEliminationsForPeriod(string $period): Collection
    {
        $normalizedPeriod = Carbon::parse($period)->format('Y-m-01');

        if (GlobalIntercompanyRule::count() === 0) {
            $this->seedDefaultRules();
        }

        $activeRules = GlobalIntercompanyRule::where('is_active', true)->get();

        foreach ($activeRules as $rule) {
            $query = GlobalFinancialRecord::query()
                ->where(function ($q) use ($normalizedPeriod) {
                    $q->whereDate('period', $normalizedPeriod)
                        ->orWhere('period', 'LIKE', "{$normalizedPeriod}%");
                })
                ->where('entity_code', $rule->from_entity_code)
                ->where('report_type', $rule->source_report_type);

            if (! empty($rule->source_category)) {
                $query->where('category', $rule->source_category);
            }

            if (str_contains($rule->account_name_pattern, '%')) {
                $query->where('account_name', 'LIKE', $rule->account_name_pattern);
            } else {
                $query->where('account_name', $rule->account_name_pattern);
            }

            $amount = (float) $query->sum('amount');

            if ($amount > 0) {
                GlobalIntercompanyElimination::updateOrCreate(
                    [
                        'period' => $normalizedPeriod,
                        'from_entity_code' => $rule->from_entity_code,
                        'to_entity_code' => $rule->to_entity_code,
                        'category' => $rule->category,
                    ],
                    [
                        'description' => $rule->description_template ?: "Eliminasi {$rule->name}",
                        'amount' => $amount,
                        'is_active' => true,
                    ]
                );
            } else {
                // Jika periode ini akunnya bernilai 0 atau tidak ada, nonaktifkan eliminasi
                GlobalIntercompanyElimination::where(function ($q) use ($normalizedPeriod) {
                    $q->whereDate('period', $normalizedPeriod)
                        ->orWhere('period', 'LIKE', "{$normalizedPeriod}%");
                })
                    ->where('from_entity_code', $rule->from_entity_code)
                    ->where('to_entity_code', $rule->to_entity_code)
                    ->where('category', $rule->category)
                    ->update(['amount' => 0, 'is_active' => false]);
            }
        }

        return GlobalIntercompanyElimination::query()
            ->where(function ($q) use ($normalizedPeriod) {
                $q->whereDate('period', $normalizedPeriod)
                    ->orWhere('period', 'LIKE', "{$normalizedPeriod}%");
            })
            ->where('is_active', true)
            ->where('amount', '>', 0)
            ->get();
    }

    /**
     * Sinkronisasi seluruh periode finansial yang ada di database.
     *
     * @return array{periods_processed: int, total_eliminations_active: int}
     */
    public function syncAllPeriods(): array
    {
        if (GlobalIntercompanyRule::count() === 0) {
            $this->seedDefaultRules();
        }

        $periods = GlobalFinancialRecord::query()
            ->distinct()
            ->pluck('period')
            ->map(fn ($p) => Carbon::parse($p)->format('Y-m-01'))
            ->unique()
            ->values();

        foreach ($periods as $period) {
            $this->syncEliminationsForPeriod($period);
        }

        $totalActive = GlobalIntercompanyElimination::where('is_active', true)->where('amount', '>', 0)->count();

        return [
            'periods_processed' => $periods->count(),
            'total_eliminations_active' => $totalActive,
        ];
    }

    /**
     * Ambil seluruh aturan eliminasi aktif dan nonaktif.
     *
     * @return Collection<int, GlobalIntercompanyRule>
     */
    public function getRules(): Collection
    {
        if (GlobalIntercompanyRule::count() === 0) {
            $this->seedDefaultRules();
        }

        return GlobalIntercompanyRule::orderBy('id', 'asc')->get();
    }

    /**
     * Toggle status aktif aturan eliminasi.
     */
    public function toggleRule(int $ruleId): bool
    {
        $rule = GlobalIntercompanyRule::findOrFail($ruleId);
        $rule->is_active = ! $rule->is_active;
        $rule->save();

        // Re-sinkronisasi semua periode
        $this->syncAllPeriods();

        return $rule->is_active;
    }
}
