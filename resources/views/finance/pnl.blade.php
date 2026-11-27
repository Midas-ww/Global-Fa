@extends('layouts.app')

@section('title', 'Profit & Loss Monitoring')

@section('content')
<div class="space-y-6 pb-12">

    <!-- FILTER BAR & QUICK TABS -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-[#e9e5d6] shadow-xs space-y-4">
        <!-- TOP: Quick Tabs for Business Unit -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                @if($isUnrestricted ?? true)
                <!-- ALL / CONSOLIDATED TAB -->
                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ empty($selectedBu) ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-white hover:bg-[#fef9c3] text-slate-700 hover:text-[#713f12] border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="layers" class="w-3.5 h-3.5 {{ empty($selectedBu) ? 'text-[#ca8a04]' : 'text-slate-400' }}"></i>
                        <span>Semua (Konsolidasi Holding)</span>
                    </span>
                </a>
                @endif

                @foreach($businessUnits as $bu)
                @php
                    $tabParams = ['business_unit' => $bu['code']];
                    if (in_array($bu['code'], $unitsWithSelectedPeriod ?? [], true)) {
                        $tabParams['period'] = $selectedPeriod;
                    }
                @endphp
                <a href="{{ route('finance.pnl', $tabParams) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ $selectedBu === $bu['code'] ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-white hover:bg-[#fef9c3] text-slate-700 hover:text-[#713f12] border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $bu['color'] }}"></span>
                        <span>{{ $bu['name'] }}</span>
                    </span>
                </a>
                @endforeach
            </div>

            <!-- Period Selector Control -->
            <div class="flex items-center gap-3 flex-wrap">
                <form action="{{ route('finance.pnl') }}" method="GET" class="flex items-center">
                    @if($selectedBu)
                        <input type="hidden" name="business_unit" value="{{ $selectedBu }}">
                    @endif
                    @if($selectedEntity)
                        <input type="hidden" name="entity" value="{{ $selectedEntity }}">
                    @endif
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-[#faf8f0] border border-[#e9e5d6] rounded-xl shadow-2xs">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#ca8a04] shrink-0"></i>
                        <label for="periodSelectPnl" class="text-xs font-semibold text-slate-500">Periode:</label>
                        <select name="period" id="periodSelectPnl" onchange="this.form.submit()" class="text-xs font-bold font-mono bg-transparent text-slate-900 focus:outline-none cursor-pointer">
                            @foreach($availablePeriods as $p)
                                <option value="{{ $p }}" {{ $selectedPeriod === $p ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($p)->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                @if($selectedBu === 'spbu' && (auth()->user()?->canAccessSyncMenu() ?? false))
                <form action="{{ route('finance.sync.database') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="target" value="spbu">
                    <input type="hidden" name="period" value="{{ substr($selectedPeriod, 0, 7) }}">
                    <button type="submit"
                        class="px-3 py-1.5 rounded-xl bg-[#ca8a04]/10 hover:bg-[#ca8a04]/20 text-[#713f12] border border-[#fde047] text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        title="Tarik data transaksi terbaru langsung dari sistem database SPBU untuk periode {{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('F Y') }}">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-[#ca8a04]"></i>
                        <span>Sync SPBU</span>
                    </button>
                </form>
                @endif

                <div class="text-xs font-semibold text-slate-400">
                    Vs Prev: <span class="font-bold text-slate-700 font-mono">{{ $pnl['previous_period_label'] }}</span>
                </div>
            </div>
        </div>

        @if($selectedBu)
        <!-- Sub-filter: Entitas / Cabang jika ada -->
        @php
            $activeBu = collect($businessUnits)->firstWhere('code', $selectedBu);
            $entities = $activeBu['entities'] ?? [];
            $entityLabel = match($selectedBu) {
                'fmcg' => 'Depo FMCG',
                'ccod' => 'Cabang CCOD',
                'xtrans' => 'Divisi / Rute',
                'spbu' => 'Unit SPBU',
                'iqos' => 'Outlet IQOS',
                'kandang' => 'Kandang',
                'onbold' => 'Distributor On Bold',
                default => 'Cabang / Entitas',
            };
        @endphp
        @if(count($entities) > 0)
        <div class="pt-3 border-t border-[#e9e5d6] space-y-2">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-bold text-slate-700 mr-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $activeBu['color'] ?? '#ca8a04' }}"></span>
                        <span>{{ $entityLabel }} ({{ count($entities) }}):</span>
                    </span>
                    <a href="{{ route('finance.pnl', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ empty($selectedEntity) ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        Semua Cabang
                    </a>
                    @foreach($entities as $e)
                    @if(empty($pnl['by_entity']) || collect($pnl['by_entity'])->contains('code', $e['code']))
                    <a href="{{ route('finance.pnl', ['period' => $selectedPeriod, 'business_unit' => $selectedBu, 'entity' => $e['code']]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all {{ $selectedEntity === $e['code'] ? 'bg-[#ca8a04]/10 text-[#713f12] font-bold border border-[#fde047] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        {{ $e['name'] }}
                    </a>
                    @endif
                    @endforeach
                </div>
                @if($selectedEntity)
                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}" class="text-xs text-rose-600 hover:text-rose-800 font-bold flex items-center gap-1">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i> Reset Cabang
                </a>
                @endif
            </div>
        </div>
        @endif
        @endif
    </div>

    <!-- STATUS CLOSING & CATATAN PERIODE -->
    @include('finance.partials.period-note-banner', ['reportType' => 'pnl'])

    <!-- FINANCIAL CHART (IDENTIK DENGAN SIR-IQOS P&L) -->
    <div class="rounded-2xl border border-[#e9e5d6] bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>Pendapatan, HPP &amp; Biaya Operasional</span>
                </h2>
                <p class="text-slate-500 text-xs mt-0.5">
                    Tren performa pendapatan, HPP, beban operasional, dan laba bersih antar periode
                </p>
            </div>

            <!-- Type Switcher: Batang vs Garis Area -->
            <div class="flex items-center gap-2.5 shrink-0">
                <div class="inline-flex items-center p-0.5 bg-slate-100 rounded-lg border border-slate-200 text-[11px] font-semibold">
                    <button
                        type="button"
                        onclick="setPnlChartType('bar')"
                        id="btnPnlChartBar"
                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs"
                    >
                        <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>
                        <span>Batang</span>
                    </button>
                    <button
                        type="button"
                        onclick="setPnlChartType('line')"
                        id="btnPnlChartLine"
                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800"
                    >
                        <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
                        <span>Garis Tren</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Legend Indicators (Tema Jagung / Golden Corn & Husk Green Palette) -->
        <div class="mt-2 flex flex-wrap items-center gap-2.5 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 text-[#713f12] bg-[#fef9c3] px-2.5 py-0.5 rounded-md border border-[#fde047]">
                <span class="w-2.5 h-2.5 rounded-full bg-[#ca8a04]"></span>
                Pendapatan
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#6e4620] bg-[#fbf3eb] px-2.5 py-0.5 rounded-md border border-[#edd7c4]">
                <span class="w-2.5 h-2.5 rounded-full bg-[#854d0e]"></span>
                HPP (COGS)
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#4e3d2c] bg-[#f5f1ec] px-2.5 py-0.5 rounded-md border border-[#dfd6cd]">
                <span class="w-2.5 h-2.5 rounded-full bg-[#65a30d]"></span>
                Biaya Operasional
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#14532d] bg-[#edf5ee] px-2.5 py-0.5 rounded-md border border-[#c2e7cb]">
                <span class="w-2.5 h-2.5 rounded-full bg-[#15803d]"></span>
                Laba Bersih
            </span>
        </div>

        <!-- Canvas Container -->
        <div class="relative w-full h-64 sm:h-72 mt-3">
            <canvas id="pnlFinancialChart"></canvas>
        </div>
    </div>

    @if(empty($selectedBu) && !empty($pnl['by_business_unit']))
    <!-- KONTRIBUSI UNIT USAHA SUMMARY CARDS -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="pie-chart" class="w-4 h-4 text-[#ca8a04]"></i>
                Ringkasan Laba Rugi per Unit Bisnis (Periode {{ $pnl['period_label'] }})
            </h3>
            <span class="text-xs text-slate-400">Klik unit untuk melihat rincian buku besar</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            @foreach($pnl['by_business_unit'] as $buItem)
            <div class="bg-white rounded-2xl border border-[#e9e5d6] p-4 shadow-xs space-y-3 hover:border-slate-300 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs shadow-2xs"
                             style="background-color: {{ $buItem['color'] ?: '#fef9c3' }}20; border: 1.5px solid {{ $buItem['color'] ?: '#ca8a04' }}; color: {{ $buItem['color'] ?: '#713f12' }};">
                            <i data-lucide="{{ $buItem['icon'] ?: 'building-2' }}" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900">{{ $buItem['name'] }}</h4>
                            <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ $buItem['code'] }}</span>
                        </div>
                    </div>
                    <a href="{{ route('finance.pnl', ['period' => $selectedPeriod, 'business_unit' => $buItem['code']]) }}"
                       class="text-[11px] font-bold text-[#ca8a04] hover:text-[#713f12] flex items-center gap-0.5"
                       title="Buka Rincian P&L {{ $buItem['name'] }}">
                        Rincian &rarr;
                    </a>
                </div>

                <div class="space-y-1.5 pt-1 border-t border-slate-100 text-xs font-mono">
                    <div class="flex items-center justify-between text-slate-600">
                        <span class="text-[11px] font-sans text-slate-400">Pendapatan:</span>
                        <span class="font-bold text-slate-900">Rp {{ number_format($buItem['revenue'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span class="text-[11px] font-sans text-slate-400">HPP (COGS):</span>
                        <span class="font-medium text-slate-700">Rp {{ number_format($buItem['cogs'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span class="text-[11px] font-sans text-slate-400">Biaya Opex:</span>
                        <span class="font-medium text-slate-700">Rp {{ number_format($buItem['opex'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-dashed border-slate-200">
                        <span class="text-[11px] font-sans font-bold text-slate-800">Laba Bersih:</span>
                        <span class="font-black {{ $buItem['net_profit'] >= 0 ? 'text-[#16a34a]' : 'text-rose-600' }}">
                            Rp {{ number_format($buItem['net_profit'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(!empty($selectedBu) && empty($selectedEntity) && !empty($pnl['by_entity']))
    <!-- KONTRIBUSI & PERBANDINGAN KINERJA ANTAR CABANG / DEPO / DEPARTEMEN -->
    <div class="bg-white rounded-2xl border border-[#e9e5d6] shadow-xs overflow-hidden">
        <div class="p-5 border-b border-[#e9e5d6] flex items-center justify-between bg-white flex-wrap gap-2">
            <div>
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <i data-lucide="building" class="w-4 h-4 text-[#ca8a04]"></i>
                    Perbandingan Kinerja Antar Cabang / Departemen (Periode {{ $pnl['period_label'] }})
                </h3>
                <p class="text-xs text-slate-400">Ringkasan performa pendapatan, HPP, beban operasional, dan laba bersih tiap unit</p>
            </div>
            <div class="text-xs font-bold text-slate-500">
                Total: <span class="text-slate-900 font-mono">{{ count($pnl['by_entity']) }} Cabang/Entitas</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-700 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                        <th class="p-3.5 pl-6">Cabang / Departemen</th>
                        <th class="p-3.5 text-right">Pendapatan</th>
                        <th class="p-3.5 text-right">HPP (COGS)</th>
                        <th class="p-3.5 text-right">Laba Kotor</th>
                        <th class="p-3.5 text-center">GP Margin</th>
                        <th class="p-3.5 text-right">Beban Opex</th>
                        <th class="p-3.5 text-right">Laba Bersih</th>
                        <th class="p-3.5 text-center">Net Margin</th>
                        <th class="p-3.5 text-center pr-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono font-medium">
                    @php
                        $totEntRev = 0;
                        $totEntCogs = 0;
                        $totEntGp = 0;
                        $totEntOpex = 0;
                        $totEntNet = 0;
                    @endphp
                    @foreach($pnl['by_entity'] as $entItem)
                        @php
                            $totEntRev += $entItem['revenue'];
                            $totEntCogs += $entItem['cogs'];
                            $totEntGp += $entItem['gross_profit'];
                            $totEntOpex += $entItem['opex'];
                            $totEntNet += $entItem['net_profit'];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-3 pl-6 font-sans">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $entItem['color'] ?? ($activeBu['color'] ?? '#ca8a04') }}"></span>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $entItem['name'] }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono uppercase">{{ $entItem['code'] }} &bull; {{ $entItem['location'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-right font-bold text-slate-900">
                                Rp {{ number_format($entItem['revenue'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right text-slate-600">
                                Rp {{ number_format($entItem['cogs'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-bold {{ $entItem['gross_profit'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                                Rp {{ number_format($entItem['gross_profit'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $entItem['gross_margin'] >= 10 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $entItem['gross_margin'] }}%
                                </span>
                            </td>
                            <td class="p-3 text-right text-slate-600">
                                Rp {{ number_format($entItem['opex'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-black {{ $entItem['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                Rp {{ number_format($entItem['net_profit'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $entItem['net_margin'] >= 0 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $entItem['net_margin'] }}%
                                </span>
                            </td>
                            <td class="p-3 text-center pr-6">
                                <a href="{{ route('finance.pnl', ['period' => $selectedPeriod, 'business_unit' => $selectedBu, 'entity' => $entItem['code']]) }}"
                                   class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs inline-flex items-center gap-1 font-sans">
                                    Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100/80 font-mono font-bold text-slate-900 border-t-2 border-slate-300">
                        <td class="p-3.5 pl-6 font-sans uppercase text-[11px]">Total Seluruh Cabang</td>
                        <td class="p-3.5 text-right font-black">Rp {{ number_format($totEntRev, 0, ',', '.') }}</td>
                        <td class="p-3.5 text-right">Rp {{ number_format($totEntCogs, 0, ',', '.') }}</td>
                        <td class="p-3.5 text-right font-black">Rp {{ number_format($totEntGp, 0, ',', '.') }}</td>
                        <td class="p-3.5 text-center">{{ $totEntRev > 0 ? round(($totEntGp / $totEntRev) * 100, 2) : 0 }}%</td>
                        <td class="p-3.5 text-right">Rp {{ number_format($totEntOpex, 0, ',', '.') }}</td>
                        <td class="p-3.5 text-right font-black {{ $totEntNet >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">Rp {{ number_format($totEntNet, 0, ',', '.') }}</td>
                        <td class="p-3.5 text-center">{{ $totEntRev > 0 ? round(($totEntNet / $totEntRev) * 100, 2) : 0 }}%</td>
                        <td class="p-3.5 pr-6"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif
    <div class="bg-white rounded-2xl border border-[#e9e5d6] shadow-xs overflow-hidden">
        <div class="p-5 border-b border-[#e9e5d6] flex items-center justify-between bg-white flex-wrap gap-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">
                    @if(empty($selectedBu))
                        LAPORAN LABA RUGI KONSOLIDASI GLOBAL (ALL HOLDING)
                    @else
                        @php $currentBuObj = collect($businessUnits)->firstWhere('code', $selectedBu); @endphp
                        LAPORAN LABA RUGI &mdash; {{ strtoupper($currentBuObj['name'] ?? $selectedBu) }}
                        @if($selectedEntity)
                            @php $curEntObj = collect($currentBuObj['entities'] ?? [])->firstWhere('code', $selectedEntity); @endphp
                            ({{ $curEntObj['name'] ?? $selectedEntity }})
                        @endif
                    @endif
                </h3>
                <p class="text-xs text-slate-400">Periode berakhir per {{ $pnl['period_label'] }} (Mata Uang: IDR Rupiah)</p>
            </div>

            @if($canEdit ?? false)
            <div class="flex items-center gap-2">
                <button type="button" onclick="openAddAccountModal('pnl', '{{ $selectedBu }}', '{{ $selectedEntity }}')"
                    class="px-3 py-1.5 rounded-xl bg-[#ca8a04] hover:bg-[#a16207] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    <span>+ Tambah Akun P&L</span>
                </button>
            </div>
            @endif
        </div>

        <div class="overflow-x-auto overflow-y-auto max-h-[680px] custom-scrollbar border-t border-slate-100">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 z-10 bg-white border-b-2 border-slate-200 shadow-2xs">
                    <tr class="bg-white text-slate-700 uppercase text-[11px] font-bold tracking-wider">
                        <th class="p-3.5 pl-6 w-24 bg-white">Kode Akun</th>
                        <th class="p-3.5 bg-white">Keterangan / Uraian Akun</th>
                        <th class="p-3.5 text-right w-44 bg-white">{{ $pnl['period_label'] }}</th>
                        <th class="p-3.5 text-right w-44 text-slate-400 bg-white">{{ $pnl['previous_period_label'] }}</th>
                        <th class="p-3.5 text-right w-36 bg-white">Selisih (MoM)</th>
                        <th class="p-3.5 text-center w-24 bg-white">% Growth</th>
                        @if($canEdit ?? false)
                        <th class="p-3.5 text-center w-20 bg-white">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    
                    @foreach($pnl['sections'] as $secKey => $section)
                        <!-- Section Header -->
                        <tr class="bg-[#faf8f0] font-extrabold text-slate-900">
                            <td colspan="{{ ($canEdit ?? false) ? 7 : 6 }}" class="p-3 pl-6 tracking-wide text-[11px] uppercase text-slate-900 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#ca8a04]"></span>
                                {{ $section['title'] }}
                            </td>
                        </tr>

                        <!-- Section Items -->
                        @forelse($section['items'] as $item)
                        <tr class="hover:bg-[#fef9c3]/40 transition-colors">
                            <td class="p-2.5 pl-6 font-mono text-[11px] text-slate-400">{{ $item['account_code'] ?? '-' }}</td>
                            <td class="p-2.5 text-slate-800">
                                <div class="flex items-center gap-2">
                                    <span>{{ $item['account_name'] }}</span>
                                    @if(empty($selectedBu) && !empty($item['business_unit_code']))
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider
                                        @if(str_contains(strtolower($item['business_unit_code']), 'spbu')) bg-rose-50 text-rose-700 border border-rose-200
                                        @elseif(str_contains(strtolower($item['business_unit_code']), 'iqos') || str_contains(strtolower($item['business_unit_code']), 'retail')) bg-teal-50 text-teal-800 border border-teal-200
                                        @elseif(str_contains(strtolower($item['business_unit_code']), 'fmcg')) bg-sky-50 text-sky-700 border border-sky-200
                                        @elseif(str_contains(strtolower($item['business_unit_code']), 'xtrans')) bg-[#fef9c3] text-[#ca8a04] border border-[#edd7ce]
                                        @elseif(str_contains(strtolower($item['business_unit_code']), 'ccod')) bg-indigo-50 text-indigo-700 border border-indigo-200
                                        @elseif(str_contains(strtolower($item['business_unit_code']), 'onbold')) bg-violet-50 text-violet-700 border border-violet-200
                                        @else bg-slate-100 text-slate-700 border border-slate-200 @endif">
                                        {{ $item['business_unit_code'] }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-2.5 text-right font-semibold font-mono tracking-tight text-slate-900">Rp {{ number_format($item['current_amount'], 0, ',', '.') }}</td>
                            <td class="p-2.5 text-right font-mono tracking-tight text-slate-500">Rp {{ number_format($item['previous_amount'], 0, ',', '.') }}</td>
                            <td class="p-2.5 text-right font-semibold font-mono tracking-tight {{ $item['variance'] >= 0 ? 'text-[#16a34a]' : 'text-rose-600' }}">
                                {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 0, ',', '.') }}
                            </td>
                            <td class="p-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-bold {{ $item['growth_pct'] >= 0 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                    {{ $item['growth_pct'] >= 0 ? '+' : '' }}{{ $item['growth_pct'] }}%
                                </span>
                            </td>
                            @if($canEdit ?? false)
                            <td class="p-2.5 text-center">
                                <button type="button"
                                    onclick="openEditAccountModal({{ json_encode([
                                        'id' => $item['record_id'] ?? null,
                                        'account_name' => $item['account_name'],
                                        'account_code' => $item['account_code'] ?? '',
                                        'category' => $secKey,
                                        'amount' => $item['current_amount'],
                                        'report_type' => 'pnl',
                                        'business_unit_code' => $selectedBu,
                                        'entity_code' => $selectedEntity ?? ($item['entity_code'] ?? null),
                                    ]) }})"
                                    class="p-1 rounded-md text-slate-400 hover:text-[#ca8a04] hover:bg-[#fef9c3] transition cursor-pointer"
                                    title="Edit Nominal Akun">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </button>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ ($canEdit ?? false) ? 7 : 6 }}" class="p-3 pl-6 text-slate-400 italic text-center">Tidak ada transaksi akun pada kategori ini.</td>
                        </tr>
                        @endforelse

                        <!-- Subtotal Section -->
                        <tr class="bg-slate-50 font-bold border-t border-b border-slate-200">
                            <td class="p-3 pl-6 font-mono text-[11px] text-slate-500">SUBTOTAL</td>
                            <td class="p-3 uppercase text-slate-700">TOTAL {{ $section['title'] }}</td>
                            <td class="p-3 text-right text-slate-900 font-extrabold font-mono tracking-tight">Rp {{ number_format($section['subtotal_current'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right text-slate-600 font-mono tracking-tight">Rp {{ number_format($section['subtotal_previous'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right font-extrabold font-mono tracking-tight {{ $section['variance'] >= 0 ? 'text-[#16a34a]' : 'text-rose-600' }}">
                                {{ $section['variance'] >= 0 ? '+' : '' }}{{ number_format($section['variance'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-md font-extrabold font-mono text-[10px] {{ $section['growth_pct'] >= 0 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $section['growth_pct'] >= 0 ? '+' : '' }}{{ $section['growth_pct'] }}%
                                </span>
                            </td>
                            @if($canEdit ?? false)
                            <td class="p-3"></td>
                            @endif
                        </tr>

                        <!-- Insert Gross Profit row after COGS (SOLID FLAT - NO GRADIENT) -->
                        @if($secKey === 'cogs')
                        <tr class="bg-[#fef9c3]/70 font-black text-slate-900 border-t-2 border-b-2 border-[#fde047]">
                            <td class="p-3.5 pl-6 font-mono text-[11px] text-[#713f12]">GP</td>
                            <td class="p-3.5 uppercase tracking-wide">LABA KOTOR (GROSS PROFIT)</td>
                            <td class="p-3.5 text-right font-black font-mono tracking-tight text-slate-900">Rp {{ number_format($pnl['summary']['gross_profit_current'], 0, ',', '.') }}</td>
                            <td class="p-3.5 text-right font-mono tracking-tight text-slate-500">Rp {{ number_format($pnl['summary']['gross_profit_previous'], 0, ',', '.') }}</td>
                            @php $gpVar = $pnl['summary']['gross_profit_current'] - $pnl['summary']['gross_profit_previous']; @endphp
                            <td class="p-3.5 text-right font-mono tracking-tight {{ $gpVar >= 0 ? 'text-[#16a34a]' : 'text-rose-600' }}">
                                {{ $gpVar >= 0 ? '+' : '' }}{{ number_format($gpVar, 0, ',', '.') }}
                            </td>
                            <td class="p-3.5 text-center">
                                @php $gpGrow = $pnl['summary']['gross_profit_previous'] != 0 ? round(($gpVar / abs($pnl['summary']['gross_profit_previous'])) * 100, 2) : 0; @endphp
                                <span class="px-2.5 py-1 rounded-md font-mono font-bold text-[10px] {{ $gpGrow >= 0 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $gpGrow >= 0 ? '+' : '' }}{{ $gpGrow }}%
                                </span>
                            </td>
                            @if($canEdit ?? false)
                            <td class="p-3.5"></td>
                            @endif
                        </tr>
                        @endif

                        <!-- Insert Operating Profit row after OPEX (SOLID FLAT - NO GRADIENT) -->
                        @if($secKey === 'opex')
                        <tr class="bg-[#fefce8] font-black text-slate-900 border-t-2 border-b-2 border-[#fef08a]">
                            <td class="p-3.5 pl-6 font-mono text-[11px] text-[#854d0e]">OP</td>
                            <td class="p-3.5 uppercase tracking-wide">LABA USAHA (OPERATING PROFIT)</td>
                            <td class="p-3.5 text-right font-black font-mono tracking-tight text-[#854d0e]">Rp {{ number_format($pnl['summary']['operating_profit_current'], 0, ',', '.') }}</td>
                            <td class="p-3.5 text-right font-mono tracking-tight text-slate-500">Rp {{ number_format($pnl['summary']['operating_profit_previous'], 0, ',', '.') }}</td>
                            @php $opVar = $pnl['summary']['operating_profit_current'] - $pnl['summary']['operating_profit_previous']; @endphp
                            <td class="p-3.5 text-right font-mono tracking-tight {{ $opVar >= 0 ? 'text-[#16a34a]' : 'text-rose-600' }}">
                                {{ $opVar >= 0 ? '+' : '' }}{{ number_format($opVar, 0, ',', '.') }}
                            </td>
                            <td class="p-3.5 text-center">
                                @php $opGrow = $pnl['summary']['operating_profit_previous'] != 0 ? round(($opVar / abs($pnl['summary']['operating_profit_previous'])) * 100, 2) : 0; @endphp
                                <span class="px-2.5 py-1 rounded-md font-mono font-bold text-[10px] {{ $opGrow >= 0 ? 'bg-[#fef9c3] text-[#854d0e]' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $opGrow >= 0 ? '+' : '' }}{{ $opGrow }}%
                                </span>
                            </td>
                            @if($canEdit ?? false)
                            <td class="p-3.5"></td>
                            @endif
                        </tr>
                        @endif

                    @endforeach

                    <!-- FINAL NET PROFIT ROW (SOLID FLAT - NO GRADIENT) -->
                    @php
                        $netCur = $pnl['summary']['net_profit_current'];
                        $netPrev = $pnl['summary']['net_profit_previous'];
                        $netVar = $pnl['summary']['net_profit_variance'];
                        $netGrow = $pnl['summary']['net_profit_growth'];
                    @endphp
                    <tr class="bg-[#fef9c3]/40 text-slate-900 font-black text-sm border-t-2 border-b-2 border-[#fde047] shadow-xs">
                        <td class="p-4 pl-6 font-mono text-xs text-[#ca8a04]">NET</td>
                        <td class="p-4 uppercase tracking-wider text-slate-900">LABA BERSIH HOLDING (NET PROFIT)</td>
                        <td class="p-4 text-right font-black font-mono tracking-tight {{ $netCur >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                            Rp {{ number_format($netCur, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-right font-mono tracking-tight text-slate-500">
                            Rp {{ number_format($netPrev, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-right font-mono tracking-tight {{ $netVar >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                            {{ $netVar >= 0 ? '+' : '' }}{{ number_format($netVar, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2.5 py-1 rounded-md font-mono font-bold text-xs {{ $netGrow >= 0 ? 'bg-[#fef9c3] text-[#713f12] border border-[#fde047]' : 'bg-rose-100 text-rose-800' }}">
                                {{ $netGrow >= 0 ? '+' : '' }}{{ $netGrow }}%
                            </span>
                        </td>
                        @if($canEdit ?? false)
                        <td class="p-4"></td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    const pnlTrends = @json($pnl['monthly_trends'] ?? []);
    const pnlTrendLabels = pnlTrends.map(t => t.label);
    const pnlRevData = pnlTrends.map(t => Number(t.revenue || 0));
    const pnlCogsData = pnlTrends.map(t => Number(t.cogs || 0));
    const pnlOpexData = pnlTrends.map(t => Number(t.opex || 0));
    const pnlProfitData = pnlTrends.map(t => Number(t.net_profit || 0));

    let pnlChart = null;

    function formatRupiahPnl(val) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val);
    }

    function renderPnlChart(type) {
        const canvas = document.getElementById('pnlFinancialChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (pnlChart) {
            pnlChart.destroy();
        }

        const isLine = type === 'line';

        // Warna Harmonis (Tema Jagung: Golden Corn & Husk Green Palette)
        // 1. Pendapatan (Golden Corn)
        const revGrad = ctx.createLinearGradient(0, 0, 0, 280);
        revGrad.addColorStop(0, 'rgba(202, 138, 4, 0.95)');
        revGrad.addColorStop(1, isLine ? 'rgba(202, 138, 4, 0.08)' : 'rgba(202, 138, 4, 0.45)');

        // 2. HPP (COGS) (Maize Amber)
        const cogsGrad = ctx.createLinearGradient(0, 0, 0, 280);
        cogsGrad.addColorStop(0, 'rgba(133, 77, 14, 0.95)');
        cogsGrad.addColorStop(1, isLine ? 'rgba(133, 77, 14, 0.08)' : 'rgba(133, 77, 14, 0.45)');

        // 3. Biaya Operasional (Olive Husk)
        const opexGrad = ctx.createLinearGradient(0, 0, 0, 280);
        opexGrad.addColorStop(0, 'rgba(101, 163, 13, 0.90)');
        opexGrad.addColorStop(1, isLine ? 'rgba(101, 163, 13, 0.08)' : 'rgba(101, 163, 13, 0.45)');

        // 4. Laba Bersih (Husk Green)
        const profitGrad = ctx.createLinearGradient(0, 0, 0, 280);
        profitGrad.addColorStop(0, 'rgba(21, 128, 61, 0.95)');
        profitGrad.addColorStop(1, isLine ? 'rgba(21, 128, 61, 0.08)' : 'rgba(21, 128, 61, 0.45)');

        const datasets = isLine ? [
            {
                label: 'Pendapatan',
                data: pnlRevData,
                borderColor: '#ca8a04',
                backgroundColor: '#ca8a04',
                borderWidth: 2.5,
                tension: 0.3,
                fill: false,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#ca8a04',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'HPP',
                data: pnlCogsData,
                borderColor: '#854d0e',
                backgroundColor: '#854d0e',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                pointRadius: 3.5,
                pointHoverRadius: 5.5,
                pointBackgroundColor: '#854d0e',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'Biaya Operasional',
                data: pnlOpexData,
                borderColor: '#65a30d',
                backgroundColor: '#65a30d',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                pointRadius: 3.5,
                pointHoverRadius: 5.5,
                pointBackgroundColor: '#65a30d',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
            {
                label: 'Laba Bersih',
                data: pnlProfitData,
                borderColor: '#15803d',
                backgroundColor: '#15803d',
                borderWidth: 2.5,
                tension: 0.3,
                fill: false,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#15803d',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            }
        ] : [
            {
                label: 'Pendapatan',
                data: pnlRevData,
                backgroundColor: revGrad,
                borderColor: '#ca8a04',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'HPP',
                data: pnlCogsData,
                backgroundColor: cogsGrad,
                borderColor: '#854d0e',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'Biaya Operasional',
                data: pnlOpexData,
                backgroundColor: opexGrad,
                borderColor: '#65a30d',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
            {
                label: 'Laba Bersih',
                data: pnlProfitData,
                backgroundColor: profitGrad,
                borderColor: '#15803d',
                borderWidth: 1,
                borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            }
        ];

        pnlChart = new Chart(ctx, {
            type: type,
            data: {
                labels: pnlTrendLabels,
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
                                const omzet = pnlRevData[idx] || 0;
                                let suffix = '';
                                if (context.dataset.label === 'Laba Bersih' && omzet > 0) {
                                    const margin = ((val / omzet) * 100).toFixed(1);
                                    suffix = ' (' + margin + '% margin)';
                                }
                                return ' ' + context.dataset.label + ': ' + formatRupiahPnl(val) + suffix;
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

    function setPnlChartType(type) {
        const btnBar = document.getElementById('btnPnlChartBar');
        const btnLine = document.getElementById('btnPnlChartLine');

        if (type === 'bar') {
            btnBar.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs';
            btnLine.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800';
        } else {
            btnLine.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 bg-white text-slate-900 font-bold shadow-2xs';
            btnBar.className = 'px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5 text-slate-500 hover:text-slate-800';
        }

        renderPnlChart(type);
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderPnlChart('bar');
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });

    function openAddAccountModal(reportType, buCode, entityCode) {
        document.getElementById('modalPnlTitle').innerText = 'Tambah Akun P&L';
        document.getElementById('pnlRecordId').value = '';
        document.getElementById('pnlBuCode').value = buCode;
        document.getElementById('pnlEntityCode').value = entityCode || '';
        document.getElementById('pnlCategory').value = 'revenue';
        document.getElementById('pnlAccountCode').value = '';
        document.getElementById('pnlAccountName').value = '';
        document.getElementById('pnlAmount').value = '';
        document.getElementById('pnlNotes').value = '';
        const btnDel = document.getElementById('btnDeletePnlRecord');
        if (btnDel) btnDel.classList.add('hidden');
        document.getElementById('modalAccountPnl').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function openEditAccountModal(data) {
        document.getElementById('modalPnlTitle').innerText = 'Edit Nominal Akun P&L';
        document.getElementById('pnlRecordId').value = data.id || '';
        document.getElementById('pnlBuCode').value = data.business_unit_code || '{{ $selectedBu }}';
        document.getElementById('pnlEntityCode').value = data.entity_code || '';
        document.getElementById('pnlCategory').value = data.category || 'revenue';
        document.getElementById('pnlAccountCode').value = data.account_code || '';
        document.getElementById('pnlAccountName').value = data.account_name || '';
        document.getElementById('pnlAmount').value = data.amount || 0;
        document.getElementById('pnlNotes').value = data.notes || '';
        
        const btnDel = document.getElementById('btnDeletePnlRecord');
        if (btnDel) {
            if (data.id) {
                btnDel.classList.remove('hidden');
            } else {
                btnDel.classList.add('hidden');
            }
        }
        
        document.getElementById('modalAccountPnl').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function closeAccountModal() {
        const modal = document.getElementById('modalAccountPnl');
        if (modal) modal.classList.add('hidden');
    }

    function deleteAccountRecord() {
        const id = document.getElementById('pnlRecordId').value;
        if (!id) return;
        if (!confirm('Apakah Anda yakin ingin menghapus akun ini?')) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/records/' + id;
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
</script>

@if($canEdit ?? false)
<!-- MODAL TAMBAH / EDIT AKUN PNL -->
<div id="modalAccountPnl" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-[#e9e5d6] shadow-xl overflow-hidden">
        <div class="p-5 border-b border-[#e9e5d6] flex items-center justify-between bg-[#faf8f0]">
            <div class="flex items-center gap-2">
                <i data-lucide="edit-3" class="w-4 h-4 text-[#ca8a04]"></i>
                <h4 id="modalPnlTitle" class="text-sm font-bold text-slate-900 uppercase">Tambah Akun P&L</h4>
            </div>
            <button type="button" onclick="closeAccountModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="formPnlAccount" action="{{ route('finance.records.save') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="id" id="pnlRecordId" value="">
            <input type="hidden" name="report_type" value="pnl">
            <input type="hidden" name="business_unit_code" id="pnlBuCode" value="{{ $selectedBu }}">
            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
            <input type="hidden" name="entity_code" id="pnlEntityCode" value="{{ $selectedEntity }}">

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori Akun <span class="text-rose-500">*</span></label>
                <select name="category" id="pnlCategory" required class="w-full text-xs font-semibold border border-slate-300 rounded-xl px-3 py-2 bg-white focus:outline-none focus:border-[#ca8a04]">
                    <option value="revenue">Pendapatan Usaha (Revenue)</option>
                    <option value="cogs">Harga Pokok Penjualan (COGS)</option>
                    <option value="opex">Beban Operasional (Opex)</option>
                    <option value="other_income">Pendapatan Diluar Usaha</option>
                    <option value="other_expense">Beban Diluar Usaha</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kode Akun</label>
                    <input type="text" name="account_code" id="pnlAccountCode" placeholder="contoh: 4001" class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-[#ca8a04]">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Akun / Uraian <span class="text-rose-500">*</span></label>
                    <input type="text" name="account_name" id="pnlAccountName" required placeholder="Nama Akun" class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-[#ca8a04]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nominal Periode Ini (Rp) <span class="text-rose-500">*</span></label>
                <input type="number" step="0.01" name="amount" id="pnlAmount" required placeholder="0" class="w-full text-xs font-mono font-bold border border-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-[#ca8a04]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan / Keterangan (Opsional)</label>
                <input type="text" name="notes" id="pnlNotes" placeholder="Catatan transaksi..." class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-[#ca8a04]">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <button type="button" id="btnDeletePnlRecord" onclick="deleteAccountRecord()" class="hidden px-3 py-2 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition cursor-pointer">
                    Hapus Akun
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" onclick="closeAccountModal()" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-[#ca8a04] hover:bg-[#a16207] text-white transition shadow-2xs cursor-pointer">
                        Simpan Akun
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
