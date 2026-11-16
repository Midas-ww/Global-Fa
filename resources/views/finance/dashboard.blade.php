@extends('layouts.app')

@section('title', 'Executive Dashboard')
@section('page_title', 'Dashboard Eksekutif Konsolidasi Holding')

@section('content')
<div class="space-y-6 pb-12">

    <!-- KPI STATS CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Revenue Card -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Omzet / Revenue</span>
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                    Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}
                </h3>
                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span class="text-blue-600 font-semibold">Terkonsolidasi</span> dari 4 Unit Bisnis
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
        </div>

        <!-- Gross Profit Card -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Laba Kotor (Gross Profit)</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                    Rp {{ number_format($summary['kpis']['gross_profit'] ?? 0, 0, ',', '.') }}
                </h3>
                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span class="px-2 py-0.5 rounded-full font-bold bg-indigo-50 text-indigo-700">Margin: {{ $summary['kpis']['gross_margin_percentage'] ?? 0 }}%</span>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
        </div>

        <!-- OPEX Card -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Beban Operasional (OPEX)</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="receipt" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                    Rp {{ number_format($summary['kpis']['opex'] ?? 0, 0, ',', '.') }}
                </h3>
                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span class="px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700">Rasio OPEX: {{ $summary['kpis']['opex_ratio'] ?? 0 }}%</span>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-600"></div>
        </div>

        <!-- Net Profit Card -->
        @php
            $net = $summary['kpis']['net_profit'] ?? 0;
            $isNetPositive = $net >= 0;
        @endphp
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Laba Bersih (Net Profit)</span>
                <div class="w-9 h-9 rounded-xl {{ $isNetPositive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }} flex items-center justify-center">
                    <i data-lucide="{{ $isNetPositive ? 'arrow-up-right' : 'arrow-down-right' }}" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <h3 class="text-2xl font-extrabold {{ $isNetPositive ? 'text-emerald-600' : 'text-rose-600' }} tracking-tight">
                    Rp {{ number_format($net, 0, ',', '.') }}
                </h3>
                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span class="px-2 py-0.5 rounded-full font-bold {{ $isNetPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                        Net Margin: {{ $summary['kpis']['net_margin_percentage'] ?? 0 }}%
                    </span>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 {{ $isNetPositive ? 'bg-gradient-to-r from-emerald-500 to-teal-600' : 'bg-gradient-to-r from-rose-500 to-red-600' }}"></div>
        </div>
    </div>

    <!-- 12-MONTH TREND & SECTOR BREAKDOWN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- 12-Month Performance Trend Chart -->
        <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Tren Kinerja Finansial (12 Bulan)</h4>
                    <p class="text-xs text-slate-400">Pergerakan total pendapatan dan laba bersih holding</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span> Omzet</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span> Laba Bersih</span>
                </div>
            </div>
            <div class="h-72 w-full">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <!-- Business Unit Contribution Card -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div>
                <h4 class="font-bold text-slate-900 text-sm mb-1">Kontribusi Unit Bisnis</h4>
                <p class="text-xs text-slate-400 mb-4">Porsi pendapatan per lini usaha bulan ini</p>
                <div class="space-y-4">
                    @foreach($summary['by_business_unit'] as $bu)
                    @php
                        $totalRev = $summary['kpis']['revenue'] ?? 1;
                        $pct = $totalRev > 0 ? round(($bu['revenue'] / $totalRev) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold mb-1.5">
                            <span class="flex items-center gap-2 text-slate-700">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $bu['color'] }}"></span>
                                {{ $bu['name'] }}
                            </span>
                            <span class="text-slate-900 font-bold">Rp {{ number_format($bu['revenue'], 0, ',', '.') }} ({{ $pct }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full" style="width: {{ $pct }}%; background-color: {{ $bu['color'] }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="pt-6 border-t border-slate-100 mt-6">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-500">Total Konsolidasi Omzet:</span>
                    <span class="text-blue-600 text-sm">Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- BUSINESS UNIT DETAIL PERFORMANCE TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h4 class="font-bold text-slate-900 text-sm">Rincian Finansial Tiap Unit Bisnis</h4>
                <p class="text-xs text-slate-400">Komparasi pendapatan, beban pokok, OPEX dan margin laba</p>
            </div>
            <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                Buka P&L Detail <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200">
                        <th class="p-4">Unit Bisnis</th>
                        <th class="p-4 text-right">Pendapatan (Revenue)</th>
                        <th class="p-4 text-right">HPP (COGS)</th>
                        <th class="p-4 text-right">Laba Kotor</th>
                        <th class="p-4 text-right">Biaya OPEX</th>
                        <th class="p-4 text-right">Laba Bersih</th>
                        <th class="p-4 text-center">Net Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($summary['by_business_unit'] as $bu)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="p-4 font-semibold text-slate-800 flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $bu['color'] }}"></span>
                            {{ $bu['name'] }}
                        </td>
                        <td class="p-4 text-right font-bold text-slate-900">Rp {{ number_format($bu['revenue'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right text-slate-600">Rp {{ number_format($bu['cogs'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-medium text-slate-800">Rp {{ number_format($bu['gross_profit'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right text-slate-600">Rp {{ number_format($bu['opex'], 0, ',', '.') }}</td>
                        <td class="p-4 text-right font-extrabold {{ $bu['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            Rp {{ number_format($bu['net_profit'], 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2 py-0.5 rounded-full font-bold text-[11px] {{ $bu['net_profit'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                {{ $bu['net_margin'] }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                    <tr class="bg-slate-50 font-extrabold text-slate-900 border-t-2 border-slate-300">
                        <td class="p-4">TOTAL HOLDING</td>
                        <td class="p-4 text-right text-blue-600">Rp {{ number_format($summary['kpis']['revenue'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right">Rp {{ number_format($summary['kpis']['cogs'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right">Rp {{ number_format($summary['kpis']['gross_profit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right">Rp {{ number_format($summary['kpis']['opex'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-4 text-right {{ ($summary['kpis']['net_profit'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            Rp {{ number_format($summary['kpis']['net_profit'] ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] bg-blue-100 text-blue-800">
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
    const trends = @json($summary['monthly_trends'] ?? []);
    const labels = trends.map(t => t.label);
    const revenues = trends.map(t => t.revenue);
    const netProfits = trends.map(t => t.net_profit);

    const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Omzet',
                    data: revenues,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3
                },
                {
                    label: 'Laba Bersih',
                    data: netProfits,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            if (value >= 1e9) return (value / 1e9).toFixed(1) + ' M';
                            if (value >= 1e6) return (value / 1e6).toFixed(0) + ' Jt';
                            return value;
                        }
                    },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
@endsection
