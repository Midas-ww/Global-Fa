@extends('layouts.app')

@section('title', 'Neraca Konsolidasi')
@section('page_title', 'Laporan Posisi Keuangan (Neraca) Konsolidasi Global')

@section('content')
<div class="space-y-6 pb-12">

    <!-- FILTER BAR & STATUS -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <form action="{{ route('finance.balance-sheet') }}" method="GET" class="flex flex-wrap items-center gap-3">
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
            <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod]) }}" class="text-xs text-rose-600 hover:text-rose-800 font-bold flex items-center gap-1">
                <i data-lucide="x" class="w-3.5 h-3.5"></i> Reset Filter
            </a>
            @endif
        </form>

        <!-- Balance Status Badge -->
        <div class="flex items-center gap-3">
            @if($bs['is_balanced'])
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-extrabold shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>STATUS: NERACA SEIMBANG (BALANCED)</span>
            </div>
            @else
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-extrabold shadow-sm">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                <span>STATUS: ADA SELISIH ELIMINASI / PROSES</span>
            </div>
            @endif

            <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 flex items-center gap-1.5 shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Neraca
            </button>
        </div>
    </div>

    <!-- BALANCE SHEET COMPARISON GRID (AKTIVA VS PASIVA) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- LEFT COLUMN: AKTIVA (ASSETS) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between overflow-hidden">
            <div>
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="briefcase" class="w-4 h-4 text-blue-400"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">AKTIVA (ASSETS)</h4>
                    </div>
                    <span class="text-xs font-mono text-blue-200 font-semibold">{{ $bs['period_label'] }}</span>
                </div>

                <div class="p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-200">
                                    <th class="pb-2">Kode Akun</th>
                                    <th class="pb-2">Nama Akun Aktiva</th>
                                    <th class="pb-2 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse($bs['assets']['items'] as $item)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2.5 font-mono text-[11px] text-slate-400">{{ $item->account_code ?? '1000' }}</td>
                                    <td class="py-2.5 text-slate-800">
                                        <div class="font-semibold">{{ $item->account_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                    </td>
                                    <td class="py-2.5 text-right font-bold text-slate-900">
                                        Rp {{ number_format($item->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-400 italic">Belum ada akun aktiva yang dicatat untuk periode ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Total Assets Footer -->
            <div class="p-4 bg-blue-50 border-t border-blue-100 flex items-center justify-between text-blue-900">
                <span class="font-extrabold uppercase tracking-wide text-xs">TOTAL AKTIVA (ASSETS)</span>
                <span class="text-base font-extrabold text-blue-700">Rp {{ number_format($bs['assets']['total'], 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- RIGHT COLUMN: PASIVA (LIABILITIES & EQUITY) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between overflow-hidden">
            <div>
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">PASIVA (KEWAJIBAN & EKUITAS)</h4>
                    </div>
                    <span class="text-xs font-mono text-emerald-200 font-semibold">{{ $bs['period_label'] }}</span>
                </div>

                <div class="p-4 space-y-6">
                    
                    <!-- 1. LIABILITIES -->
                    <div>
                        <div class="text-[11px] font-extrabold uppercase text-slate-500 tracking-wider pb-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>1. KEWAJIBAN (LIABILITIES)</span>
                            <span class="text-slate-800">Rp {{ number_format($bs['liabilities']['total'], 0, ',', '.') }}</span>
                        </div>
                        <table class="w-full text-left border-collapse text-xs">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse($bs['liabilities']['items'] as $item)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2 font-mono text-[11px] text-slate-400 w-20">{{ $item->account_code ?? '2000' }}</td>
                                    <td class="py-2 text-slate-800">
                                        <div class="font-semibold">{{ $item->account_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                    </td>
                                    <td class="py-2 text-right font-bold text-slate-900">
                                        Rp {{ number_format($item->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="py-2 text-slate-400 italic text-center">Tidak ada kewajiban.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 2. EQUITY -->
                    <div>
                        <div class="text-[11px] font-extrabold uppercase text-slate-500 tracking-wider pb-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>2. EKUITAS & LABA DITAHAN (EQUITY)</span>
                            <span class="text-slate-800">Rp {{ number_format($bs['equity']['total'], 0, ',', '.') }}</span>
                        </div>
                        <table class="w-full text-left border-collapse text-xs">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse($bs['equity']['items'] as $item)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2 font-mono text-[11px] text-slate-400 w-20">{{ $item->account_code ?? '3000' }}</td>
                                    <td class="py-2 text-slate-800">
                                        <div class="font-semibold">{{ $item->account_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                    </td>
                                    <td class="py-2 text-right font-bold text-slate-900">
                                        Rp {{ number_format($item->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="py-2 text-slate-400 italic text-center">Tidak ada ekuitas.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <!-- Total Liabilities & Equity Footer -->
            <div class="p-4 bg-emerald-50 border-t border-emerald-100 flex items-center justify-between text-emerald-900">
                <span class="font-extrabold uppercase tracking-wide text-xs">TOTAL PASIVA (KEWAJIBAN + EKUITAS)</span>
                <span class="text-base font-extrabold text-emerald-700">Rp {{ number_format($bs['total_liabilities_and_equity'], 0, ',', '.') }}</span>
            </div>
        </div>

    </div>

    <!-- INTERCOMPANY ELIMINATIONS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h4 class="font-bold text-slate-900 text-sm">Eliminasi Transaksi Antar Entitas Holding (Intercompany Eliminations)</h4>
                <p class="text-xs text-slate-400">Penyesuaian saldo piutang/hutang dan penjualan internal antar unit grup</p>
            </div>
            <div class="text-xs font-extrabold text-slate-700">
                Total Dieliminasi: <span class="text-rose-600">Rp {{ number_format($bs['eliminations']['total_elimination'], 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px] border-b border-slate-200">
                        <th class="p-3 pl-6">Entitas Asal</th>
                        <th class="p-3">Entitas Tujuan</th>
                        <th class="p-3">Kategori Eliminasi</th>
                        <th class="p-3">Deskripsi Transaksi</th>
                        <th class="p-3 text-right pr-6">Nominal Dieliminasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($bs['eliminations']['items'] as $elm)
                    <tr class="hover:bg-slate-50/70">
                        <td class="p-3 pl-6 font-bold text-slate-800">{{ $elm->from_entity_code }}</td>
                        <td class="p-3 font-bold text-slate-800">{{ $elm->to_entity_code }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase">
                                {{ str_replace('_', ' ', $elm->category) }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-600">{{ $elm->description }}</td>
                        <td class="p-3 text-right pr-6 font-extrabold text-rose-600">
                            Rp {{ number_format($elm->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-4 text-center text-slate-400 italic">Tidak ada transaksi eliminasi antar entitas pada periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
