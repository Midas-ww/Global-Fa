@extends('layouts.app')

@section('title', 'Data Ingestion & Sync Hub')
@section('page_title', 'Pusat Sinkronisasi Database & Import Excel')

@section('content')
<div class="space-y-6 pb-12">

    <!-- OVERVIEW STATS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Record Data</span>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['total_records'] ?? 0, 0, ',', '.') }}</h4>
                <p class="text-xs text-slate-500 mt-0.5">Tersimpan di database konsolidasi</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="database" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Unit Bisnis Aktif</span>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ $stats['total_business_units'] ?? 4 }} Unit Bisnis</h4>
                <p class="text-xs text-slate-500 mt-0.5">{{ $stats['total_entities'] ?? 12 }} Cabang & Entitas Hukum</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="building-2" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Koneksi Database</span>
                <h4 class="text-2xl font-black text-emerald-600 mt-1 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    2 Terkoneksi
                </h4>
                <p class="text-xs text-slate-500 mt-0.5">SPBU (api_air) & IQOS (sir_iqos)</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="network" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pembaruan Terakhir</span>
                <h4 class="text-sm font-bold text-slate-800 mt-2 truncate max-w-[150px]">
                    {{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->diffForHumans() : 'Belum Ada' }}
                </h4>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->format('d M Y H:i') : '-' }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN SECTIONS: 1. DB SYNC, 2. EXCEL INGESTION -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- SECTION 1: DATABASE SYNC (SPBU & IQOS) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 text-base">Sinkronisasi Database Otomatis</h3>
                        <p class="text-xs text-slate-500">Tarik data real-time langsung dari database operasional unit bisnis.</p>
                    </div>
                </div>
                <form action="{{ route('finance.sync.database') }}" method="POST" onsubmit="return confirm('Mulai sinkronisasi seluruh database terhubung (SPBU & IQOS)?');">
                    @csrf
                    <input type="hidden" name="target" value="all">
                    <input type="hidden" name="period" value="{{ $selectedPeriod ?? '2026-07' }}">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                        Sinkronkan Semua
                    </button>
                </form>
            </div>

            <!-- SPBU CARD -->
            <div class="p-4 rounded-xl border border-slate-200/90 bg-slate-50/50 hover:bg-white hover:border-blue-300 transition duration-150">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 font-black text-xs flex items-center justify-center">
                            SPBU
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">SPBU 34-45501 (PT Air Mekar Jaya)</h4>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <span>DB: <code class="text-blue-600 font-mono">api_air</code></span>
                                <span>•</span>
                                <span class="text-emerald-600 font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-blue-50 text-blue-700">Daily Shifts & Sales</span>
                </div>
                <p class="text-xs text-slate-600 mt-2.5 leading-relaxed">
                    Menghitung omzet BBM (Pertalite, Pertamax, Biosolar, Dexlite), HPP modal tebusan Pertamina, dan laba kotor per shift untuk dikonversi ke akun Finansial Holding.
                </p>
                <form action="{{ route('finance.sync.database') }}" method="POST" class="mt-4 flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="target" value="spbu">
                    <div class="flex-1">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Periode Bulan</label>
                        <select name="period" class="w-full bg-white border border-slate-200 rounded-lg text-xs font-medium px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="2026-07" selected>Juli 2026</option>
                            <option value="2026-06">Juni 2026</option>
                            <option value="2026-05">Mei 2026</option>
                        </select>
                    </div>
                    <div class="pt-5">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            Sinkron SPBU
                        </button>
                    </div>
                </form>
            </div>

            <!-- IQOS CARD -->
            <div class="p-4 rounded-xl border border-slate-200/90 bg-slate-50/50 hover:bg-white hover:border-purple-300 transition duration-150">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-purple-100 text-purple-700 font-black text-xs flex items-center justify-center">
                            IQOS
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Retail IQOS (PT Mekar Retail Jaya)</h4>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <span>DB: <code class="text-purple-600 font-mono">sir_iqos</code></span>
                                <span>•</span>
                                <span class="text-emerald-600 font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-purple-50 text-purple-700">Monthly P&L Ledger</span>
                </div>
                <p class="text-xs text-slate-600 mt-2.5 leading-relaxed">
                    Membaca buku laporan bulanan toko resmi IQOS, mencakup penjualan device & consumables HEETS/TEREA, HPP konsinyasi, dan biaya sewa counter.
                </p>
                <form action="{{ route('finance.sync.database') }}" method="POST" class="mt-4 flex items-center justify-between pt-1">
                    @csrf
                    <input type="hidden" name="target" value="iqos">
                    <span class="text-xs text-slate-500">Mencakup 10 periode historis terdata di sistem SIR-IQOS.</span>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        Sinkron IQOS
                    </button>
                </form>
            </div>
        </div>

        <!-- SECTION 2: EXCEL INGESTION HUB -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 text-base">Excel Ingestion Hub</h3>
                        <p class="text-xs text-slate-500">Import laporan keuangan untuk unit bisnis yang belum terhubung sistem.</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">
                    Auto-Parsing
                </span>
            </div>

            <!-- FMCG 9 DEPO UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/20 hover:bg-emerald-50/40 transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 font-black text-xs flex items-center justify-center">
                            FMCG
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Distribusi FMCG (9 Depo Cabang)</h4>
                            <p class="text-[11px] text-slate-500">Kuningan, Cirebon, Majalengka, Indramayu, Subang, Purwakarta, Karawang, Bekasi, Cikarang</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="fmcg">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-emerald-500 rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel FMCG...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-emerald-700">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-emerald-600"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel FMCG (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[11px] text-slate-400">Format: Multi-sheet per depo (Sales, HPP, OPEX)</span>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                            Import FMCG
                        </button>
                    </div>
                </form>
            </div>

            <!-- XTRANS EKSPEDISI UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-amber-300 bg-amber-50/20 hover:bg-amber-50/40 transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 font-black text-xs flex items-center justify-center">
                            XTR
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Ekspedisi (PT Mekar Jaya Xtrans Jaya)</h4>
                            <p class="text-[11px] text-slate-500">Laporan Laba Rugi Operasional & Neraca Konsolidasi Armada</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="xtrans">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-amber-500 rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel Ekspedisi...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-amber-700">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-amber-600"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel Xtrans (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[11px] text-slate-400">Format: Sheet Laba Rugi & Neraca Berimbang</span>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                            Import Ekspedisi
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- INGESTION & SYNC AUDIT LOG TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <i data-lucide="history" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-900 text-base">Riwayat Aktivitas Ingestion & Sinkronisasi</h3>
                    <p class="text-xs text-slate-500">Log audit seluruh penyerapan data dari database dan upload berkas Excel.</p>
                </div>
            </div>
            <span class="text-xs font-medium text-slate-400">Menampilkan 20 aktivitas terakhir</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <th class="py-3 px-5">Waktu</th>
                        <th class="py-3 px-5">Unit Bisnis</th>
                        <th class="py-3 px-5">Metode / Sumber</th>
                        <th class="py-3 px-5">Berkas / Tabel Referensi</th>
                        <th class="py-3 px-5 text-right">Data Terserap</th>
                        <th class="py-3 px-5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-3 px-5 font-mono text-[11px] text-slate-500">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="py-3 px-5 font-bold uppercase text-slate-800">
                            <span class="px-2 py-0.5 rounded text-[11px] 
                                @if(str_contains(strtolower($log->business_unit_code), 'fmcg')) bg-emerald-50 text-emerald-700 border border-emerald-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'spbu')) bg-blue-50 text-blue-700 border border-blue-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'iqos')) bg-purple-50 text-purple-700 border border-purple-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'xtrans')) bg-amber-50 text-amber-700 border border-amber-200
                                @else bg-slate-100 text-slate-700 border border-slate-200 @endif">
                                {{ $log->business_unit_code }}
                            </span>
                        </td>
                        <td class="py-3 px-5">
                            @if($log->source_type === 'database_sync')
                                <span class="inline-flex items-center gap-1 text-blue-600 font-semibold">
                                    <i data-lucide="database" class="w-3.5 h-3.5"></i> Database Sync
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                                    <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Excel Upload
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-5 font-mono text-slate-600">
                            {{ $log->filename ?? '-' }}
                        </td>
                        <td class="py-3 px-5 text-right font-black text-slate-900">
                            {{ number_format($log->records_count, 0, ',', '.') }} rows
                        </td>
                        <td class="py-3 px-5 text-center">
                            @if($log->status === 'success')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 inline-flex items-center gap-1">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i> Berhasil
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 inline-flex items-center gap-1" title="{{ $log->details }}">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i> Gagal
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                            Belum ada riwayat aktivitas sinkronisasi atau upload file.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
