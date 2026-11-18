<?php

namespace Tests\Feature;

use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIngestionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteIngestionLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_delete_ingestion_log(): void
    {
        $log = GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'sample.xlsx',
            'records_count' => 10,
            'status' => 'success',
        ]);

        $response = $this->delete(route('finance.sync.delete-log', $log->id));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('global_ingestion_logs', ['id' => $log->id]);
    }

    public function test_authenticated_user_can_delete_excel_ingestion_log_and_its_financial_records(): void
    {
        $user = User::factory()->create();

        $log = GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'fmcg_test.xlsx',
            'records_count' => 2,
            'status' => 'success',
        ]);

        // Records for this upload
        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Penjualan Test',
            'amount' => 1500000,
            'source_type' => 'excel_upload',
            'notes' => 'fmcg_test.xlsx',
        ]);

        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'opex',
            'account_name' => 'Biaya Test',
            'amount' => 500000,
            'source_type' => 'excel_upload',
            'notes' => 'fmcg_test.xlsx',
        ]);

        // Record for another upload that should NOT be deleted
        $otherRecord = GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Penjualan File Lain',
            'amount' => 2000000,
            'source_type' => 'excel_upload',
            'notes' => 'another_file.xlsx',
        ]);

        $response = $this->actingAs($user)->delete(route('finance.sync.delete-log', $log->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('global_financial_records', ['notes' => 'fmcg_test.xlsx']);
        $this->assertDatabaseHas('global_financial_records', ['id' => $otherRecord->id]);
    }

    public function test_authenticated_user_can_delete_database_sync_log(): void
    {
        $user = User::factory()->create();

        $log = GlobalIngestionLog::create([
            'business_unit_code' => 'spbu',
            'source_type' => 'database_sync',
            'filename' => 'api_air.shift_sales (2026-09)',
            'records_count' => 30,
            'status' => 'success',
        ]);

        $response = $this->actingAs($user)->delete(route('finance.sync.delete-log', $log->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $log->id]);
    }

    public function test_guest_cannot_bulk_delete_ingestion_logs(): void
    {
        $log = GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'sample.xlsx',
            'records_count' => 10,
            'status' => 'success',
        ]);

        $response = $this->delete(route('finance.sync.bulk-delete'), [
            'ids' => [$log->id],
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('global_ingestion_logs', ['id' => $log->id]);
    }

    public function test_authenticated_user_can_bulk_delete_ingestion_logs_and_financial_records(): void
    {
        $user = User::factory()->create();

        $log1 = GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'bulk_file_1.xlsx',
            'records_count' => 1,
            'status' => 'success',
        ]);

        $log2 = GlobalIngestionLog::create([
            'business_unit_code' => 'xtrans',
            'source_type' => 'excel_upload',
            'filename' => 'bulk_file_2.xlsx',
            'records_count' => 1,
            'status' => 'success',
        ]);

        $log3 = GlobalIngestionLog::create([
            'business_unit_code' => 'ccod',
            'source_type' => 'excel_upload',
            'filename' => 'bulk_keep.xlsx',
            'records_count' => 1,
            'status' => 'success',
        ]);

        GlobalFinancialRecord::create([
            'business_unit_code' => 'fmcg',
            'entity_code' => 'BMG',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Data 1',
            'amount' => 100000,
            'source_type' => 'excel_upload',
            'notes' => 'bulk_file_1.xlsx',
        ]);

        GlobalFinancialRecord::create([
            'business_unit_code' => 'xtrans',
            'entity_code' => 'MJX',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Data 2',
            'amount' => 200000,
            'source_type' => 'excel_upload',
            'notes' => 'bulk_file_2.xlsx',
        ]);

        $keepRecord = GlobalFinancialRecord::create([
            'business_unit_code' => 'ccod',
            'entity_code' => 'MJS',
            'period' => '2026-09-01',
            'report_type' => 'pnl',
            'category' => 'revenue',
            'account_name' => 'Data Keep',
            'amount' => 300000,
            'source_type' => 'excel_upload',
            'notes' => 'bulk_keep.xlsx',
        ]);

        $response = $this->actingAs($user)->delete(route('finance.sync.bulk-delete'), [
            'ids' => [$log1->id, $log2->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $log1->id]);
        $this->assertDatabaseMissing('global_ingestion_logs', ['id' => $log2->id]);
        $this->assertDatabaseHas('global_ingestion_logs', ['id' => $log3->id]);

        $this->assertDatabaseMissing('global_financial_records', ['notes' => 'bulk_file_1.xlsx']);
        $this->assertDatabaseMissing('global_financial_records', ['notes' => 'bulk_file_2.xlsx']);
        $this->assertDatabaseHas('global_financial_records', ['id' => $keepRecord->id]);
    }

    public function test_bulk_delete_validation_fails_when_no_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('finance.sync.bulk-delete'), [
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }
}
