@extends('layouts.app')

@section('title', 'Riwayat & Import Data')

@section('content')
<div class="space-y-6 pb-12">

    <!-- PAGE HEADER WITH QUICK JUMP -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight">Pusat Integrasi & Riwayat Import Data</h2>
            <p class="text-xs text-slate-500 mt-0.5">Sinkronisasi database live antar unit usaha & upload laporan berkas Excel konsolidasi</p>
        </div>
        <a href="#riwayat-import" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition shadow-2xs">
            <i data-lucide="history" class="w-4 h-4 text-[#9c4a2f]"></i>
            <span>Lihat Riwayat & Hapus Data Import ({{ count($logs) }} Riwayat)</span>
        </a>
    </div>

    <!-- OVERVIEW STATS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-[#e6e1d5] shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Record Data</span>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['total_records'] ?? 0, 0, ',', '.') }}</h4>
                <p class="text-xs text-slate-400 mt-0.5">Tersimpan di database konsolidasi</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                <i data-lucide="database" class="w-5 h-5 text-[#9c4a2f]"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-[#e6e1d5] shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Unit Bisnis Aktif</span>
                <h4 class="text-2xl font-black text-slate-900 mt-1">{{ $stats['total_business_units'] ?? count($businessUnits) }} Unit Bisnis</h4>
                <p class="text-xs text-slate-400 mt-0.5">{{ $stats['total_entities'] ?? 24 }} Cabang & Entitas Hukum</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                <i data-lucide="building-2" class="w-5 h-5 text-[#9c4a2f]"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-[#e6e1d5] shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Koneksi Database</span>
                <h4 class="text-2xl font-black text-[#16a34a] mt-1 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#16a34a]"></span>
                    2 Terkoneksi
                </h4>
                <p class="text-xs text-slate-400 mt-0.5">SPBU (api_air) & IQOS (Retail) (sir_iqos)</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                <i data-lucide="network" class="w-5 h-5 text-[#9c4a2f]"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-[#e6e1d5] shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pembaruan Terakhir</span>
                <h4 class="text-sm font-bold text-slate-800 mt-2 truncate max-w-[150px]">
                    {{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->diffForHumans() : 'Belum Ada' }}
                </h4>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->format('d M Y H:i') : '-' }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-[#fef9c3] text-[#854d0e] flex items-center justify-center">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN SECTIONS: 1. DB SYNC, 2. EXCEL INGESTION -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- SECTION 1: DATABASE SYNC (SPBU & RETAIL) -->
        <div class="bg-white rounded-2xl border border-[#e6e1d5] shadow-xs p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-[#e6e1d5] pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                        <i data-lucide="refresh-cw" class="w-5 h-5 text-[#9c4a2f]"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Sinkronisasi Database Otomatis</h3>
                        <p class="text-xs text-slate-400">Tarik data real-time langsung dari database operasional SPBU & IQOS (Retail).</p>
                    </div>
                </div>
                <form action="{{ route('finance.sync.database') }}" method="POST" onsubmit="return confirm('Mulai sinkronisasi seluruh database terhubung?');">
                    @csrf
                    <input type="hidden" name="target" value="all">
                    <input type="hidden" name="period" value="{{ $selectedPeriod ?? '2026-07' }}">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-[#e6e1d5] text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <i data-lucide="zap" class="w-3.5 h-3.5 text-[#9c4a2f]"></i>
                        Sinkronkan Semua
                    </button>
                </form>
            </div>

            <!-- SPBU CARD -->
            <div class="p-4 rounded-xl border border-[#e6e1d5] bg-[#fbfdfa] hover:bg-white hover:border-slate-300 transition duration-150 group">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-700 font-bold text-xs flex items-center justify-center border border-rose-200">
                            SPBU
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">SPBU No. 44.511.12 (PT Air Mekar Jaya)</h4>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <span>DB: <code class="text-rose-600 font-mono font-semibold">api_air</code></span>
                                <span>•</span>
                                <span class="text-emerald-600 font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-rose-50 text-rose-700 border border-rose-200/60 font-mono">Daily BBM Shifts</span>
                </div>
                <p class="text-xs text-slate-500 mt-2.5 leading-relaxed">
                    Menghitung omzet BBM (Pertalite, Pertamax, Biosolar, Dexlite), HPP modal tebusan Pertamina, dan laba kotor per shift untuk dikonversi ke akun Finansial Holding.
                </p>
                <form action="{{ route('finance.sync.database') }}" method="POST" class="mt-4 flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="target" value="spbu">
                    <div class="flex-1">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Periode Bulan</label>
                        <select name="period" class="w-full bg-white border border-[#e6e1d5] rounded-xl text-xs font-mono font-medium px-3 py-2 text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#059669]">
                            <option value="2026-11" selected>November 2026 (Berjalan)</option>
                            <option value="2026-10">Oktober 2026</option>
                            <option value="2026-09">September 2026</option>
                            <option value="2026-08">Agustus 2026</option>
                            <option value="2026-07">Juli 2026</option>
                            <option value="2026-06">Juni 2026</option>
                            <option value="2026-05">Mei 2026</option>
                            <option value="2026-04">April 2026</option>
                            <option value="2026-03">Maret 2026</option>
                            <option value="2026-02">Februari 2026</option>
                            <option value="2026-01">Januari 2026</option>
                        </select>
                    </div>
                    <div class="pt-5">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-[#e6e1d5] text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-[#9c4a2f]"></i>
                            Sinkron SPBU
                        </button>
                    </div>
                </form>
            </div>

            <!-- IQOS (RETAIL) CARD -->
            <div class="p-4 rounded-xl border border-[#e6e1d5] bg-[#fbfdfa] hover:bg-white hover:border-slate-300 transition duration-150 group">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-teal-50 text-teal-800 font-bold text-xs flex items-center justify-center border border-teal-200">
                            IQOS
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">IQOS (Retail) - PT Mekar Retail Jaya</h4>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <span>DB: <code class="text-slate-700 font-mono font-semibold">sir_iqos</code></span>
                                <span>•</span>
                                <span class="text-emerald-600 font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-teal-50 text-teal-800 border border-teal-200 font-mono">Monthly P&L Ledger</span>
                </div>
                <p class="text-xs text-slate-500 mt-2.5 leading-relaxed">
                    Membaca buku laporan bulanan unit retail store, mencakup penjualan produk retail & consumables, HPP konsinyasi, dan biaya operasional toko.
                </p>
                <form action="{{ route('finance.sync.database') }}" method="POST" class="mt-4 flex items-center justify-between pt-1">
                    @csrf
                    <input type="hidden" name="target" value="iqos">
                    <span class="text-xs text-slate-400">Mencakup periode historis terdata di database operasional.</span>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-[#9c4a2f] hover:bg-[#853c23] text-white font-bold text-xs transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        Sinkron IQOS (Retail)
                    </button>
                </form>
            </div>
        </div>

        <!-- SECTION 2: EXCEL INGESTION HUB -->
        <div class="bg-white rounded-2xl border border-[#e6e1d5] shadow-xs p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-[#e6e1d5] pb-4 flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5 text-[#9c4a2f]"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Excel Ingestion Hub</h3>
                        <p class="text-xs text-slate-400">Import laporan keuangan untuk unit bisnis yang belum terhubung sistem.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-[#f8ede7] text-[#782f18] border border-[#e2beaf] flex items-center gap-1.5 shadow-2xs">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5 text-[#9c4a2f]"></i>
                        <span>Validasi Cerdas & Proteksi Duplikasi Aktif</span>
                    </span>
                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-[#f8ede7] text-[#782f18] border border-[#e2beaf]">
                        Auto-Parsing
                    </span>
                </div>
            </div>

            <!-- UNILEVER (FMCG) 9 DEPO UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-emerald-300 bg-[#fbfdfa] hover:bg-white transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 font-black text-xs flex items-center justify-center">
                            UNL
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Unilever - 9 Depo Cabang (CV Mekar Jaya Perkasa)</h4>
                            <p class="text-[11px] text-slate-400">Pekalongan, Banyumas, Purwokerto, Pemalang, Weleri, Randudongkal, Purworejo, Kebumen</p>
                        </div>
                    </div>
                </div>

                <form id="formFmcgUpload" action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3" onsubmit="return handleExcelImportSubmit(event, this)">
                    @csrf
                    <input type="hidden" name="type" value="fmcg">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-emerald-400 rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel Unilever...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-emerald-700">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-emerald-500"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel Unilever (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="overwrite" id="overwriteFmcg" value="1" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                            <label for="overwriteFmcg" class="text-xs text-slate-600 font-medium cursor-pointer select-none">
                                Timpa data jika file sudah ada (<span class="font-mono text-slate-400">Overwrite</span>)
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i data-lucide="search-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                            Cek & Import Unilever
                        </button>
                    </div>
                </form>
            </div>

            <!-- AQUA (DANONE) UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-sky-300 bg-[#fbfdfa] hover:bg-white transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 font-black text-xs flex items-center justify-center">
                            AQUA
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">AQUA - CV Mekar Jaya Sentosa</h4>
                            <p class="text-[11px] text-slate-400">Laporan Laba Rugi Operasional & Neraca Resmi Distributor Danone AQUA (Sheet PL & BS)</p>
                        </div>
                    </div>
                </div>

                <form id="formAquaUpload" action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3" onsubmit="return handleExcelImportSubmit(event, this)">
                    @csrf
                    <input type="hidden" name="type" value="aqua">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-sky-400 rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel AQUA...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-sky-700">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-sky-500"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel Danone AQUA (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="overwrite" id="overwriteAqua" value="1" class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer">
                            <label for="overwriteAqua" class="text-xs text-slate-600 font-medium cursor-pointer select-none">
                                Timpa data jika file sudah ada (<span class="font-mono text-slate-400">Overwrite</span>)
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i data-lucide="search-check" class="w-3.5 h-3.5 text-sky-600"></i>
                            Cek & Import AQUA
                        </button>
                    </div>
                </form>
            </div>

            <!-- XTRANS UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-[#e2beaf] bg-[#fbfdfa] hover:bg-white transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-[#f8ede7] text-[#782f18] border border-[#edd7ce] font-black text-xs flex items-center justify-center">
                            XT
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Xtrans - PT Mekar Jaya Xtrans Jaya</h4>
                            <p class="text-[11px] text-slate-400">Laporan Laba Rugi Operasional & Neraca Konsolidasi Armada</p>
                        </div>
                    </div>
                </div>

                <form id="formXtransUpload" action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3" onsubmit="return handleExcelImportSubmit(event, this)">
                    @csrf
                    <input type="hidden" name="type" value="xtrans">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-[#e2beaf] rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel Xtrans...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-[#9c4a2f]">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-[#9c4a2f]"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel Xtrans (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="overwrite" id="overwriteXtrans" value="1" class="w-4 h-4 rounded border-slate-300 text-[#9c4a2f] focus:ring-[#9c4a2f] cursor-pointer">
                            <label for="overwriteXtrans" class="text-xs text-slate-600 font-medium cursor-pointer select-none">
                                Timpa data jika file sudah ada (<span class="font-mono text-slate-400">Overwrite</span>)
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i data-lucide="search-check" class="w-3.5 h-3.5 text-[#9c4a2f]"></i>
                            Cek & Import Xtrans
                        </button>
                    </div>
                </form>
            </div>

            <!-- CCOD UPLOAD BOX -->
            <div class="p-4 rounded-xl border border-dashed border-indigo-300 bg-[#fbfdfa] hover:bg-white transition">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 font-black text-xs flex items-center justify-center">
                            CCOD
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">CCOD - PT Mekar Jaya Sentosa Multikarya</h4>
                            <p class="text-[11px] text-slate-400">Laporan Laba Rugi 12 Cabang CCOD Heinz ABC (Pati, Semarang, Pekalongan, Tegal, Bandung, Sumedang, Salatiga, Magelang, Cirebon, Jatiwangi, Kuningan, Losarang)</p>
                        </div>
                    </div>
                </div>

                <form id="formCcodUpload" action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3" onsubmit="return handleExcelImportSubmit(event, this)">
                    @csrf
                    <input type="hidden" name="type" value="ccod">
                    <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-indigo-400 rounded-xl p-3 bg-white transition cursor-pointer group">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih file Excel CCOD...'">
                        <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-indigo-700">
                            <i data-lucide="upload-cloud" class="w-4 h-4 text-indigo-500"></i>
                            <span class="font-medium text-slate-600">Pilih file Excel CCOD (.xlsx / .xls)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="overwrite" id="overwriteCcod" value="1" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                            <label for="overwriteCcod" class="text-xs text-slate-600 font-medium cursor-pointer select-none">
                                Timpa data jika file sudah ada (<span class="font-mono text-slate-400">Overwrite</span>)
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i data-lucide="search-check" class="w-3.5 h-3.5 text-indigo-600"></i>
                            Cek & Import CCOD
                        </button>
                    </div>
                </form>
            </div>

            <!-- UNIVERSAL TEMPLATE UPLOAD BOX (FOR ANY REGISTERED / NEW BUSINESS UNIT) -->
            <div class="p-4 rounded-xl border border-dashed border-[#e2beaf] bg-[#f7f5f0] hover:bg-white transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-[#f8ede7] text-[#782f18] font-black text-xs flex items-center justify-center border border-[#e2beaf]">
                            <i data-lucide="layers" class="w-4 h-4 text-[#9c4a2f]"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-slate-900">Unit Usaha Lain / Baru (Template Universal)</h4>
                                <span class="px-2 py-0.2 text-[10px] font-bold rounded-full bg-[#f8ede7] text-[#782f18] border border-[#e2beaf]">Fleksibel</span>
                            </div>
                            <p class="text-[11px] text-slate-400">Gunakan untuk unit bisnis baru dari Master Data atau cabang mana pun dengan format template standar holding.</p>
                        </div>
                    </div>

                    <a id="btnDownloadUniversalTemplate" href="{{ route('finance.master.units.template', $businessUnits[0]['code'] ?? 'fmcg') }}"
                       class="px-3 py-1.5 rounded-lg bg-white hover:bg-[#f8ede7] text-[#782f18] border border-[#e2beaf] text-xs font-bold transition flex items-center gap-1.5 shadow-2xs self-start sm:self-auto cursor-pointer"
                       title="Unduh format template Excel resmi">
                        <i data-lucide="download" class="w-3.5 h-3.5 text-[#9c4a2f]"></i>
                        Unduh Template Excel
                    </a>
                </div>

                <form id="formUniversalUpload" action="{{ route('finance.sync.excel') }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3" onsubmit="return handleExcelImportSubmit(event, this)">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Pilih Unit Usaha Tujuan</label>
                            <select name="type" required 
                                onchange="document.getElementById('btnDownloadUniversalTemplate').href = '/master/units/' + this.value + '/template'"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[#9c4a2f] bg-white">
                                @foreach($businessUnits as $bu)
                                <option value="{{ $bu['code'] }}">{{ $bu['name'] }} ({{ strtoupper($bu['code']) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2 space-y-1">
                            <label class="text-xs font-bold text-slate-700">Pilih Berkas Laporan Keuangan</label>
                            <div class="relative flex items-center justify-center border-2 border-dashed border-slate-200 hover:border-[#e2beaf] rounded-xl p-2 bg-white transition cursor-pointer group">
                                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="this.nextElementSibling.innerText = this.files[0]?.name || 'Pilih berkas template Excel...'">
                                <div class="text-center text-xs text-slate-500 flex items-center gap-2 pointer-events-none group-hover:text-[#782f18] py-0.5">
                                    <i data-lucide="upload-cloud" class="w-4 h-4 text-[#9c4a2f]"></i>
                                    <span class="font-medium text-slate-600">Pilih berkas Excel (.xlsx / .xls)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1 border-t border-slate-100">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="overwrite" id="overwriteUniversal" value="1" class="w-4 h-4 rounded border-slate-300 text-[#9c4a2f] focus:ring-[#9c4a2f] cursor-pointer">
                            <label for="overwriteUniversal" class="text-xs text-slate-600 font-medium cursor-pointer select-none">
                                Timpa data jika file sudah ada (<span class="font-mono text-slate-400">Overwrite</span>)
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#9c4a2f] hover:bg-[#853c23] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i data-lucide="search-check" class="w-3.5 h-3.5 text-slate-950"></i>
                            Cek & Import Data Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- INGESTION & SYNC AUDIT LOG TABLE -->
    <div id="riwayat-import" class="bg-white rounded-2xl border border-[#e6e1d5] shadow-xs overflow-hidden scroll-mt-6">
        <div class="p-5 border-b border-[#e6e1d5] flex items-center justify-between flex-wrap gap-3 bg-white">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                    <i data-lucide="history" class="w-4 h-4 text-[#9c4a2f]"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Riwayat Aktivitas Ingestion & Sinkronisasi</h3>
                    <p class="text-xs text-slate-400">Log audit seluruh penyerapan data dari database dan upload berkas Excel.</p>
                </div>
            </div>

            <!-- Action Controls: Bulk Delete & Total Summary -->
            <div class="flex items-center gap-3">
                <div id="bulkActionBar" class="hidden flex items-center gap-2">
                    <span id="selectedCountBadge" class="text-xs font-bold bg-[#f8ede7] text-[#782f18] border border-[#edd7ce] px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#f8ede7]0 animate-pulse"></span>
                        <span id="selectedCountText">0 riwayat dipilih</span>
                    </span>
                    <button type="button" onclick="openBulkDeleteModal()" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        <span>Hapus Terpilih</span>
                    </button>
                    <button type="button" onclick="resetLogSelections()" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                </div>
                <span id="logSummaryCount" class="text-xs font-medium text-slate-400">Menampilkan {{ count($logs) }} aktivitas terakhir</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f7f5f0] text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-[#e6e1d5]">
                        <th class="py-3 px-4 w-10 text-center">
                            <input type="checkbox" id="selectAllLogs" onchange="toggleSelectAllLogs(this)" class="w-4 h-4 rounded border-slate-300 text-[#9c4a2f] focus:ring-[#9c4a2f] cursor-pointer" title="Pilih Semua Riwayat">
                        </th>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Unit Bisnis</th>
                        <th class="py-3 px-4">Metode / Sumber</th>
                        <th class="py-3 px-4">Berkas / Tabel Referensi</th>
                        <th class="py-3 px-4 text-right">Data Terserap</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-[#fbfdfa] transition" id="logRow_{{ $log->id }}">
                        <td class="py-3 px-4 text-center">
                            <input type="checkbox" name="log_ids[]" value="{{ $log->id }}"
                                data-id="{{ $log->id }}"
                                data-filename="{{ $log->filename ?? '-' }}"
                                data-unit="{{ strtoupper($log->business_unit_code) }}"
                                data-rows="{{ $log->records_count }}"
                                data-type="{{ $log->source_type }}"
                                onchange="updateSelectedLogsCount()"
                                class="log-checkbox w-4 h-4 rounded border-slate-300 text-[#9c4a2f] focus:ring-[#9c4a2f] cursor-pointer"
                                title="Pilih riwayat {{ $log->filename }}">
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="py-3 px-4 font-bold uppercase text-slate-800">
                            <span class="px-2 py-0.5 rounded text-[11px] 
                                @if(str_contains(strtolower($log->business_unit_code), 'spbu')) bg-rose-50 text-rose-700 border border-rose-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'iqos') || str_contains(strtolower($log->business_unit_code), 'retail')) bg-teal-50 text-teal-800 border border-teal-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'fmcg')) bg-sky-50 text-sky-700 border border-sky-200
                                @elseif(str_contains(strtolower($log->business_unit_code), 'xtrans')) bg-[#f8ede7] text-[#9c4a2f] border border-[#edd7ce]
                                @elseif(str_contains(strtolower($log->business_unit_code), 'ccod')) bg-indigo-50 text-indigo-700 border border-indigo-200
                                @else bg-slate-100 text-slate-700 border border-slate-200 @endif">
                                {{ $log->business_unit_code }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            @if($log->source_type === 'database_sync')
                                <span class="inline-flex items-center gap-1 text-slate-800 font-semibold whitespace-nowrap">
                                    <i data-lucide="database" class="w-3.5 h-3.5 text-[#9c4a2f]"></i> Database Sync
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-slate-800 font-semibold whitespace-nowrap">
                                    <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5 text-[#9c4a2f]"></i> Excel Upload
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-600">
                            {{ $log->filename ?? '-' }}
                        </td>
                        <td class="py-3 px-4 text-right font-black text-slate-900 font-mono whitespace-nowrap">
                            {{ number_format($log->records_count, 0, ',', '.') }} rows
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            @if($log->status === 'success')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#f8ede7] text-[#782f18] border border-[#edd7ce] inline-flex items-center gap-1">
                                    <i data-lucide="check-circle" class="w-3 h-3 text-[#9c4a2f]"></i> Berhasil
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 inline-flex items-center gap-1" title="{{ $log->details }}">
                                    <i data-lucide="alert-circle" class="w-3 h-3 text-rose-600"></i> Gagal
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <button type="button"
                                onclick="openSingleDeleteModal({{ $log->id }}, '{{ addslashes($log->filename ?? '-') }}', '{{ strtoupper($log->business_unit_code) }}', {{ $log->records_count }}, '{{ $log->source_type }}')"
                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition inline-flex items-center justify-center cursor-pointer" title="Hapus Riwayat">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400">
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

<!-- PREVIEW & VERIFIKASI IMPORT MODAL (IDENTIK SISTEM SIR-IQOS) -->
<div id="modalExcelPreview" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh]">
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf] flex items-center justify-center">
                    <i data-lucide="file-check-2" class="w-5 h-5 text-[#9c4a2f]"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Verifikasi & Pratinjau Berkas Excel</h3>
                    <p class="text-[11px] text-slate-400">Pengecekan kecocokan format, duplikasi, dan ringkasan data sebelum disimpan ke database</p>
                </div>
            </div>
            <button type="button" onclick="closePreviewModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Body Scrollable -->
        <div class="p-6 overflow-y-auto space-y-4 text-xs">
            
            <!-- 1. LOADING STATE -->
            <div id="previewLoadingState" class="py-12 flex flex-col items-center justify-center space-y-3 text-center">
                <div class="w-10 h-10 border-3 border-[#edd7ce] border-t-[#9c4a2f] rounded-full animate-spin"></div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm">Menganalisis Berkas Excel...</h4>
                    <p class="text-xs text-slate-400 mt-1 max-w-md">Membaca struktur sheet, mendeteksi tipe format, memeriksa histori duplikasi, dan mengekstrak ringkasan akun finansial.</p>
                </div>
            </div>

            <!-- 2. ERROR STATE (MISMATCH / UNKNOWN / INVALID) -->
            <div id="previewErrorState" class="hidden space-y-4 py-2">
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3">
                    <div class="p-1.5 rounded-lg bg-rose-100 text-rose-700 shrink-0 mt-0.5">
                        <i data-lucide="alert-octagon" class="w-5 h-5"></i>
                    </div>
                    <div class="space-y-1">
                        <h4 class="font-bold text-rose-900 text-sm" id="previewErrorTitle">Validasi Format Gagal</h4>
                        <p class="text-xs text-rose-700 leading-relaxed" id="previewErrorMessage"></p>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-slate-600 text-xs">
                    <span class="font-bold text-slate-800">Petunjuk Solusi:</span>
                    <ul class="list-disc pl-5 space-y-1 text-slate-500">
                        <li>Pastikan Anda mengunggah berkas pada kotak unit usaha yang sesuai (MJS FMCG, MJX Xtrans, atau Unit Usaha Lain).</li>
                        <li>Gunakan format template resmi holding yang dapat diunduh pada tombol <span class="font-semibold text-slate-700">"Unduh Template Excel"</span>.</li>
                        <li>Pastikan berkas Excel tidak dalam keadaan terkunci atau memiliki kata sandi.</li>
                    </ul>
                </div>
            </div>

            <!-- 3. SUCCESS / PREVIEW STATE -->
            <div id="previewSuccessState" class="hidden space-y-4">
                <!-- File Card -->
                <div class="p-3.5 rounded-xl bg-[#f7f5f0] border border-[#e6e1d5] flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-[#9c4a2f] shadow-2xs shrink-0">
                            <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                        </div>
                        <div class="truncate">
                            <p class="font-bold text-slate-900 text-xs truncate" id="previewFileName">-</p>
                            <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                                Ukuran: <span id="previewFileSize" class="text-slate-600 font-semibold">-</span> • 
                                Hash: <span id="previewFileHash" class="text-slate-500 font-mono">-</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-slate-400 uppercase">Tujuan:</span>
                        <span id="previewTargetUnitBadge" class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            -
                        </span>
                    </div>
                </div>

                <!-- Validation & Duplicate Status Alerts -->
                <div class="space-y-2">
                    <!-- Format Validation Badge -->
                    <div id="previewFormatStatusAlert" class="p-3 rounded-xl flex items-center gap-2.5 text-xs font-semibold">
                        <i id="previewFormatStatusIcon" data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                        <span id="previewFormatStatusText">-</span>
                    </div>

                    <!-- Duplicate Warning Alert -->
                    <div id="previewDuplicateAlert" class="hidden p-3 rounded-xl bg-[#f8ede7] border border-[#edd7ce] text-[#782f18] flex items-start gap-2.5 text-xs">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-[#9c4a2f] shrink-0 mt-0.5"></i>
                        <div class="space-y-0.5">
                            <span class="font-bold text-[#782f18]">Peringatan Duplikasi Berkas Terdeteksi</span>
                            <p id="previewDuplicateMessage" class="text-[11px] text-[#9c4a2f] leading-relaxed"></p>
                        </div>
                    </div>
                </div>

                <!-- 4 Summary Metrics Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <div class="p-3 rounded-xl bg-white border border-slate-200 shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Lembar Kerja</span>
                        <p class="text-base font-black text-slate-900 mt-0.5 font-mono" id="previewSheetsCount">0</p>
                        <span class="text-[10px] text-slate-400">Sheet terdeteksi</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white border border-slate-200 shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Rentang Periode</span>
                        <p class="text-xs font-bold text-slate-800 mt-1 truncate" id="previewPeriodRange">-</p>
                        <span class="text-[10px] text-slate-400 font-mono"><span id="previewPeriodsCount">0</span> Periode</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white border border-slate-200 shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Estimasi Rekor</span>
                        <p class="text-base font-black text-[#9c4a2f] mt-0.5 font-mono" id="previewEstimatedRecords">0</p>
                        <span class="text-[10px] text-slate-400">Baris data</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white border border-slate-200 shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Pendapatan Terakhir</span>
                        <p class="text-xs font-black text-slate-900 mt-1 font-mono truncate" id="previewTotalRevenue">-</p>
                        <span class="text-[10px] text-slate-400" id="previewLatestPeriodLabel">Periode akhir</span>
                    </div>
                </div>

                <!-- Sheets list preview -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Daftar Sheet Excel</span>
                        <span class="text-[10px] text-slate-400" id="previewSheetsBadge"></span>
                    </div>
                    <div id="previewSheetsContainer" class="flex flex-wrap gap-1.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 max-h-20 overflow-y-auto">
                        <!-- Populated dynamically -->
                    </div>
                </div>

                <!-- Sample Rows Table -->
                <div class="space-y-1.5">
                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Sampel Akun Finansial Terbaca</span>
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="py-2 px-3">Kode Akun</th>
                                    <th class="py-2 px-3">Uraian / Nama Akun</th>
                                    <th class="py-2 px-3 text-right">Nilai Periode</th>
                                </tr>
                            </thead>
                            <tbody id="previewSampleRowsBody" class="divide-y divide-slate-100 bg-white font-medium text-slate-700">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Overwrite Checkbox in Modal -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between flex-wrap gap-2">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="modalOverwriteCheckbox" class="w-4 h-4 rounded border-slate-300 text-[#9c4a2f] focus:ring-[#9c4a2f] cursor-pointer">
                        <span class="text-xs font-semibold text-slate-700">
                            Timpa data jika berkas sudah ada (<span class="font-mono text-slate-400 font-normal">Overwrite</span>)
                        </span>
                    </label>
                    <span class="text-[11px] text-slate-400">Centang opsi ini jika Anda ingin memperbarui data sebelumnya</span>
                </div>

            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <button type="button" onclick="closePreviewModal()" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-bold text-xs transition cursor-pointer">
                Batal / Ganti Berkas
            </button>
            <button type="button" id="btnConfirmImport" onclick="confirmAndSubmit()" class="px-5 py-2 rounded-xl bg-[#9c4a2f] hover:bg-[#853c23] text-white font-bold text-xs transition flex items-center gap-2 shadow-xs cursor-pointer">
                <i data-lucide="check" class="w-4 h-4 text-slate-950"></i>
                <span id="btnConfirmText">Konfirmasi & Simpan ke Database</span>
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentActiveForm = null;

    function handleExcelImportSubmit(event, form) {
        if (form.dataset.confirmed === "true") {
            return true;
        }

        event.preventDefault();

        const fileInput = form.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert("Silakan pilih berkas Excel terlebih dahulu.");
            return false;
        }

        currentActiveForm = form;
        openPreviewModal();

        const formData = new FormData(form);

        fetch('{{ route("finance.sync.preview") }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                showPreviewError(data.message || data.match_error || 'Gagal memverifikasi berkas Excel.');
                return;
            }
            renderPreviewData(data);
        })
        .catch(err => {
            showPreviewError('Terjadi kesalahan jaringan atau berkas terlalu besar: ' + err.message);
        });

        return false;
    }

    function openPreviewModal() {
        const modal = document.getElementById('modalExcelPreview');
        modal.classList.remove('hidden');
        document.getElementById('previewLoadingState').classList.remove('hidden');
        document.getElementById('previewErrorState').classList.add('hidden');
        document.getElementById('previewSuccessState').classList.add('hidden');
        document.getElementById('btnConfirmImport').classList.add('hidden');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closePreviewModal() {
        const modal = document.getElementById('modalExcelPreview');
        modal.classList.add('hidden');
        if (currentActiveForm) {
            currentActiveForm.dataset.confirmed = "";
        }
    }

    function showPreviewError(message) {
        document.getElementById('previewLoadingState').classList.add('hidden');
        document.getElementById('previewSuccessState').classList.add('hidden');
        const errState = document.getElementById('previewErrorState');
        errState.classList.remove('hidden');
        document.getElementById('previewErrorMessage').innerText = message;
        document.getElementById('btnConfirmImport').classList.add('hidden');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function renderPreviewData(data) {
        document.getElementById('previewLoadingState').classList.add('hidden');
        document.getElementById('previewErrorState').classList.add('hidden');
        const successState = document.getElementById('previewSuccessState');
        successState.classList.remove('hidden');

        // Fill file info
        document.getElementById('previewFileName').innerText = data.filename;
        document.getElementById('previewFileSize').innerText = data.file_size;
        document.getElementById('previewFileHash').innerText = (data.file_hash || '').substring(0, 16) + '...';
        document.getElementById('previewTargetUnitBadge').innerText = data.target_label;

        // Type Match Alert
        const formatAlert = document.getElementById('previewFormatStatusAlert');
        const formatIcon = document.getElementById('previewFormatStatusIcon');
        const formatText = document.getElementById('previewFormatStatusText');

        if (data.is_type_match) {
            formatAlert.className = 'p-3 rounded-xl flex items-center gap-2.5 text-xs font-semibold bg-[#f8ede7] text-[#782f18] border border-[#e2beaf]';
            formatIcon.className = 'w-4 h-4 shrink-0 text-[#9c4a2f]';
            formatText.innerText = 'Format Berkas Sesuai: Terverifikasi untuk ' + data.target_label;
        } else {
            formatAlert.className = 'p-3 rounded-xl flex items-center gap-2.5 text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            formatIcon.className = 'w-4 h-4 shrink-0 text-rose-600';
            formatText.innerText = data.match_error || ('Format Berbeda: Terdeteksi ' + data.detected_label);
        }

        // Duplicate Alert
        const dupAlert = document.getElementById('previewDuplicateAlert');
        const modalOverwrite = document.getElementById('modalOverwriteCheckbox');
        
        // Sync modal overwrite checkbox with current form
        const originalOverwrite = currentActiveForm ? currentActiveForm.querySelector('input[name="overwrite"]') : null;
        modalOverwrite.checked = originalOverwrite ? originalOverwrite.checked : false;

        if (data.is_duplicate) {
            dupAlert.classList.remove('hidden');
            document.getElementById('previewDuplicateMessage').innerText = data.duplicate_info ? data.duplicate_info.message : 'File ini sudah pernah diimpor sebelumnya.';
            // Otomatis centang overwrite jika duplikasi terdeteksi agar pengguna dapat langsung memperbarui
            modalOverwrite.checked = true;
        } else {
            dupAlert.classList.add('hidden');
        }

        // Metrics
        const s = data.summary || {};
        document.getElementById('previewSheetsCount').innerText = s.sheets_count || (data.sheets ? data.sheets.length : 0);
        document.getElementById('previewPeriodRange').innerText = s.period_range || '-';
        document.getElementById('previewPeriodsCount').innerText = s.periods_count || 0;
        document.getElementById('previewEstimatedRecords').innerText = Number(s.estimated_records || 0).toLocaleString('id-ID');
        document.getElementById('previewTotalRevenue').innerText = s.total_revenue_formatted || '-';
        document.getElementById('previewLatestPeriodLabel').innerText = s.latest_period ? ('Periode ' + s.latest_period) : 'Periode akhir';

        // Sheets list
        const sheetsContainer = document.getElementById('previewSheetsContainer');
        sheetsContainer.innerHTML = '';
        (data.sheets || []).forEach(sh => {
            const span = document.createElement('span');
            span.className = 'px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-mono text-[10px] font-semibold';
            span.innerText = sh;
            sheetsContainer.appendChild(span);
        });
        document.getElementById('previewSheetsBadge').innerText = (data.sheets || []).length + ' Sheet';

        // Sample rows table
        const sampleBody = document.getElementById('previewSampleRowsBody');
        sampleBody.innerHTML = '';
        const sampleRows = s.sample_rows || [];
        if (sampleRows.length === 0) {
            sampleBody.innerHTML = '<tr><td colspan="3" class="py-3 px-3 text-center text-slate-400">Tidak ada sampel baris akun yang terbaca.</td></tr>';
        } else {
            sampleRows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="py-2 px-3 font-mono font-semibold text-slate-600 whitespace-nowrap">${r.code || '-'}</td>
                    <td class="py-2 px-3 text-slate-800">${r.name}</td>
                    <td class="py-2 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">${r.formatted_amount || ('Rp ' + Number(r.amount).toLocaleString('id-ID'))}</td>
                `;
                sampleBody.appendChild(tr);
            });
        }

        // Confirm button
        const btnConfirm = document.getElementById('btnConfirmImport');
        if (data.can_import) {
            btnConfirm.classList.remove('hidden');
            btnConfirm.disabled = false;
            document.getElementById('btnConfirmText').innerText = 'Konfirmasi & Simpan ke Database';
        } else {
            btnConfirm.classList.add('hidden');
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function confirmAndSubmit() {
        if (!currentActiveForm) return;

        // Sync modal overwrite back to original form
        const modalOverwrite = document.getElementById('modalOverwriteCheckbox');
        let originalOverwrite = currentActiveForm.querySelector('input[name="overwrite"]');
        if (originalOverwrite) {
            originalOverwrite.checked = modalOverwrite.checked;
        }

        const btnConfirm = document.getElementById('btnConfirmImport');
        btnConfirm.disabled = true;
        document.getElementById('btnConfirmText').innerText = 'Menyimpan ke Database...';

        currentActiveForm.dataset.confirmed = "true";
        currentActiveForm.submit();
    }

    // Bulk Selection & Delete Modal Logic (Ala SIR-IQOS)
    function toggleSelectAllLogs(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.log-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });
        updateSelectedLogsCount();
    }

    function updateSelectedLogsCount() {
        const checkboxes = document.querySelectorAll('.log-checkbox:checked');
        const count = checkboxes.length;
        const totalCheckboxes = document.querySelectorAll('.log-checkbox').length;
        const masterCheckbox = document.getElementById('selectAllLogs');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const selectedCountText = document.getElementById('selectedCountText');
        const logSummaryCount = document.getElementById('logSummaryCount');

        if (masterCheckbox) {
            masterCheckbox.checked = (totalCheckboxes > 0 && count === totalCheckboxes);
            masterCheckbox.indeterminate = (count > 0 && count < totalCheckboxes);
        }

        if (count > 0) {
            if (bulkActionBar) bulkActionBar.classList.remove('hidden');
            if (selectedCountText) selectedCountText.innerText = count + ' riwayat dipilih';
            if (logSummaryCount) logSummaryCount.classList.add('hidden');
        } else {
            if (bulkActionBar) bulkActionBar.classList.add('hidden');
            if (logSummaryCount) logSummaryCount.classList.remove('hidden');
        }
    }

    function resetLogSelections() {
        const checkboxes = document.querySelectorAll('.log-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const masterCheckbox = document.getElementById('selectAllLogs');
        if (masterCheckbox) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        }
        updateSelectedLogsCount();
    }

    function openSingleDeleteModal(id, filename, unit, rows, type) {
        const modal = document.getElementById('modalDeleteConfirm');
        const form = document.getElementById('deleteConfirmForm');
        const title = document.getElementById('deleteModalTitle');
        const warning = document.getElementById('deleteModalWarningText');
        const list = document.getElementById('deleteModalItemList');
        const totalRowsEl = document.getElementById('deleteModalTotalRows');
        const hiddenInputs = document.getElementById('deleteModalHiddenInputs');
        const submitBtnText = document.getElementById('deleteModalSubmitBtnText');

        form.action = `/sync/log/${id}`;
        hiddenInputs.innerHTML = '';
        title.innerText = 'Konfirmasi Hapus Riwayat Import';
        submitBtnText.innerText = 'Ya, Hapus Data Ini';

        if (type === 'excel_upload') {
            warning.innerText = 'PERINGATAN: Berkas ini diunggah via Excel. Menghapus riwayat ini juga akan menghapus ' + Number(rows).toLocaleString('id-ID') + ' baris data transaksi finansial terkait dari database konsolidasi (P&L & Neraca).';
        } else {
            warning.innerText = 'Menghapus log audit sinkronisasi database untuk unit ' + unit + '. Data live di database cabang tidak akan terpengaruh.';
        }

        list.innerHTML = `
            <div class="flex items-center justify-between p-2.5 rounded-lg bg-white border border-slate-200 text-xs">
                <div class="flex items-center gap-2 truncate min-w-0">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">${unit}</span>
                    <span class="font-mono text-slate-800 truncate" title="${filename}">${filename}</span>
                </div>
                <span class="font-mono font-bold text-slate-900 shrink-0 ml-2">${Number(rows).toLocaleString('id-ID')} rows</span>
            </div>
        `;

        totalRowsEl.innerText = Number(rows).toLocaleString('id-ID') + ' baris data';

        modal.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function openBulkDeleteModal() {
        const selected = Array.from(document.querySelectorAll('.log-checkbox:checked'));
        if (selected.length === 0) return;

        const modal = document.getElementById('modalDeleteConfirm');
        const form = document.getElementById('deleteConfirmForm');
        const title = document.getElementById('deleteModalTitle');
        const warning = document.getElementById('deleteModalWarningText');
        const list = document.getElementById('deleteModalItemList');
        const totalRowsEl = document.getElementById('deleteModalTotalRows');
        const hiddenInputs = document.getElementById('deleteModalHiddenInputs');
        const submitBtnText = document.getElementById('deleteModalSubmitBtnText');

        form.action = "{{ route('finance.sync.bulk-delete') }}";
        hiddenInputs.innerHTML = '';
        title.innerText = 'Konfirmasi Hapus ' + selected.length + ' Riwayat Terpilih';
        submitBtnText.innerText = 'Ya, Hapus ' + selected.length + ' Riwayat Terpilih';

        let totalRows = 0;
        list.innerHTML = '';

        selected.forEach(cb => {
            const id = cb.value;
            const filename = cb.dataset.filename || '-';
            const unit = cb.dataset.unit || '-';
            const rows = parseInt(cb.dataset.rows || 0, 10);
            totalRows += rows;

            // append hidden input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            hiddenInputs.appendChild(input);

            // append list item
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-2.5 rounded-lg bg-white border border-slate-200 text-xs';
            item.innerHTML = `
                <div class="flex items-center gap-2 truncate min-w-0">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">${unit}</span>
                    <span class="font-mono text-slate-800 truncate" title="${filename}">${filename}</span>
                </div>
                <span class="font-mono font-bold text-slate-900 shrink-0 ml-2">${rows.toLocaleString('id-ID')} rows</span>
            `;
            list.appendChild(item);
        });

        warning.innerText = 'PERINGATAN: Anda akan menghapus ' + selected.length + ' riwayat sekaligus. Seluruh transaksi finansial terkait berkas-berkas di atas (' + totalRows.toLocaleString('id-ID') + ' baris data) akan dibersihkan dari database konsolidasi.';
        totalRowsEl.innerText = totalRows.toLocaleString('id-ID') + ' baris data';

        modal.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeDeleteModal() {
        const modal = document.getElementById('modalDeleteConfirm');
        if (modal) modal.classList.add('hidden');
    }
</script>

<!-- MODAL KONFIRMASI HAPUS RIWAYAT & ROLLBACK DATA (ALA SIR-IQOS) -->
<div id="modalDeleteConfirm" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-rose-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center border border-rose-200 shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm" id="deleteModalTitle">Konfirmasi Hapus Data Import</h3>
                    <p class="text-[11px] text-slate-500" id="deleteModalSubtitle">Peringatan penghapusan riwayat dan rollback data finansial</p>
                </div>
            </div>
            <button type="button" onclick="closeDeleteModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-4 text-xs">
            <!-- Warning Box -->
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-rose-900">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span>Tindakan ini permanen dan tidak dapat dibatalkan!</span>
                </div>
                <p class="text-[11px] text-rose-700 leading-relaxed" id="deleteModalWarningText">
                    Seluruh data transaksi finansial terkait riwayat yang dipilih akan dihapus dari sistem konsolidasi (P&L dan Neraca).
                </p>
            </div>

            <!-- Detail List of Items to be deleted -->
            <div class="space-y-2">
                <span class="font-bold text-slate-700 text-xs">Daftar Berkas yang Akan Dihapus:</span>
                <div id="deleteModalItemList" class="max-h-44 overflow-y-auto space-y-1.5 border border-slate-200 rounded-xl p-2.5 bg-slate-50/50">
                    <!-- Dynamic Items injected via JS -->
                </div>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 font-mono text-[11px]">
                <span>Total Data Finansial Terdampak:</span>
                <span id="deleteModalTotalRows" class="font-bold text-slate-900 font-sans">0 baris</span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold transition cursor-pointer">
                Batal
            </button>
            <form id="deleteConfirmForm" method="POST" action="">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div id="deleteModalHiddenInputs"></div>
                <button type="submit" id="deleteModalSubmitBtn" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span id="deleteModalSubmitBtnText">Ya, Hapus Data Sekarang</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
