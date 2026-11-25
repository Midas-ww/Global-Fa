<?php

namespace Tests\Feature;

use App\Models\GlobalFinancialRecord;
use App\Models\User;
use App\Services\GlobalFinance\ConsolidationReportService;
use App\Services\GlobalFinance\OnBoldSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnBoldSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpOnBoldConnection(): void
    {
        Config::set('database.connections.mysql_onbold', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('mysql_onbold');

        $schema = Schema::connection('mysql_onbold');

        $schema->create('balance_sheet_records', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->string('entity')->default('MEKAR JAYA MULTI INDO');
            $table->string('dist_name')->default('MJM ROKOK TEGAL');
            $table->string('account_code');
            $table->string('account_name');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $schema->create('cost_pnl_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_date');
            $table->string('month_name');
            $table->string('entity')->default('MEKAR JAYA MULTI INDO');
            $table->string('dist_name')->default('MJM ROKOK TEGAL');
            $table->string('account_code');
            $table->string('account_name');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $conn = DB::connection('mysql_onbold');

        // Seed exact October 2026 sample rows from Dash Onbold
        $conn->table('cost_pnl_records')->insert([
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '410.01.010', 'account_name' => 'PENJUALAN', 'category' => 'revenue',
                'sub_category' => 'penjualan_reguler', 'amount' => 1061116100.00, 'description' => 'PENJUALAN October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '410.01.020', 'account_name' => 'PENJUALAN-PT.CAKRA', 'category' => 'revenue',
                'sub_category' => 'penjualan_cakra', 'amount' => 1717397800.00, 'description' => 'PENJUALAN-PT.CAKRA October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '510.01.010', 'account_name' => 'HPP', 'category' => 'cogs',
                'sub_category' => 'hpp_reguler', 'amount' => 1018662123.00, 'description' => 'HPP October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '510.01.020', 'account_name' => 'HPP -PT.CAKRA', 'category' => 'cogs',
                'sub_category' => 'hpp_cakra', 'amount' => 1597242510.00, 'description' => 'HPP -PT.CAKRA October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '612.01.010', 'account_name' => 'GAJI KARYAWAN / DIREKSI', 'category' => 'opex',
                'sub_category' => 'karyawan', 'amount' => 80076367.00, 'description' => 'Total Opex October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '710.010.020', 'account_name' => 'PENDAPATAN BUNGA BANK', 'category' => 'other_income',
                'sub_category' => 'bunga_bank', 'amount' => 1015537.00, 'description' => 'PENDAPATAN BUNGA BANK October 2026',
            ],
            [
                'period_year' => 2026, 'period_month' => 10, 'period_date' => '2026-10-31', 'month_name' => 'October',
                'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '411.00.000', 'account_name' => 'RETUR PENJUALAN', 'category' => 'revenue',
                'sub_category' => 'retur_penjualan', 'amount' => 0.00, 'description' => 'Zero row should be skipped',
            ],
        ]);

        $conn->table('balance_sheet_records')->insert([
            [
                'period_date' => '2026-10-31', 'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '111.01.010', 'account_name' => 'KAS BESAR', 'category' => 'aktiva_lancar',
                'sub_category' => 'kas', 'amount' => 150451283.00, 'description' => 'KAS BESAR',
            ],
            [
                'period_date' => '2026-10-31', 'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '112.01.010', 'account_name' => 'BANK PERMATA', 'category' => 'aktiva_lancar',
                'sub_category' => 'bank', 'amount' => 1848727290.00, 'description' => 'BANK',
            ],
            [
                'period_date' => '2026-10-31', 'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '210.01.020', 'account_name' => 'HUTANG DAGANG-PT.CAKRA', 'category' => 'pasiva_hutang',
                'sub_category' => 'hutang_dagang', 'amount' => 1000000000.00, 'description' => 'HUTANG',
            ],
            [
                'period_date' => '2026-10-31', 'entity' => 'MEKAR JAYA MULTI INDO', 'dist_name' => 'MJM ROKOK TEGAL',
                'account_code' => '310.00.010', 'account_name' => 'LABA/(RUGI) TAHUN BERJALAN', 'category' => 'modal_ekuitas',
                'sub_category' => 'modal', 'amount' => 999178573.00, 'description' => 'MODAL',
            ],
        ]);
    }

    public function test_onbold_sync_service_ingests_pnl_and_balance_sheet_idempotently(): void
    {
        $this->setUpOnBoldConnection();

        $service = new OnBoldSyncService;
        $result = $service->sync();

        $this->assertEquals('onbold', $result['business_unit']);
        $this->assertEquals(['2026-10'], $result['synced_periods']);
        $this->assertEquals(10, $result['records_count']); // 6 PnL (1 zero skipped) + 4 BS

        $this->assertDatabaseHas('global_business_units', [
            'code' => 'onbold',
            'name' => 'On Bold',
        ]);

        $this->assertDatabaseHas('global_entities', [
            'code' => 'MJM_ROKOK_TEGAL',
            'name' => 'Mjm Rokok Tegal',
        ]);

        // Re-syncing must be idempotent without creating duplicate records
        $service->sync();
        $this->assertEquals(10, GlobalFinancialRecord::where('business_unit_code', 'onbold')->count());

        $reportService = app(ConsolidationReportService::class);
        $pnl = $reportService->getConsolidatedPnl('2026-10-01', 'onbold');

        $this->assertEquals(162609267.00, $pnl['summary']['gross_profit_current']);
        $this->assertEquals(83548437.00, $pnl['summary']['net_profit_current']);

        $bs = $reportService->getConsolidatedBalanceSheet('2026-10-01', 'onbold');
        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals(1999178573.00, $bs['assets']['total']);
    }

    public function test_authenticated_user_can_trigger_onbold_sync_from_controller(): void
    {
        $this->setUpOnBoldConnection();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('finance.sync.database'), [
            'target' => 'onbold',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('global_ingestion_logs', [
            'business_unit_code' => 'onbold',
            'source_type' => 'database_sync',
            'status' => 'success',
        ]);
    }

    public function test_sync_page_displays_onbold_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('finance.sync'));

        $response->assertOk();
        $response->assertSee('On Bold (PT Mekar Jaya Multi Indo)');
        $response->assertSee('dash_onbold_db');
        $response->assertSee('Sinkron On Bold');
    }
}
