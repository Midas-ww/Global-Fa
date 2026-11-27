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

        // 1. Unrestricted Super Admin
        $this->admin = User::firstOrCreate(
            ['email' => 'superadmin@mjg.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
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

    public function test_scoped_user_can_view_scoped_dashboard_without_redirect(): void
    {
        $response = $this->actingAs($this->asmara)->get(route('finance.dashboard'));

        $response->assertOk();
        $response->assertSee('Overview');
        $response->assertSee('Laba Rugi (P&L)', false);
        $response->assertSee('Neraca Keuangan');
        $response->assertDontSee('Riwayat & Import Data');
        $response->assertDontSee('Master Unit Bisnis');
        $response->assertDontSee('Data Ingestion');
        $response->assertDontSee('Accounting Standards');
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

    public function test_non_superadmin_staff_cannot_access_sync_page_while_superadmin_sees_all_logs(): void
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

        $this->actingAs($this->asmara)->get(route('finance.sync'))->assertForbidden();
        $this->actingAs($this->tasya)->get(route('finance.sync'))->assertForbidden();

        $responseAdmin = $this->actingAs($this->admin)->get(route('finance.sync'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('unilever_pnl_test.xlsx');
        $responseAdmin->assertSee('api_air.shift_sales');
        $responseAdmin->assertSee('dash_onbold_db.records');
    }

    public function test_only_superadmin_can_delete_ingestion_log(): void
    {
        $spbuLog = GlobalIngestionLog::create([
            'business_unit_code' => 'spbu',
            'source_type' => 'database_sync',
            'filename' => 'spbu_test_log.xlsx',
            'records_count' => 10,
            'status' => 'success',
        ]);

        $this->actingAs($this->asmara)->delete(route('finance.sync.delete-log', ['id' => $spbuLog->id]))->assertForbidden();

        $fransiska = User::firstOrCreate(
            ['email' => 'fransiskalia21@gmail.com'],
            ['name' => 'Fransiska Lia', 'password' => bcrypt('password')]
        );
        UserBusinessUnit::updateOrCreate(
            ['user_id' => $fransiska->id, 'business_unit_code' => 'spbu'],
            ['can_edit' => true]
        );

        $this->actingAs($fransiska)->delete(route('finance.sync.delete-log', ['id' => $spbuLog->id]))->assertForbidden();
        $this->assertDatabaseHas('global_ingestion_logs', ['id' => $spbuLog->id]);

        $responseAdmin = $this->actingAs($this->admin)->delete(route('finance.sync.delete-log', ['id' => $spbuLog->id]));
        $responseAdmin->assertRedirect();

        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $spbuLog->id]);
    }

    public function test_pak_adri_and_bu_imel_are_strictly_read_only_with_three_menus_only(): void
    {
        $adri = User::firstOrCreate(
            ['email' => 'adrian.devianto@mekar-jaya.com'],
            ['name' => 'Adrian Devianto', 'password' => bcrypt('password')]
        );
        $imel = User::firstOrCreate(
            ['email' => 'imelda.daniel@mekar-jaya.com'],
            ['name' => 'Imelda Daniel', 'password' => bcrypt('password')]
        );

        foreach ([$adri, $imel] as $viewer) {
            $dash = $this->actingAs($viewer)->get(route('finance.dashboard'));
            $dash->assertOk();
            $dash->assertSee('Overview');
            $dash->assertSee('Laba Rugi (P&L)', false);
            $dash->assertSee('Neraca Keuangan');
            $dash->assertDontSee('Riwayat & Import Data');
            $dash->assertDontSee('Master Unit Bisnis');
            $dash->assertDontSee('Data Ingestion');
            $dash->assertDontSee('Accounting Standards');

            $pnl = $this->actingAs($viewer)->get(route('finance.pnl', ['business_unit' => 'fmcg']));
            $pnl->assertOk();
            $pnl->assertViewHas('canEdit', false);
            $pnl->assertViewHas('canEditNote', false);
            $pnl->assertDontSee('+ Isi Catatan Closing');
            $pnl->assertDontSee('+ Tambah Akun');

            $bs = $this->actingAs($viewer)->get(route('finance.balance-sheet', ['business_unit' => 'fmcg']));
            $bs->assertOk();
            $bs->assertViewHas('canEdit', false);
            $bs->assertViewHas('canEditNote', false);
            $bs->assertDontSee('Sinkronkan Ulang');
            $bs->assertDontSee('Kelola Aturan');

            $this->actingAs($viewer)->get(route('finance.sync'))->assertForbidden();
            $this->actingAs($viewer)->get(route('finance.master.units'))->assertForbidden();
            $this->actingAs($viewer)->post(route('finance.eliminations.sync'))->assertForbidden();
            $this->actingAs($viewer)->postJson(route('finance.records.save'), [
                'business_unit_code' => 'fmcg',
                'period' => '2026-07-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_name' => 'Coba Edit Viewer',
                'amount' => 1000,
            ])->assertForbidden();
            $this->actingAs($viewer)->postJson(route('finance.period-note.save'), [
                'period' => '2026-07-01',
                'business_unit_code' => 'all',
                'report_type' => 'pnl',
                'closing_status' => 'closed',
                'notes' => 'Coba catatan viewer',
            ])->assertForbidden();
        }
    }

    public function test_other_fa_users_only_see_three_menus_and_can_save_notes_and_accounts(): void
    {
        $holdingFa = User::factory()->create([
            'email' => 'kadiv_fa@mjg.com',
            'name' => 'Kadiv FA',
        ]);

        $dash = $this->actingAs($holdingFa)->get(route('finance.dashboard'));
        $dash->assertOk();
        $dash->assertSee('Overview');
        $dash->assertSee('Laba Rugi (P&L)', false);
        $dash->assertSee('Neraca Keuangan');
        $dash->assertDontSee('Riwayat & Import Data');
        $dash->assertDontSee('Master Unit Bisnis');
        $dash->assertDontSee('Accounting Standards');
        $dash->assertDontSee('title="Portal Mekar Jaya Super App"', false);

        $this->actingAs($holdingFa)->get(route('finance.sync'))->assertForbidden();
        $this->actingAs($holdingFa)->get(route('finance.master.units'))->assertForbidden();
        $this->actingAs($holdingFa)->post(route('finance.eliminations.sync'))->assertForbidden();

        // FA can still save period notes and manual accounts
        $this->actingAs($holdingFa)->post(route('finance.period-note.save'), [
            'period' => '2026-07-01',
            'business_unit_code' => 'fmcg',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Catatan Kadiv FA',
        ])->assertSessionHas('success');

        $this->actingAs($holdingFa)->post(route('finance.records.save'), [
            'business_unit_code' => 'fmcg',
            'period' => '2026-07-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Akun Manual FA',
            'amount' => 1500000,
        ])->assertSessionHas('success');
    }

    public function test_superadmin_can_access_all_menus_and_sidebar_has_no_portal_link(): void
    {
        $dash = $this->actingAs($this->admin)->get(route('finance.dashboard'));
        $dash->assertOk();
        $dash->assertSee('Overview');
        $dash->assertSee('Laba Rugi (P&L)', false);
        $dash->assertSee('Neraca Keuangan');
        $dash->assertSee('Riwayat & Import Data', false);
        $dash->assertSee('Master Unit Bisnis');
        $dash->assertSee('Accounting Standards');
        $dash->assertDontSee('title="Portal Mekar Jaya Super App"', false);

        $this->actingAs($this->admin)->get(route('finance.sync'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.master.units'))->assertOk();
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

    public function test_overview_hides_units_without_period_pnl_and_lists_missing_status_note(): void
    {
        GlobalBusinessUnit::firstOrCreate(['code' => 'aqua'], ['name' => 'AQUA', 'data_source' => 'excel', 'is_active' => true, 'sort_order' => 1]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'ccod'], ['name' => 'CCOD', 'data_source' => 'excel', 'is_active' => true, 'sort_order' => 2]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'cocacola'], ['name' => 'Coca-Cola', 'data_source' => 'excel', 'is_active' => true, 'sort_order' => 3]);

        // AQUA punya data Oktober 2026
        GlobalFinancialRecord::insert([
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan Aqua',
                'amount' => 500000000,
                'source_type' => 'excel_upload',
            ],
            // CCOD hanya punya data September 2026 (Oktober kosong)
            [
                'business_unit_code' => 'ccod',
                'entity_code' => 'CCOD_HO',
                'period' => '2026-09-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan CCOD',
                'amount' => 250000000,
                'source_type' => 'excel_upload',
            ],
        ]);

        $service = app(ConsolidationReportService::class);
        $summary = $service->getDashboardSummary('2026-10-01');

        $this->assertEquals(['aqua'], array_column($summary['by_business_unit'], 'code'));

        $missingByCode = collect($summary['missing_business_units'])->keyBy('code');
        $this->assertTrue($missingByCode->has('ccod'));
        $this->assertTrue($missingByCode->has('cocacola'));
        $this->assertStringStartsWith('terakhir ', $missingByCode['ccod']['status_label']);
        $this->assertEquals('belum ada laporan', $missingByCode['cocacola']['status_label']);

        $response = $this->actingAs($this->admin)->get(route('finance.dashboard', ['period' => '2026-10-01']));
        $response->assertOk();
        $response->assertSee('Belum ada data P&amp;L', false);
        $response->assertSee('CCOD');
        $response->assertSee('Coca-Cola');
        $response->assertSee('belum ada laporan');
    }

    public function test_period_filter_shows_all_months_globally_with_smart_default_and_unit_scoped_periods(): void
    {
        GlobalBusinessUnit::firstOrCreate(['code' => 'aqua'], ['name' => 'AQUA', 'data_source' => 'excel', 'is_active' => true, 'sort_order' => 1]);
        GlobalBusinessUnit::firstOrCreate(['code' => 'xtrans'], ['name' => 'Xtrans', 'data_source' => 'excel', 'is_active' => true, 'sort_order' => 2]);

        GlobalFinancialRecord::insert([
            // SPBU punya November 2026 (1 unit saja -> bulan berjalan yang masih sepi)
            [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => '2026-11-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan SPBU Nov',
                'amount' => 100000000,
                'source_type' => 'database',
            ],
            // Oktober 2026 terisi oleh 4 unit (mayoritas: fmcg, aqua, xtrans, spbu)
            [
                'business_unit_code' => 'fmcg',
                'entity_code' => 'MJP',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan FMCG Okt',
                'amount' => 300000000,
                'source_type' => 'excel_upload',
            ],
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan Aqua Okt',
                'amount' => 400000000,
                'source_type' => 'excel_upload',
            ],
            [
                'business_unit_code' => 'xtrans',
                'entity_code' => 'XTRANS',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan Xtrans Okt',
                'amount' => 200000000,
                'source_type' => 'excel_upload',
            ],
            [
                'business_unit_code' => 'spbu',
                'entity_code' => 'SPBU_44',
                'period' => '2026-10-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan SPBU Okt',
                'amount' => 150000000,
                'source_type' => 'database',
            ],
            // AQUA juga punya September 2026 (dan Balance Sheet hanya Oktober 2026)
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-09-01',
                'report_type' => 'pnl',
                'category' => 'revenue',
                'account_code' => '4000-01',
                'account_name' => 'Penjualan Aqua Sep',
                'amount' => 350000000,
                'source_type' => 'excel_upload',
            ],
            [
                'business_unit_code' => 'aqua',
                'entity_code' => 'MJS',
                'period' => '2026-10-01',
                'report_type' => 'balance_sheet',
                'category' => 'asset',
                'account_code' => '1101',
                'account_name' => 'Kas Aqua Okt',
                'amount' => 500000000,
                'source_type' => 'excel_upload',
            ],
        ]);

        // 1. Overview & Global P&L: dropdown menampilkan semua bulan (termasuk Nov), tapi default mendarat di Okt 2026
        $dash = $this->actingAs($this->admin)->get(route('finance.dashboard'));
        $dash->assertOk();
        $dash->assertViewHas('availablePeriods', ['2026-11-01', '2026-10-01', '2026-09-01']);
        $dash->assertViewHas('selectedPeriod', '2026-10-01');

        $globalPnl = $this->actingAs($this->admin)->get(route('finance.pnl'));
        $globalPnl->assertOk();
        $globalPnl->assertViewHas('availablePeriods', ['2026-11-01', '2026-10-01', '2026-09-01']);
        $globalPnl->assertViewHas('selectedPeriod', '2026-10-01');

        // 2. Per Unit (AQUA di P&L): dropdown hanya memunculkan bulan milik AQUA (Okt & Sep), default ke Okt
        $aquaPnl = $this->actingAs($this->admin)->get(route('finance.pnl', ['business_unit' => 'aqua']));
        $aquaPnl->assertOk();
        $aquaPnl->assertViewHas('availablePeriods', ['2026-10-01', '2026-09-01']);
        $aquaPnl->assertViewHas('selectedPeriod', '2026-10-01');

        // 3. Per Unit (SPBU di P&L): dropdown hanya memunculkan Nov & Okt, default ke bulan terbarunya Nov 2026
        $spbuPnl = $this->actingAs($this->admin)->get(route('finance.pnl', ['business_unit' => 'spbu']));
        $spbuPnl->assertOk();
        $spbuPnl->assertViewHas('availablePeriods', ['2026-11-01', '2026-10-01']);
        $spbuPnl->assertViewHas('selectedPeriod', '2026-11-01');

        // 4. Per Unit di Neraca (AQUA hanya punya Balance Sheet Okt 2026, bukan Sep 2026)
        $aquaBs = $this->actingAs($this->admin)->get(route('finance.balance-sheet', ['business_unit' => 'aqua']));
        $aquaBs->assertOk();
        $aquaBs->assertViewHas('availablePeriods', ['2026-10-01']);
        $aquaBs->assertViewHas('selectedPeriod', '2026-10-01');
    }
}
