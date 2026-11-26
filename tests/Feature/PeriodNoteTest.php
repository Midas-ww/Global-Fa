<?php

namespace Tests\Feature;

use App\Models\GlobalFinancialRecord;
use App\Models\GlobalPeriodNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_save_period_note(): void
    {
        $response = $this->post(route('finance.period-note.save'), [
            'period' => '2026-09-01',
            'business_unit_code' => 'abc',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Catatan test belum closing',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_save_and_update_period_note(): void
    {
        $user = User::factory()->create(['name' => 'Finance Staff']);

        $response = $this->actingAs($user)->post(route('finance.period-note.save'), [
            'period' => '2026-09-01',
            'business_unit_code' => 'abc',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Menunggu rekonsiliasi kas cabang',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('global_period_notes', [
            'business_unit_code' => 'abc',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Menunggu rekonsiliasi kas cabang',
            'user_name' => 'Finance Staff',
        ]);

        // Update to closed
        $responseUpdate = $this->actingAs($user)->post(route('finance.period-note.save'), [
            'period' => '2026-09-01',
            'business_unit_code' => 'abc',
            'report_type' => 'pnl',
            'closing_status' => 'closed',
            'notes' => 'Sudah closing dan diverifikasi.',
        ]);

        $responseUpdate->assertSessionHas('success');
        $this->assertDatabaseHas('global_period_notes', [
            'business_unit_code' => 'abc',
            'closing_status' => 'closed',
            'notes' => 'Sudah closing dan diverifikasi.',
        ]);

        // Must still be 1 record (updated in place)
        $this->assertEquals(1, GlobalPeriodNote::where('business_unit_code', 'abc')->count());
    }

    public function test_period_note_shows_dash_when_not_set_and_shows_unclosed_when_set(): void
    {
        $user = User::factory()->create();

        // Create a financial record so 2026-09-01 is in availablePeriods
        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Penjualan Test',
            'amount' => 1000000,
            'source_type' => 'excel_upload',
            'status' => 'final',
        ]);

        // When no period note set yet: should see dash "-"
        $responsePnlEmpty = $this->actingAs($user)->get(route('finance.pnl', ['period' => '2026-09-01']));
        $responsePnlEmpty->assertStatus(200);
        $responsePnlEmpty->assertSee('Belum ada catatan kendala / status closing untuk periode ini.');

        // When set to unclosed
        GlobalPeriodNote::create([
            'period' => '2026-09-01',
            'business_unit_code' => 'all',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Belum closing karena menunggu data fisik stok',
            'user_name' => 'Test Auditor',
        ]);

        $responsePnl = $this->actingAs($user)->get(route('finance.pnl', ['period' => '2026-09-01']));
        $responsePnl->assertStatus(200);
        $responsePnl->assertSee('Belum closing karena menunggu data fisik stok');
        $responsePnl->assertSee('Belum Closing');
    }

    public function test_authenticated_user_can_delete_period_note(): void
    {
        $user = User::factory()->create();

        $note = GlobalPeriodNote::create([
            'period' => '2026-09-01',
            'business_unit_code' => 'all',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Catatan yang akan dihapus',
            'user_name' => 'Auditor',
        ]);

        $response = $this->actingAs($user)->delete(route('finance.period-note.delete', $note->id));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('global_period_notes', ['id' => $note->id]);
    }

    public function test_pak_adrian_and_bu_imel_cannot_edit_period_notes(): void
    {
        $pakAdrian = User::factory()->create(['email' => 'adrian.devianto@mekar-jaya.com']);
        $buImel = User::factory()->create(['email' => 'imelda.daniel@mekar-jaya.com']);

        $note = GlobalPeriodNote::create([
            'period' => '2026-09-01',
            'business_unit_code' => 'all',
            'report_type' => 'pnl',
            'closing_status' => 'unclosed',
            'notes' => 'Catatan laporan resmi',
            'user_name' => 'Finance Staff',
        ]);

        // Pak Adrian cannot save
        $responseAdrianSave = $this->actingAs($pakAdrian)->post(route('finance.period-note.save'), [
            'period' => '2026-09-01',
            'business_unit_code' => 'all',
            'report_type' => 'pnl',
            'closing_status' => 'closed',
            'notes' => 'Coba ubah oleh Adrian',
        ]);
        $responseAdrianSave->assertSessionHas('error');

        // Bu Imel cannot delete
        $responseImelDelete = $this->actingAs($buImel)->delete(route('finance.period-note.delete', $note->id));
        $responseImelDelete->assertSessionHas('error');

        // Note still unchanged in DB
        $this->assertDatabaseHas('global_period_notes', [
            'id' => $note->id,
            'notes' => 'Catatan laporan resmi',
            'closing_status' => 'unclosed',
        ]);

        // They can still view notes on PnL page, but edit button is hidden
        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Penjualan',
            'amount' => 500000,
            'source_type' => 'excel_upload',
            'status' => 'final',
        ]);

        $responseView = $this->actingAs($pakAdrian)->get(route('finance.pnl', ['period' => '2026-09-01']));
        $responseView->assertStatus(200);
        $responseView->assertSee('Catatan laporan resmi');
        $responseView->assertDontSee('+ Isi Catatan Closing');
        $responseView->assertDontSee('Ubah Catatan & Status');
    }
}
