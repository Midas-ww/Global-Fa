<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalFinancialRecord;
use App\Models\User;
use Database\Seeders\GlobalFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordInlineEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GlobalFinanceSeeder::class);
    }

    public function test_business_units_order_places_aqua_first_and_ccod_abc_at_end(): void
    {
        $units = GlobalBusinessUnit::orderBy('sort_order')->pluck('code')->toArray();

        $this->assertEquals('aqua', $units[0], 'AQUA must be in 1st position');
        $this->assertEquals('fmcg', $units[1], 'Unilever (fmcg) must be in 2nd position');

        $total = count($units);
        $this->assertEquals('ccod', $units[$total - 2], 'CCOD must be in second-to-last position');
        $this->assertEquals('abc', $units[$total - 1], 'ABC must be in last position');
    }

    public function test_authorized_user_can_edit_imported_excel_record(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@mekarjaya.com'],
            ['name' => 'Admin Holding', 'password' => bcrypt('password')]
        );

        $record = GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'category' => 'operating_expense',
            'account_code' => '6000-02-031',
            'account_name' => 'Biaya telepon, data seluler & internet',
            'amount' => 7536746,
            'source_type' => 'excel_upload',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson(route('finance.records.save'), [
            'id' => $record->id,
            'period' => '2026-10-01',
            'business_unit_code' => 'fmcg',
            'report_type' => 'pnl',
            'category' => 'operating_expense',
            'account_code' => '6000-02-031',
            'account_name' => 'Biaya telepon, data seluler & internet (Revisi)',
            'amount' => 8000000,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $record->refresh();
        $this->assertEquals(8000000, (float) $record->amount);
        $this->assertEquals('Biaya telepon, data seluler & internet (Revisi)', $record->account_name);
    }

    public function test_authorized_user_can_delete_imported_excel_record(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@mekarjaya.com'],
            ['name' => 'Admin Holding', 'password' => bcrypt('password')]
        );

        $record = GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-10-01',
            'report_type' => 'pnl',
            'category' => 'operating_expense',
            'account_code' => '6000-02-035',
            'account_name' => 'Biaya Klaim Dummy',
            'amount' => 500000,
            'source_type' => 'excel_upload',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('finance.records.delete', ['id' => $record->id]));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('global_financial_records', [
            'id' => $record->id,
        ]);
    }
}
