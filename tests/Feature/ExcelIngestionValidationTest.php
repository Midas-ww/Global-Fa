<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalIngestionLog;
use App\Models\User;
use App\Services\GlobalFinance\ExcelIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExcelIngestionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_xtrans_file_properly(): void
    {
        $service = new ExcelIngestionService;
        $xtransFile = 'D:/BKU MJS/PL & BS Detail MJX Sept 26.xlsx';

        if (file_exists($xtransFile)) {
            $detected = $service->detectFileType($xtransFile);
            $this->assertEquals('xtrans', $detected['type']);

            // Harus melempar exception jika dipaksa validasi ke FMCG
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Validasi Format Gagal: File ini terdeteksi sebagai Xtrans');
            $service->validateBusinessUnit($xtransFile, 'fmcg');
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_detects_fmcg_file_properly(): void
    {
        $service = new ExcelIngestionService;
        $fmcgFile = 'D:/BKU MJS/PL Depo Persentase MJP Jan 24 - Dec 26.xlsx';

        if (file_exists($fmcgFile)) {
            $detected = $service->detectFileType($fmcgFile);
            $this->assertEquals('fmcg', $detected['type']);

            // Harus melempar exception jika dipaksa validasi ke XTRANS
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Validasi Format Gagal: File ini terdeteksi sebagai Unilever');
            $service->validateBusinessUnit($fmcgFile, 'xtrans');
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_rejects_mjs_summary_file_for_fmcg(): void
    {
        $service = new ExcelIngestionService;
        $summaryFile = 'D:/BKU MJS/MJS SEPT 26.xlsx';

        if (file_exists($summaryFile)) {
            $detected = $service->detectFileType($summaryFile);
            $this->assertEquals('aqua', $detected['type']);

            // Harus melempar exception jika dipaksa validasi ke FMCG 9 Depo
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Validasi Format Gagal: File ini terdeteksi sebagai AQUA');
            $service->validateBusinessUnit($summaryFile, 'fmcg');
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_disallows_import_of_mjs_summary_file_in_preview(): void
    {
        $user = User::first() ?? User::factory()->create();

        GlobalBusinessUnit::firstOrCreate(['code' => 'fmcg'], [
            'name' => 'Unilever',
            'data_source' => 'excel',
        ]);

        $summaryFile = 'D:/BKU MJS/MJS SEPT 26.xlsx';
        if (file_exists($summaryFile)) {
            $uploadedFile = new UploadedFile(
                $summaryFile,
                'MJS SEPT 26.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $response = $this->actingAs($user)->postJson(route('finance.sync.preview'), [
                'file' => $uploadedFile,
                'type' => 'fmcg',
            ]);

            $response->assertOk();
            $response->assertJson([
                'success' => true,
                'detected_type' => 'aqua',
                'target_type' => 'fmcg',
                'is_type_match' => false,
                'can_import' => false,
            ]);
            $this->assertStringContainsString('Laporan AQUA Danone', $response->json('match_error'));
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_blocks_duplicate_upload_without_overwrite_flag(): void
    {
        $user = User::first() ?? User::factory()->create();

        GlobalBusinessUnit::firstOrCreate(['code' => 'fmcg'], [
            'name' => 'FMCG',
            'data_source' => 'excel',
        ]);

        // Buat log seolah file pernah sukses diupload
        GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'MJS_DUPLICATE_TEST.xlsx',
            'records_count' => 50,
            'status' => 'success',
            'details' => json_encode(['file_hash' => 'dummy_hash_12345']),
        ]);

        $fakeFile = UploadedFile::fake()->create('MJS_DUPLICATE_TEST.xlsx', 100);

        $response = $this->actingAs($user)->post(route('finance.sync.excel'), [
            'file' => $fakeFile,
            'type' => 'fmcg',
            'overwrite' => 0,
        ]);

        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('Peringatan Duplikasi', $errorMessage);
    }

    public function test_it_previews_excel_file_via_api(): void
    {
        $user = User::first() ?? User::factory()->create();

        GlobalBusinessUnit::firstOrCreate(['code' => 'fmcg'], [
            'name' => 'FMCG',
            'data_source' => 'excel',
        ]);

        $fmcgFile = 'D:/BKU MJS/PL Depo Persentase MJP Jan 24 - Dec 26.xlsx';
        if (file_exists($fmcgFile)) {
            $uploadedFile = new UploadedFile(
                $fmcgFile,
                'PL Depo Persentase MJP Jan 24 - Dec 26.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $response = $this->actingAs($user)->postJson(route('finance.sync.preview'), [
                'file' => $uploadedFile,
                'type' => 'fmcg',
            ]);

            $response->assertOk();
            $response->assertJson([
                'success' => true,
                'detected_type' => 'fmcg',
                'target_type' => 'fmcg',
                'is_type_match' => true,
                'can_import' => true,
            ]);
            $this->assertArrayHasKey('summary', $response->json());
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_flags_duplicate_in_preview(): void
    {
        $user = User::first() ?? User::factory()->create();

        GlobalBusinessUnit::firstOrCreate(['code' => 'fmcg'], [
            'name' => 'FMCG',
            'data_source' => 'excel',
        ]);

        GlobalIngestionLog::create([
            'business_unit_code' => 'fmcg',
            'source_type' => 'excel_upload',
            'filename' => 'MJS_DUPLICATE_PREVIEW.xlsx',
            'records_count' => 120,
            'status' => 'success',
            'details' => json_encode(['file_hash' => 'hash_xyz']),
        ]);

        $fmcgFile = 'D:/BKU MJS/PL Depo Persentase MJP Jan 24 - Dec 26.xlsx';
        if (file_exists($fmcgFile)) {
            $uploadedFile = new UploadedFile(
                $fmcgFile,
                'MJS_DUPLICATE_PREVIEW.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $response = $this->actingAs($user)->postJson(route('finance.sync.preview'), [
                'file' => $uploadedFile,
                'type' => 'fmcg',
            ]);

            $response->assertOk();
            $this->assertTrue($response->json('is_duplicate'));
            $this->assertStringContainsString('sudah pernah diimpor', $response->json('duplicate_info.message'));
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }

    public function test_it_accepts_and_imports_aqua_file(): void
    {
        $service = new ExcelIngestionService;
        $summaryFile = 'D:/BKU MJS/MJS SEPT 26.xlsx';

        if (file_exists($summaryFile)) {
            $detected = $service->detectFileType($summaryFile);
            $this->assertEquals('aqua', $detected['type']);

            $res = $service->ingestAquaFile($summaryFile);
            $this->assertTrue($res['success']);
            $this->assertEquals('aqua', $res['business_unit']);
            $this->assertGreaterThan(0, $res['records_inserted']);
        } else {
            $this->markTestSkipped('Sample file not found');
        }
    }
}
