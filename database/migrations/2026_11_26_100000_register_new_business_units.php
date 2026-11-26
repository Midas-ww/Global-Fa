<?php

use App\Models\GlobalBusinessUnit;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $units = [
            [
                'code' => 'abc',
                'name' => 'ABC',
                'description' => 'PT Mekar Jaya Sentosa Multikarya - Jasa dan distribusi Heinz ABC.',
                'icon' => 'shopping-bag',
                'color' => '#EA580C',
                'data_source' => 'excel',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'code' => 'cocacola',
                'name' => 'Coca-Cola',
                'description' => 'Distribusi dan operasional produk Coca-Cola.',
                'icon' => 'cup-soda',
                'color' => '#DC2626',
                'data_source' => 'excel',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'sangria',
                'name' => 'Sangria',
                'description' => 'Unit Bisnis Sangria.',
                'icon' => 'coffee',
                'color' => '#9333EA',
                'data_source' => 'excel',
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'code' => 'mohala',
                'name' => 'Mohala',
                'description' => 'Unit Bisnis Mohala.',
                'icon' => 'sun',
                'color' => '#F59E0B',
                'data_source' => 'excel',
                'sort_order' => 12,
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            GlobalBusinessUnit::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        GlobalBusinessUnit::whereIn('code', ['abc', 'cocacola', 'sangria', 'mohala'])->delete();
    }
};
