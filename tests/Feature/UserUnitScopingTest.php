<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIngestionLog;
use App\Models\User;
use App\Models\UserBusinessUnit;
use App\Services\GlobalFinance\ConsolidationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserUnitScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $asmara;

    protected User $tasya;

    protected function setUp(): void
    {
        parent::setUp();

        GlobalBusinessUnit::firstOrCreate(
            ['code' => 'fmcg'],
            [
                'name' => 'Unilever',
                'data_source' => 'excel',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
        GlobalBusinessUnit::firstOrCreate(
            ['code' => 'spbu'],
            [
                'name' => 'SPBU',
                'data_source' => 'database',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );
        GlobalBusinessUnit::firstOrCreate(
            ['code' => 'onbold'],
            [
                'name' => 'On Bold',
                'data_source' => 'database',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );
        GlobalBusinessUnit::firstOrCreate(
            ['code' => 'cocacola'],
            [
                'name' => 'Coca-Cola',
                'data_source' => 'excel',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        // 1. Unrestricted Admin
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@mekarjaya.com'],
            ['name' => 'Admin Holding', 'password' => bcrypt('password')]
        );

        // 2. Asmara (Unilever / fmcg only)
        $this->asmara = User::firstOrCreate(
            ['email' => 'asmarawildha@gmail.com'],
            ['name' => 'Asmara Wildha', 'password' => bcrypt('password')]
        );
        UserBusinessUnit::updateOrCreate(
            ['user_id' => $this->asmara->id, 'business_unit_code' => 'fmcg'],
            ['can_edit' => true]
        );

        // 3. Tasya (On Bold - database & Coca-Cola - excel)
        $this->tasya = User::firstOrCreate(
            ['email' => 'tasya.laisafi1@mekar-jaya.com'],
            ['name' => 'Tasya Laisafi', 'password' => bcrypt('password')]
        );
        UserBusinessUnit::updateOrCreate(
            ['user_id' => $this->tasya->id, 'business_unit_code' => 'onbold'],
            ['can_edit' => true]
        );
        UserBusinessUnit::updateOrCreate(
            ['user_id' => $this->tasya->id, 'business_unit_code' => 'cocacola'],
            ['can_edit' => true]
        );
    }

    public function test_unrestricted_user_has_null_accessible_codes_and_can_access_all(): void
    {
        $this->assertTrue($this->admin->isUnrestrictedFinance());
        $this->assertNull($this->admin->accessibleBusinessUnitCodes());
        $this->assertTrue($this->admin->canAccessBusinessUnit('fmcg'));
        $this->assertTrue($this->admin->canAccessBusinessUnit('spbu'));
        $this->assertTrue($this->admin->canAccessBusinessUnit('onbold'));
    }

    public function test_scoped_user_only_has_assigned_business_unit_codes(): void
    {
        $this->assertFalse($this->asmara->isUnrestrictedFinance());
        $codes = $this->asmara->accessibleBusinessUnitCodes();
        $this->assertEquals(['fmcg'], $codes);

        $this->assertTrue($this->asmara->canAccessBusinessUnit('fmcg'));
        $this->assertFalse($this->asmara->canAccessBusinessUnit('spbu'));
        $this->assertFalse($this->asmara->canAccessBusinessUnit('onbold'));
    }

    public function test_database_units_are_not_editable_even_if_assigned(): void
    {
        // On Bold has data_source = database -> cannot be edited manually
        $this->assertTrue($this->tasya->canAccessBusinessUnit('onbold'));
        $this->assertFalse($this->tasya->canEditBusinessUnit('onbold'));

        // Coca-Cola has data_source = excel -> can be edited
        $this->assertTrue($this->tasya->canAccessBusinessUnit('cocacola'));
        $this->assertTrue($this->tasya->canEditBusinessUnit('cocacola'));
    }

    public function test_scoped_user_is_restricted_in_pnl_view(): void
    {
        $response = $this->actingAs($this->asmara)->get(route('finance.pnl', ['business_unit' => 'spbu']));

        $response->assertOk();
        // Since asmara is restricted, requested 'spbu' should fall back to her allowed unit 'fmcg'
        $response->assertViewHas('selectedBu', 'fmcg');
        $response->assertViewHas('canEdit', true);
        $response->assertViewHas('isUnrestricted', false);
    }

    public function test_scoped_user_redirected_from_dashboard_to_pnl(): void
    {
        $response = $this->actingAs($this->asmara)->get(route('finance.dashboard'));

        $response->assertRedirect(route('finance.pnl', ['business_unit' => 'fmcg']));
    }

    public function test_scoped_user_cannot_access_master_units(): void
    {
        $response = $this->actingAs($this->asmara)->get(route('finance.master.units'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_master_units(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.master.units'));

        $response->assertOk();
    }

    public function test_saving_financial_record_works_for_editable_excel_unit(): void
    {
        $response = $this->actingAs($this->asmara)->post(route('finance.records.save'), [
            'business_unit_code' => 'fmcg',
            'period' => '2026-07-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Pendapatan Tambahan Manual Test',
            'account_code' => '4999',
            'amount' => 5000000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('global_financial_records', [
            'business_unit_code' => 'fmcg',
            'report_type' => 'pnl',
            'account_name' => 'Pendapatan Tambahan Manual Test',
            'amount' => 5000000,
            'source_type' => 'manual',
        ]);
    }

    public function test_saving_financial_record_is_forbidden_for_database_unit(): void
    {
        $response = $this->actingAs($this->tasya)->post(route('finance.records.save'), [
            'business_unit_code' => 'onbold',
            'period' => '2026-07-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Akun Ilegal Onbold',
            'amount' => 1000000,
        ]);

        // Should be forbidden or redirect with error
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403]));
        $this->assertDatabaseMissing('global_financial_records', [
            'account_name' => 'Akun Ilegal Onbold',
        ]);
    }

    public function test_adri_and_imel_cannot_access_sync_menu_and_actions(): void
    {
        $adri = User::firstOrCreate(
            ['email' => 'adrian.devianto@mekar-jaya.com'],
            ['name' => 'Adrian Devianto', 'password' => bcrypt('password')]
        );
        $imel = User::firstOrCreate(
            ['email' => 'imelda.daniel@mekar-jaya.com'],
            ['name' => 'Imelda Daniel', 'password' => bcrypt('password')]
        );

        $this->assertFalse($adri->canAccessSyncMenu());
        $this->assertFalse($imel->canAccessSyncMenu());

        // Both get 403 when trying to access /sync
        $this->actingAs($adri)->get(route('finance.sync'))->assertForbidden();
        $this->actingAs($imel)->get(route('finance.sync'))->assertForbidden();

        // Pak Adri cannot trigger database sync
        $this->actingAs($adri)->post(route('finance.sync.database'), ['target' => 'all'])->assertForbidden();

        // Cik Imel cannot trigger excel upload
        $this->actingAs($imel)->post(route('finance.sync.excel'), ['type' => 'fmcg'])->assertForbidden();
    }

    public function test_sync_view_only_shows_assigned_unit_ingestion_logs_for_staff(): void
    {
        GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'unilever_pnl_test.xlsx',
            'records_count' => 120,
            'status' => 'success',
        ]);

        GlobalIngestionLog::create([
            'business_unit_code' => 'spbu',
            'source_type' => 'database_sync',
            'filename' => 'api_air.shift_sales',
            'records_count' => 85,
            'status' => 'success',
        ]);

        GlobalIngestionLog::create([
            'business_unit_code' => 'onbold',
            'source_type' => 'database_sync',
            'filename' => 'dash_onbold_db.records',
            'records_count' => 50,
            'status' => 'success',
        ]);

        // Asmara is assigned to FMCG only
        $response = $this->actingAs($this->asmara)->get(route('finance.sync'));

        $response->assertOk();
        $response->assertSee('unilever_pnl_test.xlsx');
        $response->assertDontSee('api_air.shift_sales');
        $response->assertDontSee('dash_onbold_db.records');

        // Tasya is assigned to On Bold and Coca-Cola
        $responseTasya = $this->actingAs($this->tasya)->get(route('finance.sync'));

        $responseTasya->assertOk();
        $responseTasya->assertSee('dash_onbold_db.records');
        $responseTasya->assertDontSee('unilever_pnl_test.xlsx');
        $responseTasya->assertDontSee('api_air.shift_sales');
    }

    public function test_staff_cannot_delete_ingestion_log_of_unassigned_unit(): void
    {
        $spbuLog = GlobalIngestionLog::create([
            'business_unit_code' => 'spbu',
            'source_type' => 'database_sync',
            'filename' => 'spbu_test_log.xlsx',
            'records_count' => 10,
            'status' => 'success',
        ]);

        // Asmara (FMCG) tries to delete SPBU log -> Forbidden 403
        $response = $this->actingAs($this->asmara)->delete(route('finance.sync.delete-log', ['id' => $spbuLog->id]));
        $response->assertForbidden();

        $this->assertDatabaseHas('global_ingestion_logs', ['id' => $spbuLog->id]);

        // Fransiska (SPBU) tries to delete SPBU log -> Success
        $fransiska = User::firstOrCreate(
            ['email' => 'fransiskalia21@gmail.com'],
            ['name' => 'Fransiska Lia', 'password' => bcrypt('password')]
        );
        UserBusinessUnit::updateOrCreate(
            ['user_id' => $fransiska->id, 'business_unit_code' => 'spbu'],
            ['can_edit' => true]
        );

        $responseFransiska = $this->actingAs($fransiska)->delete(route('finance.sync.delete-log', ['id' => $spbuLog->id]));
        $responseFransiska->assertRedirect();

        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $spbuLog->id]);
    }

    public function test_empty_period_balance_sheet_renders_account_skeleton_with_zero_amounts(): void
    {
        // 1. Buat akun historis di periode masa lalu (2026-05-01)
        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'FMCG',
            'period' => '2026-05-01',
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'account_code' => '1101',
            'account_name' => 'Kas Operasional Holding FMCG',
            'amount' => 50000000,
            'source_type' => 'excel_upload',
        ]);
        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'FMCG',
            'period' => '2026-05-01',
            'report_type' => 'balance_sheet',
            'category' => 'equity',
            'account_code' => '3101',
            'account_name' => 'Modal Saham FMCG',
            'amount' => 50000000,
            'source_type' => 'excel_upload',
        ]);

        // 2. Akses periode kosong yang belum ada data sama sekali (2026-09-01)
        $service = app(ConsolidationReportService::class);
        $bsData = $service->getConsolidatedBalanceSheet('2026-09-01', 'fmcg');

        $this->assertTrue($bsData['is_skeleton']);
        $this->assertFalse($bsData['has_official_data']);
        $this->assertFalse($bsData['has_manual_data']);

        // Akun historis tetap muncul di skeleton dengan nominal 0
        $kasItem = $bsData['assets']['items']->firstWhere('account_name', 'Kas Operasional Holding FMCG');
        $this->assertNotNull($kasItem);
        $this->assertEquals(0, $kasItem->amount);
        $this->assertTrue($kasItem->is_skeleton);
        $this->assertNull($kasItem->id);

        // Halaman web neraca menampilkan badge template / skeleton dan akun tersebut
        $response = $this->actingAs($this->asmara)->get(route('finance.balance-sheet', [
            'period' => '2026-09-01',
            'business_unit' => 'fmcg',
        ]));
        $response->assertOk();
        $response->assertSee('Draft Manual / Template');
        $response->assertSee('Kas Operasional Holding FMCG');
    }

    public function test_authorized_user_can_edit_or_delete_uploaded_records(): void
    {
        $officialRecord = GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'FMCG',
            'period' => '2026-07-01',
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'account_code' => '1101',
            'account_name' => 'Kas Resmi Upload Excel',
            'amount' => 100000000,
            'source_type' => 'excel_upload',
        ]);

        // Coba edit nominal akun resmi oleh asmara (yang punya akses edit fmcg) -> Berhasil
        $responseEdit = $this->actingAs($this->asmara)->post(route('finance.records.save'), [
            'id' => $officialRecord->id,
            'business_unit_code' => 'fmcg',
            'period' => '2026-07-01',
            'report_type' => 'balance_sheet',
            'category' => 'asset',
            'account_name' => 'Kas Resmi Diubah Manual',
            'amount' => 99999999,
        ]);

        $responseEdit->assertSessionHas('success');
        $this->assertDatabaseHas('global_financial_records', [
            'id' => $officialRecord->id,
            'amount' => 99999999,
            'account_name' => 'Kas Resmi Diubah Manual',
        ]);

        // Coba hapus akun resmi -> Berhasil
        $responseDelete = $this->actingAs($this->asmara)->delete(route('finance.records.delete', ['id' => $officialRecord->id]));
        $responseDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('global_financial_records', ['id' => $officialRecord->id]);
    }

    public function test_ferdinand_can_access_and_operate_assigned_five_units_and_previous_users_revoked(): void
    {
        $ferdinand = User::firstOrCreate(
            ['email' => 'ferdinand.winoto@gmail.com'],
            ['name' => 'Ferdinand Winoto', 'password' => bcrypt('password')]
        );

        foreach (['abc', 'aqua', 'ccod', 'fmcg', 'xtrans'] as $unit) {
            UserBusinessUnit::updateOrCreate(
                ['user_id' => $ferdinand->id, 'business_unit_code' => $unit],
                ['can_edit' => true]
            );
        }

        // Ferdinand can access all 5 units
        $this->assertTrue($ferdinand->canAccessBusinessUnit('abc'));
        $this->assertTrue($ferdinand->canAccessBusinessUnit('aqua'));
        $this->assertTrue($ferdinand->canAccessBusinessUnit('ccod'));
        $this->assertTrue($ferdinand->canAccessBusinessUnit('fmcg'));
        $this->assertTrue($ferdinand->canAccessBusinessUnit('xtrans'));

        // Ferdinand cannot access spbu, onbold
        $this->assertFalse($ferdinand->canAccessBusinessUnit('spbu'));
        $this->assertFalse($ferdinand->canAccessBusinessUnit('onbold'));

        // Check fallback mapping from DEFAULT_EMAIL_ASSIGNMENTS
        $this->assertEquals(['abc', 'aqua', 'ccod', 'fmcg', 'xtrans'], User::DEFAULT_EMAIL_ASSIGNMENTS['ferdinand.winoto@gmail.com']);
        $this->assertArrayNotHasKey('yositaaliyanti12@gmail.com', User::DEFAULT_EMAIL_ASSIGNMENTS);
        $this->assertArrayNotHasKey('dwi.indri.liana@gmail.com', User::DEFAULT_EMAIL_ASSIGNMENTS);
        $this->assertArrayNotHasKey('asmarawildha@gmail.com', User::DEFAULT_EMAIL_ASSIGNMENTS);
        $this->assertArrayNotHasKey('laraspujiastuti156@gmail.com', User::DEFAULT_EMAIL_ASSIGNMENTS);
    }
}
