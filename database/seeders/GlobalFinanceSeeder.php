<?php

namespace Database\Seeders;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalIntercompanyElimination;
use App\Models\User;
use App\Services\GlobalFinance\ExcelIngestionService;
use App\Services\GlobalFinance\IqosSyncService;
use App\Services\GlobalFinance\SpbuSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GlobalFinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ini_set('memory_limit', '1024M');
        // 0. Default Admin User
        User::updateOrCreate(
            ['email' => 'admin@mekarjaya.com'],
            [
                'name' => 'Administrator Holding',
                'password' => Hash::make('password'),
            ]
        );

        // 1. Business Units
        $buFmcg = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'fmcg'],
            [
                'name' => 'Distributor FMCG',
                'description' => 'Jaringan distribusi barang konsumen (FMCG) 9 depo di Jawa Tengah.',
                'icon' => 'box',
                'color' => '#2563EB',
                'data_source' => 'excel',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $buXtrans = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'xtrans'],
            [
                'name' => 'Ekspedisi Mekar Jaya',
                'description' => 'Jasa logistik dan angkutan truk ekspedisi PT Mekar Jaya Xtrans Jaya.',
                'icon' => 'truck',
                'color' => '#D97706',
                'data_source' => 'excel',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $buSpbu = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'spbu'],
            [
                'name' => 'SPBU Retail',
                'description' => 'Stasiun Pengisian Bahan Bakar Umum (SPBU No. 44.511.12).',
                'icon' => 'fuel',
                'color' => '#DC2626',
                'data_source' => 'database',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $buIqos = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'iqos'],
            [
                'name' => 'IQOS Official Store',
                'description' => 'Retail device dan konsumsi rokok elektrik / heat-not-burn IQOS.',
                'icon' => 'store',
                'color' => '#059669',
                'data_source' => 'database',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        // 2. Entities
        $fmcgEntities = [
            ['code' => 'BMG', 'name' => 'Depo Bumiayu (BMG)', 'location' => 'Bumiayu'],
            ['code' => 'PWT', 'name' => 'Depo Purwokerto (PWT)', 'location' => 'Purwokerto'],
            ['code' => 'PML_GT', 'name' => 'Depo Pemalang GT', 'location' => 'Pemalang'],
            ['code' => 'PML_MT', 'name' => 'Depo Pemalang MT', 'location' => 'Pemalang'],
            ['code' => 'WLR', 'name' => 'Depo Weleri (WLR)', 'location' => 'Weleri'],
            ['code' => 'RDK', 'name' => 'Depo Randudongkal (RDK)', 'location' => 'Randudongkal'],
            ['code' => 'PWJ', 'name' => 'Depo Purworejo (PWJ)', 'location' => 'Purworejo'],
            ['code' => 'KBM', 'name' => 'Depo Kebumen (KBM)', 'location' => 'Kebumen'],
            ['code' => 'PKL', 'name' => 'Depo Pekalongan (PKL)', 'location' => 'Pekalongan'],
        ];

        foreach ($fmcgEntities as $idx => $ent) {
            GlobalEntity::updateOrCreate(
                ['code' => $ent['code']],
                [
                    'business_unit_id' => $buFmcg->id,
                    'name' => $ent['name'],
                    'location' => $ent['location'],
                    'sort_order' => $idx + 1,
                    'is_active' => true,
                ]
            );
        }

        GlobalEntity::updateOrCreate(
            ['code' => 'XTRANS'],
            [
                'business_unit_id' => $buXtrans->id,
                'name' => 'PT Mekar Jaya Xtrans Jaya',
                'location' => 'Pekalongan / Jawa Tengah',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        GlobalEntity::updateOrCreate(
            ['code' => 'SPBU_44'],
            [
                'business_unit_id' => $buSpbu->id,
                'name' => 'SPBU No. 44.511.12',
                'location' => 'Pekalongan',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        GlobalEntity::updateOrCreate(
            ['code' => 'IQOS_RETAIL'],
            [
                'business_unit_id' => $buIqos->id,
                'name' => 'SIR-IQOS Store & Retail',
                'location' => 'Pekalongan & Sekitarnya',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        // 3. Intercompany Eliminations (Sample)
        GlobalIntercompanyElimination::updateOrCreate(
            [
                'period' => '2026-09-01',
                'from_entity_code' => 'XTRANS',
                'to_entity_code' => 'SPBU_44',
                'category' => 'hutang_piutang_spbu',
            ],
            [
                'description' => 'Eliminasi Piutang/Hutang Solar Ritase Armada Truk Xtrans di SPBU No. 44.511.12',
                'amount' => 125000000,
                'is_active' => true,
            ]
        );

        // 4. Ingest Databases (IQOS & SPBU)
        echo "Ingesting IQOS from database...\n";
        try {
            $iqosService = new IqosSyncService();
            $resIqos = $iqosService->sync();
            echo "IQOS Synced: " . json_encode($resIqos) . "\n";
        } catch (\Throwable $e) {
            echo "IQOS Sync Warning: " . $e->getMessage() . "\n";
        }

        echo "Ingesting SPBU July 2026 from database...\n";
        try {
            $spbuService = new SpbuSyncService();
            $resSpbu = $spbuService->sync('2026-07');
            echo "SPBU Synced: " . json_encode($resSpbu) . "\n";
        } catch (\Throwable $e) {
            echo "SPBU Sync Warning: " . $e->getMessage() . "\n";
        }

        // 5. Ingest FMCG & Xtrans if file exists
        $excelService = new ExcelIngestionService();
        $fmcgPath = 'C:/Users/arisc/.gemini/antigravity/brain/5f57d2e4-2ff0-42a5-a3c8-369db83bcf9b/.user_uploaded/media_1794797514184_a54cffc9.xlsx';
        if (file_exists($fmcgPath)) {
            echo "Ingesting FMCG 9-Depot file...\n";
            $resFmcg = $excelService->ingestFmcgFile($fmcgPath);
            echo "FMCG Ingested: " . $resFmcg['records_inserted'] . " records\n";
        }

        $xtransPath = 'C:/Users/arisc/.gemini/antigravity/brain/5f57d2e4-2ff0-42a5-a3c8-369db83bcf9b/.user_uploaded/media_1794798564677_92f0e307.xlsx';
        if (file_exists($xtransPath)) {
            echo "Ingesting Xtrans file...\n";
            $resXtrans = $excelService->ingestXtransFile($xtransPath);
            echo "Xtrans Ingested: " . $resXtrans['records_inserted'] . " records\n";
        }
    }
}
