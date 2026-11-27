<?php

namespace Tests\Feature;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Models\User;
use App\Services\GlobalFinance\ExcelIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterBusinessUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_master_units(): void
    {
        $response = $this->get(route('finance.master.units'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_master_units(): void
    {
        $user = User::factory()->create(['email' => 'superadmin@mjg.com', 'name' => 'Super Admin']);
        GlobalBusinessUnit::create([
            'code' => 'fmcg',
            'name' => 'Distribusi FMCG',
            'data_source' => 'excel',
        ]);

        $response = $this->actingAs($user)->get(route('finance.master.units'));
        $response->assertOk();
        $response->assertSee('Master Data Unit Usaha & Entitas');
        $response->assertSee('Distribusi FMCG');
    }

    public function test_authenticated_user_can_create_new_business_unit_with_entities(): void
    {
        $user = User::factory()->create(['email' => 'superadmin@mjg.com', 'name' => 'Super Admin']);

        $response = $this->actingAs($user)->post(route('finance.master.units.store'), [
            'code' => 'hotel',
            'name' => 'PT Mekar Hospitality Jaya',
            'description' => 'Unit bisnis perhotelan dan resort',
            'data_source' => 'excel',
            'color' => '#059669',
            'icon' => 'hotel',
            'initial_entities' => "HOTEL_KNG:Mekar Hotel Kuningan:Kuningan\nHOTEL_CRB:Mekar Hotel Cirebon:Cirebon",
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('global_business_units', [
            'code' => 'hotel',
            'name' => 'PT Mekar Hospitality Jaya',
        ]);

        $this->assertDatabaseHas('global_entities', [
            'code' => 'HOTEL_KNG',
            'name' => 'Mekar Hotel Kuningan',
        ]);
        $this->assertDatabaseHas('global_entities', [
            'code' => 'HOTEL_CRB',
            'name' => 'Mekar Hotel Cirebon',
        ]);
    }

    public function test_authenticated_user_can_add_entity_to_unit(): void
    {
        $user = User::factory()->create(['email' => 'superadmin@mjg.com', 'name' => 'Super Admin']);
        $unit = GlobalBusinessUnit::create([
            'code' => 'fmcg',
            'name' => 'Distribusi FMCG',
            'data_source' => 'excel',
        ]);

        $response = $this->actingAs($user)->post(route('finance.master.entities.store', $unit->id), [
            'code' => 'CABANG_BARU',
            'name' => 'Depo Cabang Baru',
            'location' => 'Semarang',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('global_entities', [
            'business_unit_id' => $unit->id,
            'code' => 'CABANG_BARU',
            'name' => 'Depo Cabang Baru',
        ]);
    }

    public function test_download_template_streams_xlsx(): void
    {
        $user = User::factory()->create(['email' => 'superadmin@mjg.com', 'name' => 'Super Admin']);
        $unit = GlobalBusinessUnit::create([
            'code' => 'fmcg',
            'name' => 'Distribusi FMCG',
            'data_source' => 'excel',
        ]);

        $response = $this->actingAs($user)->get(route('finance.master.units.template', $unit->code));
        $response->assertOk();
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    public function test_universal_template_ingestion_for_new_business_unit(): void
    {
        $user = User::factory()->create(['email' => 'superadmin@mjg.com', 'name' => 'Super Admin']);
        $unit = GlobalBusinessUnit::create([
            'code' => 'cafe',
            'name' => 'PT Mekar Cafe Jaya',
            'data_source' => 'excel',
        ]);

        GlobalEntity::create([
            'business_unit_id' => $unit->id,
            'code' => 'CAFE_01',
            'name' => 'Outlet Cafe Kuningan',
        ]);

        // Generate template
        $service = new ExcelIngestionService;
        $spreadsheet = $service->generateTemplate('cafe');

        // Save to temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'test_cafe_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'Template_Cafe.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($user)->post(route('finance.sync.excel'), [
            'file' => $uploadedFile,
            'type' => 'cafe',
        ]);

        $response->assertSessionHas('success');

        // Verify financial records were created
        $recordsCount = GlobalFinancialRecord::where('business_unit_code', 'cafe')->count();
        $this->assertGreaterThan(0, $recordsCount);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}
