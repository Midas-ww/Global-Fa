<?php

use App\Services\GlobalFinance\SpbuSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sync:spbu {period=all}', function (string $period) {
    $service = app(SpbuSyncService::class);
    if (strtolower($period) === 'all') {
        $this->info('Menjalankan sinkronisasi SPBU untuk SEMUA periode yang tersedia...');
        try {
            $result = $service->syncAll();
            $this->info("Sinkronisasi SPBU Selesai! {$result['periods_synced']} periode disinkronkan (Total: {$result['total_records']} record tersimpan).");
            $rows = [];
            foreach ($result['details'] as $m => $d) {
                if (isset($d['error'])) {
                    $rows[] = [$m, 'ERROR: '.$d['error'], '-', '-', '-'];
                } else {
                    $rows[] = [
                        $m,
                        'Rp '.number_format($d['revenue_total'], 0, ',', '.'),
                        'Rp '.number_format($d['net_profit'], 0, ',', '.'),
                        'Rp '.number_format($d['total_aktiva'], 0, ',', '.'),
                        $d['reconciled'] ? 'Cocok 100%' : 'Ada Selisih',
                    ];
                }
            }
            $this->table(['Periode', 'Omzet Penjualan', 'Laba Bersih', 'Total Aktiva', 'Rekonsiliasi'], $rows);
        } catch (Throwable $e) {
            $this->error('Gagal sinkronisasi: '.$e->getMessage());
        }
    } else {
        $this->info("Menjalankan sinkronisasi SPBU untuk periode {$period}...");
        try {
            $result = $service->sync($period);
            $this->info("Sinkronisasi SPBU Berhasil! {$result['records_count']} record tersimpan.");
            $this->table(
                ['Metrik', 'Nilai'],
                [
                    ['Omzet Penjualan', 'Rp '.number_format($result['revenue_total'], 0, ',', '.')],
                    ['Laba Bersih', 'Rp '.number_format($result['net_profit'], 0, ',', '.')],
                    ['Total Aktiva', 'Rp '.number_format($result['total_aktiva'], 0, ',', '.')],
                    ['Status Rekonsiliasi', $result['reconciled'] ? 'Cocok 100%' : 'Ada Selisih'],
                ]
            );
        } catch (Throwable $e) {
            $this->error('Gagal sinkronisasi: '.$e->getMessage());
        }
    }
})->purpose('Sinkronisasi data SPBU ke Global Finance');
