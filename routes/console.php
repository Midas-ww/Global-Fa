<?php

use App\Services\GlobalFinance\SpbuSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sync:spbu {period=2026-09}', function (string $period) {
    $this->info("Menjalankan sinkronisasi SPBU untuk periode {$period}...");
    try {
        $service = app(SpbuSyncService::class);
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
})->purpose('Sinkronisasi data SPBU ke Global Finance');
