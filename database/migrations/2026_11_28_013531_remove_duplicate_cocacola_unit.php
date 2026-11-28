<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_business_units')
            ->where('business_unit_code', 'cocacola')
            ->delete();

        if (DB::table('global_financial_records')->where('business_unit_code', 'cocacola')->doesntExist()) {
            $cocacolaId = DB::table('global_business_units')->where('code', 'cocacola')->value('id');
            if ($cocacolaId) {
                DB::table('global_entities')->where('business_unit_id', $cocacolaId)->delete();
                DB::table('global_business_units')->where('id', $cocacolaId)->delete();
            }
        }

        DB::table('global_business_units')
            ->where('code', 'ccod')
            ->update([
                'description' => 'PT Mekar Jaya Sentosa Multikarya - Distribusi Coca-Cola (CCOD).',
                'sort_order' => 10,
            ]);

        DB::table('global_business_units')->where('code', 'sangria')->update(['sort_order' => 8]);
        DB::table('global_business_units')->where('code', 'mohala')->update(['sort_order' => 9]);
        DB::table('global_business_units')->where('code', 'abc')->update(['sort_order' => 11]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('global_business_units')->updateOrInsert(
            ['code' => 'cocacola'],
            [
                'name' => 'Coca-Cola',
                'description' => 'Distribusi dan operasional produk Coca-Cola.',
                'icon' => 'cup-soda',
                'color' => '#DC2626',
                'data_source' => 'excel',
                'sort_order' => 8,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        DB::table('global_business_units')
            ->where('code', 'ccod')
            ->update([
                'description' => 'PT Mekar Jaya Sentosa Multikarya - Jasa dan distribusi Cash Collection on Delivery (CCOD) Heinz ABC 12 cabang.',
                'sort_order' => 11,
            ]);

        DB::table('global_business_units')->where('code', 'sangria')->update(['sort_order' => 9]);
        DB::table('global_business_units')->where('code', 'mohala')->update(['sort_order' => 10]);
        DB::table('global_business_units')->where('code', 'abc')->update(['sort_order' => 12]);
    }
};
