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

        // 1. Business Units (Harmonious Executive Color Palette ala Mekar Jaya & SIR-IQOS)
        $buFmcg = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'fmcg'],
            [
                'name' => 'MJS (FMCG)',
                'description' => 'CV Mekar Jaya Sentosa - Jaringan distribusi barang konsumen (FMCG) 9 depo di Jawa Tengah.',
                'icon' => 'box',
                'color' => '#059669',
                'data_source' => 'excel',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $buXtrans = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'xtrans'],
            [
                'name' => 'MJX (Ekspedisi)',
                'description' => 'PT Mekar Jaya Xtrans Jaya - Jasa logistik dan angkutan armada truk ekspedisi.',
                'icon' => 'truck',
                'color' => '#2563EB',
                'data_source' => 'excel',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $buSpbu = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'spbu'],
            [
                'name' => 'SPBU',
                'description' => 'PT Air Mekar Jaya - Stasiun Pengisian Bahan Bakar Umum (SPBU No. 44.511.12).',
                'icon' => 'fuel',
                'color' => '#E11D48',
                'data_source' => 'database',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $buIqos = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'iqos'],
            [
                'name' => 'IQOS (Retail)',
                'description' => 'PT Mekar Retail Jaya - Retail modern dan toko resmi penjualan IQOS.',
                'icon' => 'store',
                'color' => '#0D9488',
                'data_source' => 'database',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        $buCcod = GlobalBusinessUnit::updateOrCreate(
            ['code' => 'ccod'],
            [
                'name' => 'MJS Multikarya (CCOD)',
                'description' => 'PT Mekar Jaya Sentosa Multikarya - Jasa dan distribusi Cash Collection on Delivery (CCOD) 12 cabang.',
                'icon' => 'shopping-bag',
                'color' => '#6366F1',
                'data_source' => 'excel',
                'sort_order' => 5,
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

        $ccodEntities = [
            ['code' => 'CCOD_PATI', 'name' => 'CCOD PATI', 'location' => 'Pati'],
            ['code' => 'CCOD_SMG', 'name' => 'CCOD SEMARANG', 'location' => 'Semarang'],
            ['code' => 'CCOD_PKL', 'name' => 'CCOD Pekalongan', 'location' => 'Pekalongan'],
            ['code' => 'CCOD_TGL', 'name' => 'CCOD TEGAL', 'location' => 'Tegal'],
            ['code' => 'CCOD_BDG', 'name' => 'CCOD BANDUNG', 'location' => 'Bandung'],
            ['code' => 'CCOD_SMD', 'name' => 'CCOD SUMEDANG', 'location' => 'Sumedang'],
            ['code' => 'CCOD_SLT', 'name' => 'CCOD Salatiga', 'location' => 'Salatiga'],
            ['code' => 'CCOD_MGL', 'name' => 'CCOD Magelang', 'location' => 'Magelang'],
            ['code' => 'CCOD_CRB', 'name' => 'CCOD Cirebon', 'location' => 'Cirebon'],
            ['code' => 'CCOD_JTW', 'name' => 'CCOD Jatiwangi', 'location' => 'Jatiwangi'],
            ['code' => 'CCOD_KNG', 'name' => 'CCOD Kuningan', 'location' => 'Kuningan'],
            ['code' => 'CCOD_LSR', 'name' => 'CCOD Losarang', 'location' => 'Losarang'],
        ];

        foreach ($ccodEntities as $idx => $ent) {
            GlobalEntity::updateOrCreate(
                ['code' => $ent['code']],
                [
                    'business_unit_id' => $buCcod->id,
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
                'name' => 'Retail Store & Outlet',
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
            $iqosService = new IqosSyncService;
            $resIqos = $iqosService->sync();
            echo 'IQOS Synced: '.json_encode($resIqos)."\n";
        } catch (\Throwable $e) {
            echo 'IQOS Sync Warning: '.$e->getMessage()."\n";
        }

        echo "Ingesting SPBU July 2026 from database...\n";
        try {
            $spbuService = new SpbuSyncService;
            $resSpbu = $spbuService->sync('2026-07');
            echo 'SPBU Synced: '.json_encode($resSpbu)."\n";
        } catch (\Throwable $e) {
            echo 'SPBU Sync Warning: '.$e->getMessage()."\n";
        }

        // 5. Ingest FMCG & Xtrans initial files if available
        $excelService = new ExcelIngestionService;
        $fmcgCandidates = [
            'D:/BKU MJS/PL Depo Persentase MJP Jan 24 - Dec 26.xlsx',
            storage_path('app/private/imports/PL_Depo_FMCG.xlsx'),
            'C:/Users/arisc/.gemini/antigravity/brain/5f57d2e4-2ff0-42a5-a3c8-369db83bcf9b/.user_uploaded/media_1794797514184_a54cffc9.xlsx',
        ];
        foreach ($fmcgCandidates as $path) {
            if (file_exists($path)) {
                echo "Ingesting FMCG 9-Depot file from {$path}...\n";
                try {
                    $resFmcg = $excelService->ingestFmcgFile($path);
                    echo 'FMCG Ingested: '.($resFmcg['records_inserted'] ?? 0)." records\n";
                } catch (\Throwable $e) {
                    echo 'FMCG Ingest Warning: '.$e->getMessage()."\n";
                }
                break;
            }
        }

        $xtransCandidates = [
            'D:/BKU MJS/PL & BS Detail MJX Sept 26.xlsx',
            storage_path('app/private/imports/MJX_Ekspedisi.xlsx'),
            'C:/Users/arisc/.gemini/antigravity/brain/5f57d2e4-2ff0-42a5-a3c8-369db83bcf9b/.user_uploaded/media_1794798564677_92f0e307.xlsx',
        ];
        foreach ($xtransCandidates as $path) {
            if (file_exists($path)) {
                echo "Ingesting Xtrans file from {$path}...\n";
                try {
                    $resXtrans = $excelService->ingestXtransFile($path);
                    echo 'Xtrans Ingested: '.($resXtrans['records_inserted'] ?? 0)." records\n";
                } catch (\Throwable $e) {
                    echo 'Xtrans Ingest Warning: '.$e->getMessage()."\n";
                }
                break;
            }
        }
    }
}
