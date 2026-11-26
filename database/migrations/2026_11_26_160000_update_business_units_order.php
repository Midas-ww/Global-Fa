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
        $sortOrders = [
            'aqua' => 1,
            'fmcg' => 2,
            'xtrans' => 3,
            'spbu' => 4,
            'iqos' => 5,
            'kandang' => 6,
            'onbold' => 7,
            'cocacola' => 8,
            'sangria' => 9,
            'mohala' => 10,
            'ccod' => 11,
            'abc' => 12,
        ];

        foreach ($sortOrders as $code => $order) {
            GlobalBusinessUnit::where('code', $code)->update(['sort_order' => $order]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $defaultOrders = [
            'fmcg' => 1,
            'aqua' => 2,
            'ccod' => 3,
            'xtrans' => 4,
            'spbu' => 5,
            'iqos' => 6,
            'kandang' => 7,
            'onbold' => 8,
            'abc' => 9,
            'cocacola' => 10,
            'sangria' => 11,
            'mohala' => 12,
        ];

        foreach ($defaultOrders as $code => $order) {
            GlobalBusinessUnit::where('code', $code)->update(['sort_order' => $order]);
        }
    }
};
