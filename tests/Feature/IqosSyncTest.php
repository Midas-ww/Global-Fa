<?php

namespace Tests\Feature;

use App\Services\GlobalFinance\IqosSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IqosSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_iqos_sync_stores_official_debt_and_payment_debt_separately_and_receivables(): void
    {
        Config::set('database.connections.mysql_iqos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('mysql_iqos');

        Schema::connection('mysql_iqos')->create('iqos_monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->boolean('is_finalized')->default(true);
            $table->decimal('sales_regular', 18, 2)->default(0);
            $table->text('sales_promo_items')->nullable();
            $table->decimal('cogs', 18, 2)->default(0);
            $table->decimal('operating_expenses', 18, 2)->default(0);
            $table->text('operating_expenses_detail')->nullable();
            $table->text('program_incomes')->nullable();
            $table->decimal('bank_bri', 18, 2)->default(0);
            $table->decimal('bank_bca', 18, 2)->default(0);
            $table->decimal('kas_cash', 18, 2)->default(0);
            $table->text('inflow_pending_items')->nullable();
            $table->decimal('selisih_bobi', 18, 2)->default(0);
            $table->decimal('stock_value', 18, 2)->default(0);
            $table->text('receivables_program_items')->nullable();
            $table->text('receivables_personnel_items')->nullable();
            $table->decimal('initial_investment_asset', 18, 2)->default(0);
            $table->text('other_assets_items')->nullable();
            $table->decimal('debt_iqos', 18, 2)->default(0);
            $table->decimal('debt_payment_iqos', 18, 2)->default(0);
            $table->text('other_liabilities_items')->nullable();
            $table->decimal('initial_investment_equity', 18, 2)->default(0);
            $table->text('retained_earnings_items')->nullable();
            $table->decimal('total_retained_earnings', 18, 2)->default(0);
        });

        DB::connection('mysql_iqos')->table('iqos_monthly_reports')->insert([
            'period' => '2026-10',
            'is_finalized' => true,
            'sales_regular' => 86445390,
            'cogs' => 90765276.35,
            'operating_expenses' => 284450.67,
            'bank_bri' => 76132195,
            'stock_value' => 40815995.49,
            'receivables_program_items' => json_encode([
                ['name' => 'Sisa Piutang Program IQOS Berjalan', 'amount' => 17300000],
                ['name' => 'Back Margin Consumable', 'amount' => 450000],
                ['name' => 'New Member', 'amount' => 9400000],
            ]),
            'debt_iqos' => 75000000,
            'debt_payment_iqos' => 40966518,
            'total_retained_earnings' => 72761172.49,
        ]);

        $res = (new IqosSyncService)->sync('2026-10');

        $this->assertTrue($res['success']);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'iqos',
            'period' => '2026-10-01',
            'account_code' => '2101',
            'account_name' => 'Hutang IQOS (Tagihan Official)',
            'amount' => 75000000,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'iqos',
            'period' => '2026-10-01',
            'account_code' => '2102',
            'account_name' => 'Hutang Payment ke IQOS',
            'amount' => 40966518,
        ]);
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'iqos',
            'period' => '2026-10-01',
            'account_name' => 'Back Margin Consumable',
            'amount' => 450000,
        ]);
    }
}
