@extends('layouts.app')

@section('title', 'Laba Rugi Konsolidasi')
@section('page_title', 'Laporan Laba Rugi (P&L) Konsolidasi Global')

@section('content')
<div class="space-y-6 pb-12">

    <!-- FILTER BAR -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <form action="{{ route('finance.pnl') }}" method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="period" value="{{ $selectedPeriod }}">

            <!-- Business Unit Filter -->
            <div class="flex items-center gap-2">
                <label for="bu" class="text-xs font-bold text-slate-500">Unit Bisnis:</label>
                <select name="business_unit" id="bu" onchange="this.form.submit()" class="text-xs font-semibold bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-800">
                    <option value="">-- Semua Unit Bisnis --</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu['code'] }}" {{ $selectedBu === $bu['code'] ? 'selected' : '' }}>
                            {{ $bu['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if($selectedBu)
            <!-- Entity Filter -->
            @php
                $activeBu = collect($businessUnits)->firstWhere('code', $selectedBu);
                $entities = $activeBu['entities'] ?? [];
            @endphp
            @if(count($entities) > 0)
            <div class="flex items-center gap-2">
                <label for="ent" class="text-xs font-bold text-slate-500">Cabang/Entitas:</label>
                <select name="entity" id="ent" onchange="this.form.submit()" class="text-xs font-semibold bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-800">
                    <option value="">-- Semua Cabang/Depo --</option>
                    @foreach($entities as $e)
                        <option value="{{ $e['code'] }}" {{ $selectedEntity === $e['code'] ? 'selected' : '' }}>
                            {{ $e['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            @endif

            @if($selectedBu || $selectedEntity)
            <a href="{{ route('finance.pnl', ['period' => $selectedPeriod]) }}" class="text-xs text-rose-600 hover:text-rose-800 font-bold flex items-center gap-1">
                <i data-lucide="x" class="w-3.5 h-3.5"></i> Reset Filter
            </a>
            @endif
        </form>

        <div class="text-xs font-semibold text-slate-500">
            Komparasi: <span class="font-bold text-slate-900">{{ $pnl['period_label'] }}</span> vs <span class="font-bold text-slate-700">{{ $pnl['previous_period_label'] }}</span>
        </div>
    </div>

    <!-- PNL STATEMENT TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">LAPORAN LABA RUGI TERKONSOLIDASI</h3>
                <p class="text-xs text-slate-500">Periode berakhir per {{ $pnl['period_label'] }} (Mata Uang: IDR Rupiah)</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 text-white uppercase text-[11px] font-bold tracking-wider">
                        <th class="p-3.5 pl-6 w-24">Kode Akun</th>
                        <th class="p-3.5">Keterangan / Uraian Akun</th>
                        <th class="p-3.5 text-right w-44">{{ $pnl['period_label'] }}</th>
                        <th class="p-3.5 text-right w-44 text-slate-300">{{ $pnl['previous_period_label'] }}</th>
                        <th class="p-3.5 text-right w-36">Selisih (MoM)</th>
                        <th class="p-3.5 text-center w-24">% Growth</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    
                    @foreach($pnl['sections'] as $secKey => $section)
                        <!-- Section Header -->
                        <tr class="bg-slate-100/80 font-extrabold text-slate-800">
                            <td colspan="6" class="p-3 pl-6 tracking-wide text-[11px] uppercase text-blue-900 flex items-center gap-2">
                                <i data-lucide="folder" class="w-3.5 h-3.5 text-blue-600"></i>
                                {{ $section['title'] }}
                            </td>
                        </tr>

                        <!-- Section Items -->
                        @forelse($section['items'] as $item)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-2.5 pl-6 font-mono text-[11px] text-slate-400">{{ $item['account_code'] ?? '-' }}</td>
                            <td class="p-2.5 text-slate-800">{{ $item['account_name'] }}</td>
                            <td class="p-2.5 text-right font-semibold text-slate-900">Rp {{ number_format($item['current_amount'], 0, ',', '.') }}</td>
                            <td class="p-2.5 text-right text-slate-500">Rp {{ number_format($item['previous_amount'], 0, ',', '.') }}</td>
                            <td class="p-2.5 text-right font-semibold {{ $item['variance'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 0, ',', '.') }}
                            </td>
                            <td class="p-2.5 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $item['growth_pct'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $item['growth_pct'] >= 0 ? '+' : '' }}{{ $item['growth_pct'] }}%
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-3 pl-6 text-slate-400 italic text-center">Tidak ada transaksi akun pada kategori ini.</td>
                        </tr>
                        @endforelse

                        <!-- Subtotal Section -->
                        <tr class="bg-slate-50 font-bold border-t border-b border-slate-200">
                            <td class="p-3 pl-6 font-mono text-[11px] text-slate-500">SUBTOTAL</td>
                            <td class="p-3 uppercase text-slate-700">TOTAL {{ $section['title'] }}</td>
                            <td class="p-3 text-right text-slate-900 font-extrabold">Rp {{ number_format($section['subtotal_current'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right text-slate-600">Rp {{ number_format($section['subtotal_previous'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right font-extrabold {{ $section['variance'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $section['variance'] >= 0 ? '+' : '' }}{{ number_format($section['variance'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded font-extrabold text-[10px] {{ $section['growth_pct'] >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $section['growth_pct'] >= 0 ? '+' : '' }}{{ $section['growth_pct'] }}%
                                </span>
                            </td>
                        </tr>

                        <!-- Insert Gross Profit row after COGS -->
                        @if($secKey === 'cogs')
                        <tr class="bg-indigo-50/80 font-extrabold text-indigo-950 border-t-2 border-b-2 border-indigo-200">
                            <td class="p-3.5 pl-6 font-mono text-[11px]">GP</td>
                            <td class="p-3.5 uppercase tracking-wide">LABA KOTOR (GROSS PROFIT)</td>
                            <td class="p-3.5 text-right font-extrabold text-indigo-700">Rp {{ number_format($pnl['summary']['gross_profit_current'], 0, ',', '.') }}</td>
                            <td class="p-3.5 text-right text-indigo-900/60">Rp {{ number_format($pnl['summary']['gross_profit_previous'], 0, ',', '.') }}</td>
                            @php $gpVar = $pnl['summary']['gross_profit_current'] - $pnl['summary']['gross_profit_previous']; @endphp
                            <td class="p-3.5 text-right {{ $gpVar >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $gpVar >= 0 ? '+' : '' }}{{ number_format($gpVar, 0, ',', '.') }}
                            </td>
                            <td class="p-3.5 text-center">
                                @php $gpGrow = $pnl['summary']['gross_profit_previous'] != 0 ? round(($gpVar / abs($pnl['summary']['gross_profit_previous'])) * 100, 2) : 0; @endphp
                                <span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $gpGrow >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $gpGrow >= 0 ? '+' : '' }}{{ $gpGrow }}%
                                </span>
                            </td>
                        </tr>
                        @endif

                        <!-- Insert Operating Profit row after OPEX -->
                        @if($secKey === 'opex')
                        <tr class="bg-amber-50/80 font-extrabold text-amber-950 border-t-2 border-b-2 border-amber-200">
                            <td class="p-3.5 pl-6 font-mono text-[11px]">OP</td>
                            <td class="p-3.5 uppercase tracking-wide">LABA USAHA (OPERATING PROFIT)</td>
                            <td class="p-3.5 text-right font-extrabold text-amber-800">Rp {{ number_format($pnl['summary']['operating_profit_current'], 0, ',', '.') }}</td>
                            <td class="p-3.5 text-right text-amber-900/60">Rp {{ number_format($pnl['summary']['operating_profit_previous'], 0, ',', '.') }}</td>
                            @php $opVar = $pnl['summary']['operating_profit_current'] - $pnl['summary']['operating_profit_previous']; @endphp
                            <td class="p-3.5 text-right {{ $opVar >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $opVar >= 0 ? '+' : '' }}{{ number_format($opVar, 0, ',', '.') }}
                            </td>
                            <td class="p-3.5 text-center">
                                @php $opGrow = $pnl['summary']['operating_profit_previous'] != 0 ? round(($opVar / abs($pnl['summary']['operating_profit_previous'])) * 100, 2) : 0; @endphp
                                <span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $opGrow >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $opGrow >= 0 ? '+' : '' }}{{ $opGrow }}%
                                </span>
                            </td>
                        </tr>
                        @endif

                    @endforeach

                    <!-- FINAL NET PROFIT ROW -->
                    @php
                        $netCur = $pnl['summary']['net_profit_current'];
                        $netPrev = $pnl['summary']['net_profit_previous'];
                        $netVar = $pnl['summary']['net_profit_variance'];
                        $netGrow = $pnl['summary']['net_profit_growth'];
                    @endphp
                    <tr class="bg-slate-900 text-white font-extrabold text-sm border-t-4 border-slate-700">
                        <td class="p-4 pl-6 font-mono text-xs text-blue-400">NET</td>
                        <td class="p-4 uppercase tracking-wider text-white">LABA BERSIH HOLDING (NET PROFIT)</td>
                        <td class="p-4 text-right font-extrabold {{ $netCur >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            Rp {{ number_format($netCur, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-right text-slate-300">
                            Rp {{ number_format($netPrev, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-right {{ $netVar >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $netVar >= 0 ? '+' : '' }}{{ number_format($netVar, 0, ',', '.') }}
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2.5 py-1 rounded-md font-bold text-xs {{ $netGrow >= 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ $netGrow >= 0 ? '+' : '' }}{{ $netGrow }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
