<?php

namespace Tests\Feature;

use App\Models\GlobalFinancialRecord;
use App\Models\GlobalIntercompanyRule;
use App\Models\User;
use App\Services\GlobalFinance\IntercompanyEliminationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntercompanyEliminationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'superadmin@mjg.com',
            'name' => 'Super Admin',
        ]);
    }

    public function test_it_seeds_default_rules_and_auto_syncs_eliminations(): void
    {
        // 1. Create financial record matching rule
        GlobalFinancialRecord::create([
            'business_unit_code' => 'xtrans',
            'entity_code' => 'XTRANS',
            'period' => '2026-09-01',
            'report_type' => 'balance_sheet',
            'category' => 'liability',
            'account_name' => 'Hutang SPBU',
            'amount' => 68395200,
            'status' => 'final',
            'source_type' => 'excel_upload',
        ]);

        $service = app(IntercompanyEliminationService::class);
        $eliminations = $service->syncEliminationsForPeriod('2026-09-01');

        $this->assertNotEmpty($eliminations);
        $this->assertDatabaseHas('global_intercompany_eliminations', [
            'from_entity_code' => 'XTRANS',
            'to_entity_code' => 'SPBU_44',
            'category' => 'hutang_piutang_spbu',
            'amount' => 68395200,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('global_intercompany_rules', [
            'from_entity_code' => 'XTRANS',
            'account_name_pattern' => 'Hutang SPBU',
        ]);
    }

    public function test_authenticated_user_can_trigger_eliminations_sync(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('finance.eliminations.sync'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_authenticated_user_can_toggle_elimination_rule(): void
    {
        $service = app(IntercompanyEliminationService::class);
        $service->seedDefaultRules();

        $rule = GlobalIntercompanyRule::first();
        $this->assertTrue($rule->is_active);

        $response = $this->actingAs($this->user)
            ->post(route('finance.eliminations.rules.toggle', $rule->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse($rule->fresh()->is_active);
    }
}
