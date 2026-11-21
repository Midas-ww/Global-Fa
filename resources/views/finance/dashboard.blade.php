@extends('layouts.app')

@section('title', 'Dashboard Overview')

@section('content')
<div class="space-y-6 pb-12 w-full">

    <!-- 1. HEADER ROW -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                Overview
            </h1>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Period Selector -->
            <form action="{{ route('finance.dashboard') }}" method="GET" class="flex items-center">
                <div class="flex items-center gap-1.5 px-3 py-2 bg-white border border-[#e6e1d5] rounded-xl shadow-2xs">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#9c4a2f] shrink-0"></i>
                    <label for="periodSelectDash" class="text-xs font-semibold text-slate-500">Periode:</label>
                    <select name="period" id="periodSelectDash" onchange="this.form.submit()" class="text-xs font-bold font-mono bg-transparent text-slate-900 focus:outline-none cursor-pointer">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $selectedPeriod === $p ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($p)->translatedFormat('F Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <a href="{{ route('finance.sync') }}"
               class="px-3.5 py-2 bg-white hover:bg-[#f8ede7]/60 text-slate-700 border border-[#e6e1d5] rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Data Ingestion</span>
            </a>
            <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}"
               class="px-3.5 py-2 bg-[#9c4a2f] hover:bg-[#853c23] text-white font-semibold border border-[#9c4a2f] rounded-xl text-xs transition-all flex items-center gap-1.5 shadow-xs cursor-pointer">
                <i data-lucide="file-text" class="w-3.5 h-3.5 text-white"></i>
                <span>Laporan P&L</span>
            </a>
        </div>
    </div>

    <!-- 2. FOUR TOP EXECUTIVE KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Card 1: Total Revenue -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:shadow-md hover:border-[#e2beaf] transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] flex items-center justify-center border border-[#e2beaf]">
                        <i data-lucide="dollar-sign" class="w-4 h-4 text-[#9c4a2f]"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Revenue</span>
                </div>
                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}" class="text-slate-400 hover:text-[#9c4a2f] p-1 rounded-lg hover:bg-[#f8ede7] transition-colors">
                    <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                </a>
            </div>

            <div class="mt-4 flex items-baseline justify-between flex-wrap gap-2">
                <div class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight font-mono">
                    Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf]">
                        +100%
                    </span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Konsolidasi {{ count($summary['by_business_unit'] ?? []) }} unit bisnis holding</p>
        </div>

        <!-- Card 2: HPP & Beban Usaha (Expenses) -->
        @php
            $totalExpenses = ($summary['kpis']['cogs'] ?? 0) + ($summary['kpis']['opex'] ?? 0);
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:shadow-md hover:border-slate-300 transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center border border-slate-200">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">HPP & Beban Usaha</span>
                </div>
                <a href="{{ route('finance.sync') }}" class="text-slate-400 hover:text-[#9c4a2f] p-1 rounded-lg hover:bg-[#f8ede7] transition-colors">
                    <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                </a>
            </div>

            <div class="mt-4 flex items-baseline justify-between flex-wrap gap-2">
                <div class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight font-mono">
                    Rp {{ number_format($totalExpenses, 0, ',', '.') }}
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        Rasio {{ $summary['kpis']['opex_ratio'] ?? 0 }}%
                    </span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">HPP: Rp {{ number_format($summary['kpis']['cogs'] ?? 0, 0, ',', '.') }}</p>
        </div>

        <!-- Card 3: Laba Kotor (Gross Profit) -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:shadow-md hover:border-[#e2beaf] transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] flex items-center justify-center border border-[#e2beaf]">
                        <i data-lucide="trending-up" class="w-4 h-4 text-[#9c4a2f]"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Laba Kotor (GP)</span>
                </div>
                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}" class="text-slate-400 hover:text-[#9c4a2f] p-1 rounded-lg hover:bg-[#f8ede7] transition-colors">
                    <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                </a>
            </div>

            <div class="mt-4 flex items-baseline justify-between flex-wrap gap-2">
                <div class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight font-mono">
                    Rp {{ number_format($summary['kpis']['gross_profit'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf]">
                        Margin {{ $summary['kpis']['gross_margin_percentage'] ?? 0 }}%
                    </span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Laba kotor sebelum beban operasional</p>
        </div>

        <!-- Card 4: Net Profit (Laba Bersih) -->
        @php
            $net = $summary['kpis']['net_profit'] ?? 0;
            $isNetPositive = $net >= 0;
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs hover:shadow-md hover:border-[#e2beaf] transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-[#9c4a2f]/10 text-[#782f18] flex items-center justify-center border border-[#e2beaf]">
                        <i data-lucide="landmark" class="w-4 h-4 text-[#9c4a2f]"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Laba Bersih (Net)</span>
                </div>
                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}" class="text-slate-400 hover:text-[#9c4a2f] p-1 rounded-lg hover:bg-[#f8ede7] transition-colors">
                    <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                </a>
            </div>

            <div class="mt-4 flex items-baseline justify-between flex-wrap gap-2">
                <div class="text-xl sm:text-2xl font-black {{ $isNetPositive ? 'text-[#445e3d]' : 'text-slate-700' }} tracking-tight font-mono">
                    Rp {{ number_format($net, 0, ',', '.') }}
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf]">
                        {{ $isNetPositive ? '+' : '' }}{{ $summary['kpis']['net_margin_percentage'] ?? 0 }}%
                    </span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Laba bersih konsolidasi grup</p>
        </div>

    </div>

    <!-- 3. DUA GRAFIK UTAMA ALA SIR-IQOS: FINANCIAL TREND (KIRI) + DISTRIBUSI DOUGHNUT (KANAN) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
        
        <!-- FINANCIAL CHART: KOLOM KIRI (7 Kolom) -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-[#edf0ee] p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div>
                <!-- Header with Switcher Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>Tren Performa Finansial Holding</span>
                        </h2>
                        <p class="text-slate-500 text-xs mt-0.5">
                            Pergerakan omzet, beban pokok (HPP), dan laba bersih antar periode
                        </p>
                    </div>

                    <!-- Type Switcher: Batang vs Garis Area -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <div class="inline-flex items-center p-0.5 bg-slate-100 rounded-lg border border-slate-200 text-[11px] font-semibold">
                            <button
                                type="button"
                                onclick="setFinancialChartType('bar')"
                                id="btnChartBar"
                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs"
                            >
                                <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>
                                <span>Batang</span>
                            </button>
                            <button
                                type="button"
                                onclick="setFinancialChartType('line')"
                                id="btnChartLine"
                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800"
                            >
                                <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
                                <span>Garis Tren</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Legend Indicators (Earth Tone Palette) -->
                <div class="mt-3 flex flex-wrap items-center gap-2.5 text-xs font-semibold">
                    <span class="inline-flex items-center gap-1.5 text-[#782f18] bg-[#f8ede7] px-2.5 py-0.5 rounded-md border border-[#e2beaf]">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#9c4a2f]"></span>
                        Omzet Penjualan
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[#6e4620] bg-[#fbf3eb] px-2.5 py-0.5 rounded-md border border-[#edd7c4]">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#c28b5b]"></span>
                        HPP (COGS)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[#4e3d2c] bg-[#f5f1ec] px-2.5 py-0.5 rounded-md border border-[#dfd6cd]">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#786048]"></span>
                        Beban OPEX
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[#2d4529] bg-[#edf3ec] px-2.5 py-0.5 rounded-md border border-[#c4d8c1]">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#445e3d]"></span>
                        Laba Bersih
                    </span>
                </div>

                <!-- Micro Stats Strip -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-2.5 px-3 bg-[#f7f5f0] rounded-xl border border-[#e6e1d5] my-3.5 text-xs">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Total Omzet Periode</span>
                        <span class="font-bold text-slate-900 font-mono text-xs sm:text-sm">Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Laba Bersih Konsolidasi</span>
                        <span class="font-bold {{ ($summary['kpis']['net_profit'] ?? 0) >= 0 ? 'text-[#445e3d]' : 'text-slate-700' }} font-mono text-xs sm:text-sm">
                            Rp {{ number_format($summary['kpis']['net_profit'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                    <div>
                        @php
                            $avgOmzet = count($summary['monthly_trends'] ?? []) > 0 ? array_sum(array_column($summary['monthly_trends'], 'revenue')) / count($summary['monthly_trends']) : 0;
                        @endphp
                        <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Rata-rata/Bulan ({{ count($summary['monthly_trends']) }} Periode)</span>
                        <span class="font-bold text-[#782f18] font-mono text-xs sm:text-sm">Rp {{ number_format($avgOmzet, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Canvas Container (h-72 sm:h-80) -->
                <div class="relative w-full h-72 sm:h-80">
                    <canvas id="financialTrendChart"></canvas>
                </div>
            </div>

            <div class="pt-3 border-t border-[#e6e1d5] flex items-center justify-between text-xs text-slate-500 mt-2">
                <span>Data historis 12 periode pembukuan grup holding</span>
                <span class="font-semibold text-slate-700 font-mono">Periode Aktif: {{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('F Y') }}</span>
            </div>
        </div>

        <!-- DOUGHNUT BREAKDOWN: KOLOM KANAN (5 Kolom) -->
        <div class="lg:col-span-5 bg-white rounded-2xl border border-[#e6e1d5] p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div>
                <!-- Header -->
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Distribusi Unit Bisnis Holding</h2>
                        <p class="text-slate-500 text-xs mt-0.5">Proporsi kontribusi omzet per pilar usaha</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf]">
                        {{ count($summary['by_business_unit'] ?? []) }} Pilar Usaha
                    </span>
                </div>

                <!-- Doughnut + Breakdown Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-5 items-center mt-3">
                    <!-- Doughnut Chart Container with Center Summary -->
                    <div class="sm:col-span-5 relative h-72 sm:h-80 flex items-center justify-center">
                        <canvas id="buDoughnutChart"></canvas>
                        <!-- Center Summary Inside Doughnut Hole -->
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center px-4">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                Total Omzet
                            </span>
                            <span class="text-sm sm:text-base font-black text-slate-900 mt-0.5 font-mono">
                                Rp {{ number_format(($summary['kpis']['revenue'] ?? 0) / 1000000000, 1, ',', '.') }} M
                            </span>
                            <span class="text-[11px] text-[#782f18] font-semibold mt-0.5">
                                {{ count($summary['by_business_unit'] ?? []) }} Unit Bisnis
                            </span>
                        </div>
                    </div>

                    <!-- Breakdown List Per Unit Bisnis (Panjang & Mengisi Ruang Vertikal) -->
                    <div class="sm:col-span-7 space-y-2.5 max-h-[440px] overflow-y-auto pr-1 custom-scrollbar">
                        @php
                            $earthPalette = [
                                '#9c4a2f', // Rich Terracotta Earth (Tanah Liat)
                                '#445e3d', // Forest Sage (Hijau Daun)
                                '#c28b5b', // Warm Tan Sand (Pasir Hangat)
                                '#786048', // Warm Walnut (Kayu Tua)
                                '#5c7355', // Olive Foliage (Zaitun)
                                '#b87d3b', // Warm Ochre (Tanah Kering)
                                '#8a4325', // Deep Terracotta (Bata Bakar)
                                '#364f33', // Deep Moss (Lumut Hutan)
                            ];
                        @endphp
                        @foreach($summary['by_business_unit'] as $idx => $bu)
                        @php
                            $totalRev = $summary['kpis']['revenue'] ?? 1;
                            $pct = $totalRev > 0 ? round(($bu['revenue'] / $totalRev) * 100, 1) : 0;
                            $buTone = $earthPalette[$idx % count($earthPalette)];
                        @endphp
                        <div class="p-3 rounded-xl border border-[#e6e1d5] hover:border-[#e2beaf] transition-colors bg-[#f7f5f0] flex items-center justify-between shadow-2xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-2xs" style="background-color: {{ $buTone }}"></span>
                                <div class="truncate">
                                    <p class="text-xs font-bold text-slate-800 truncate">{{ $bu['name'] }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] text-slate-400 uppercase font-mono">{{ $bu['code'] }}</span>
                                        <span class="text-[10px] text-slate-300">&bull;</span>
                                        <span class="text-[10px] font-semibold {{ ($bu['net_profit'] ?? 0) >= 0 ? 'text-[#445e3d]' : 'text-rose-600' }}">Margin {{ $bu['net_margin'] ?? 0 }}%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-bold text-slate-900 font-mono">Rp {{ number_format($bu['revenue'] / 1000000, 0, ',', '.') }} Jt</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-[#9c4a2f]/10 text-[#782f18] font-mono border border-[#e2beaf]">{{ $pct }}%</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-[#e6e1d5] flex items-center justify-between text-xs text-slate-500 mt-4">
                <span>Total Omzet: <strong class="text-slate-800 font-mono">Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}</strong></span>
                <span class="text-[11px] text-[#9c4a2f] font-bold">100% Terkonsolidasi</span>
            </div>
        </div>

    </div>

    <!-- 3. PROFIT & LOSS MONITORING & BUSINESS UNIT BREAKDOWN -->
    <div class="bg-white rounded-2xl border border-[#e6e1d5] shadow-xs overflow-hidden">
        
        <!-- Header with Time Filter Tabs -->
        <div class="p-5 border-b border-[#e6e1d5] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Profit & Loss Monitoring</h3>
                <p class="text-xs text-slate-400">Distribusi omzet, HPP, beban operasional dan margin per unit bisnis</p>
            </div>

            <!-- Time Tabs (Flat Clean - NO GRADIENT) -->
            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold bg-[#9c4a2f]/10 text-[#782f18] border border-[#e2beaf]">
                    Monthly
                </span>
                <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">
                    Quarterly
                </span>
                <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">
                    Yearly
                </span>
            </div>
        </div>

        <!-- Unit Bisnis Progress Bars (Solid Flat Colors) -->
        <div class="p-5 border-b border-[#e6e1d5] bg-[#f7f5f0]">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @foreach($summary['by_business_unit'] as $idx => $bu)
                @php
                    $totalRev = $summary['kpis']['revenue'] ?? 1;
                    $pct = $totalRev > 0 ? round(($bu['revenue'] / $totalRev) * 100, 1) : 0;
                    $toneColor = $earthPalette[$idx % count($earthPalette)];
                @endphp
                <div class="bg-white p-3.5 rounded-xl border border-[#e6e1d5]">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-bold text-slate-800 truncate">{{ $bu['name'] }}</span>
                        <span class="font-mono font-bold text-slate-900">{{ $pct }}%</span>
                    </div>
                    <div class="w-full bg-[#ede8df] rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-300" style="width: {{ $pct }}%; background-color: {{ $toneColor }}"></div>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-2 font-mono font-semibold">
                        Rp {{ number_format($bu['revenue'], 0, ',', '.') }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-white text-slate-400 font-bold uppercase tracking-wider border-b border-[#e6e1d5] text-[10px]">
                        <th class="p-4 pl-6">Unit Bisnis</th>
                        <th class="p-4 text-right">Pendapatan (Revenue)</th>
                        <th class="p-4 text-right">HPP (COGS)</th>
                        <th class="p-4 text-right">Laba Kotor</th>
                        <th class="p-4 text-right">Beban OPEX</th>
                        <th class="p-4 text-right">Laba Bersih</th>
                        <th class="p-4 text-center pr-6">Net Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e1d5] font-medium text-slate-700">
                    @foreach($summary['by_business_unit'] as $idx => $bu)
                    @php $tableTone = $earthPalette[$idx % count($earthPalette)]; @endphp
                    <tr class="hover:bg-[#f8ede7]/40 transition-colors">
                        <td class="p-4 pl-6 font-bold text-slate-900 flex items-center gap-2.5">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $tableTone }}"></span>
                            <span>{{ $bu['name'] }}</span>
                        </td>
                        <td class="p-4 text-right font-bold text-slate-900 font-mono">Rp {{ number_format($bu['revenue'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right text-slate-600 font-mono">Rp {{ number_format($bu['cogs'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right text-slate-800 font-mono">Rp {{ number_format($bu['gross_profit'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right text-slate-600 font-mono">Rp {{ number_format($bu['opex'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-bold font-mono {{ $bu['net_profit'] >= 0 ? 'text-[#445e3d]' : 'text-rose-600' }}">
                            Rp {{ number_format($bu['net_profit'], 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center pr-6">
                            <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] {{ $bu['net_profit'] >= 0 ? 'bg-[#edf3ec] text-[#2d4529] border border-[#c4d8c1]' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                {{ $bu['net_margin'] }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach

                    <!-- TOTAL ROW -->
                    <tr class="bg-[#f8ede7]/70 font-black text-slate-900 border-t-2 border-[#e2beaf]">
                        <td class="p-4 pl-6 uppercase text-[11px] tracking-wider text-slate-900">TOTAL HOLDING TERKONSOLIDASI</td>
                        <td class="p-4 text-right font-mono text-slate-900 font-black">Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-mono">Rp {{ number_format($summary['kpis']['cogs'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-mono">Rp {{ number_format($summary['kpis']['gross_profit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-mono">Rp {{ number_format($summary['kpis']['opex'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-mono {{ ($summary['kpis']['net_profit'] ?? 0) >= 0 ? 'text-[#445e3d]' : 'text-rose-600' }}">
                            Rp {{ number_format($summary['kpis']['net_profit'] ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center pr-6">
                            <span class="px-2.5 py-1 rounded-full font-black text-xs bg-[#f8ede7] text-[#782f18] border border-[#e2beaf]">
                                {{ $summary['kpis']['net_margin_percentage'] ?? 0 }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    // -------------------------------------------------------------
    // DATA DARI BACKEND
    // -------------------------------------------------------------
    const monthlyTrends = @json($summary['monthly_trends'] ?? []);
    const buList = @json($summary['by_business_unit'] ?? []);

    const trendLabels = monthlyTrends.map(t => t.label);
    const revData = monthlyTrends.map(t => Number(t.revenue || 0));
    const cogsData = monthlyTrends.map(t => Number(t.cogs || 0));
    const opexData = monthlyTrends.map(t => Number(t.opex || 0));
    const profitData = monthlyTrends.map(t => Number(t.net_profit || 0));

    let financialChart = null;
    let currentChartType = 'bar';

    function formatRupiah(val) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val);
    }

    // -------------------------------------------------------------
    // 1. FINANCIAL TREND CHART (IDENTIK DENGAN SIR-IQOS)
    // -------------------------------------------------------------
    function renderFinancialChart(type) {
        currentChartType = type;
        const canvas = document.getElementById('financialTrendChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (financialChart) {
            financialChart.destroy();
        }

        const isLine = type === 'line';

        // Warna Harmonis (Palet Bumi / Earth Tone Palette)
        // 1. Omzet Penjualan (Terracotta Earth)
        const revGrad = ctx.createLinearGradient(0, 0, 0, 320);
        revGrad.addColorStop(0, 'rgba(156, 74, 47, 0.95)');
        revGrad.addColorStop(1, isLine ? 'rgba(156, 74, 47, 0.08)' : 'rgba(156, 74, 47, 0.45)');

        // 2. HPP (COGS) (Warm Tan Sand)
        const cogsGrad = ctx.createLinearGradient(0, 0, 0, 320);
        cogsGrad.addColorStop(0, 'rgba(194, 139, 91, 0.95)');
        cogsGrad.addColorStop(1, isLine ? 'rgba(194, 139, 91, 0.08)' : 'rgba(194, 139, 91, 0.45)');

        // 3. Beban OPEX (Warm Walnut)
        const opexGrad = ctx.createLinearGradient(0, 0, 0, 320);
        opexGrad.addColorStop(0, 'rgba(120, 96, 72, 0.90)');
        opexGrad.addColorStop(1, isLine ? 'rgba(120, 96, 72, 0.08)' : 'rgba(120, 96, 72, 0.45)');

        // 4. Laba Bersih (Forest Sage)
        const profitGrad = ctx.createLinearGradient(0, 0, 0, 320);
        profitGrad.addColorStop(0, 'rgba(68, 94, 61, 0.95)');
        profitGrad.addColorStop(1, isLine ? 'rgba(68, 94, 61, 0.08)' : 'rgba(68, 94, 61, 0.45)');

        const datasets = isLine ? [
            {
                label: 'Omzet Penjualan',
                data: revData,
                borderColor: '#9c4a2f',
                backgroundColor: '#9c4a2f',
                borderWidth: 2.5,
                tension: 0.3,
                fill: false,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#9c4a2f',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'HPP (COGS)',
                data: cogsData,
                borderColor: '#c28b5b',
                backgroundColor: '#c28b5b',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                pointRadius: 3.5,
                pointHoverRadius: 5.5,
                pointBackgroundColor: '#c28b5b',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'Beban OPEX',
                data: opexData,
                borderColor: '#786048',
                backgroundColor: '#786048',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                pointRadius: 3.5,
                pointHoverRadius: 5.5,
                pointBackgroundColor: '#786048',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'Laba Bersih',
                data: profitData,
                borderColor: '#445e3d',
                backgroundColor: '#445e3d',
                borderWidth: 2.5,
                tension: 0.3,
                fill: false,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#445e3d',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            }
        ] : [
            {
                label: 'Omzet Penjualan',
                data: revData,
                backgroundColor: revGrad,
                borderColor: '#9c4a2f',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'HPP (COGS)',
                data: cogsData,
                backgroundColor: cogsGrad,
                borderColor: '#c28b5b',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'Beban OPEX',
                data: opexData,
                backgroundColor: opexGrad,
                borderColor: '#786048',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'Laba Bersih',
                data: profitData,
                backgroundColor: profitGrad,
                borderColor: '#445e3d',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            }
        ];

        financialChart = new Chart(ctx, {
            type: type,
            data: {
                labels: trendLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#ffffff',
                        bodyColor: '#f8fafc',
                        padding: 12,
                        cornerRadius: 10,
                        boxPadding: 6,
                        usePointStyle: true,
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const idx = context.dataIndex;
                                const omzet = revData[idx] || 0;
                                let suffix = '';
                                if (context.dataset.label === 'Laba Bersih' && omzet > 0) {
                                    const margin = ((val / omzet) * 100).toFixed(1);
                                    suffix = ' (' + margin + '% net margin)';
                                }
                                return ' ' + context.dataset.label + ': ' + formatRupiah(val) + suffix;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, family: 'Instrument Sans', weight: '500' },
                            color: '#64748b'
                        }
                    },
                    y: {
                        grid: { color: 'rgba(241, 245, 249, 1)' },
                        ticks: {
                            font: { size: 10, family: 'Instrument Sans' },
                            color: '#94a3b8',
                            callback: function(val) {
                                const abs = Math.abs(val);
                                const sign = val < 0 ? '-' : '';
                                if (abs >= 1e9) return sign + 'Rp ' + (abs / 1e9).toFixed(1) + 'M';
                                if (abs >= 1e6) return sign + 'Rp ' + (abs / 1e6).toFixed(0) + 'Jt';
                                return sign + val;
                            }
                        }
                    }
                }
            }
        });
    }

    function setFinancialChartType(type) {
        const btnBar = document.getElementById('btnChartBar');
        const btnLine = document.getElementById('btnChartLine');

        if (type === 'bar') {
            btnBar.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs';
            btnLine.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800';
        } else {
            btnLine.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs';
            btnBar.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800';
        }

        renderFinancialChart(type);
    }

    // -------------------------------------------------------------
    // 2. DOUGHNUT CHART DISTRIBUSI UNIT BISNIS (IDENTIK DENGAN SIR-IQOS)
    // -------------------------------------------------------------
    function renderBuDoughnutChart() {
        const canvas = document.getElementById('buDoughnutChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const labels = buList.map(b => b.name);
        const data = buList.map(b => Number(b.revenue || 0));

        // Palet Harmoni Bumi (Earth Tone Palette)
        const earthPalette = [
            '#9c4a2f', // Rich Terracotta Earth (Tanah Liat)
            '#445e3d', // Forest Sage (Hijau Daun)
            '#c28b5b', // Warm Tan Sand (Pasir Hangat)
            '#786048', // Warm Walnut (Kayu Tua)
            '#5c7355', // Olive Foliage (Zaitun)
            '#b87d3b', // Warm Ochre (Tanah Kering)
            '#8a4325', // Deep Terracotta (Bata Bakar)
            '#364f33', // Deep Moss (Lumut Hutan)
        ];

        const colors = buList.map((b, idx) => earthPalette[idx % earthPalette.length]);

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2.5,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#ffffff',
                        bodyColor: '#f8fafc',
                        padding: 12,
                        cornerRadius: 10,
                        boxPadding: 6,
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const total = data.reduce((a, b) => a + b, 0) || 1;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ' ' + context.label + ': ' + formatRupiah(val) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Inisialisasi kedua grafik saat dokumen dimuat
    document.addEventListener('DOMContentLoaded', function() {
        renderFinancialChart('bar');
        renderBuDoughnutChart();
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
</script>
@endsection
