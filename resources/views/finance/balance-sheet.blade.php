@extends('layouts.app')

@section('title', 'Neraca Konsolidasi')

@section('content')
<div class="space-y-6 pb-12">

    <!-- FILTER TABS & STATUS BAR -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-[#f0efe6] shadow-xs space-y-4">
        <!-- TOP: Quick Tabs for Business Unit -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <!-- ALL / CONSOLIDATED TAB -->
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ empty($selectedBu) ? 'bg-amber-500/15 text-amber-950 border border-amber-300 shadow-2xs' : 'bg-white hover:bg-amber-50 text-slate-700 hover:text-amber-900 border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="layers" class="w-3.5 h-3.5 {{ empty($selectedBu) ? 'text-amber-700' : 'text-slate-400' }}"></i>
                        <span>Semua (Konsolidasi Holding)</span>
                    </span>
                </a>

                @foreach($businessUnits as $bu)
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $bu['code']]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ $selectedBu === $bu['code'] ? 'bg-amber-500/15 text-amber-950 border border-amber-300 shadow-2xs' : 'bg-white hover:bg-amber-50 text-slate-700 hover:text-amber-900 border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $bu['color'] }}"></span>
                        <span>{{ $bu['name'] }}</span>
                    </span>
                </a>
                @endforeach
            </div>

            <!-- Period Selector & Balance Status Badge -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <form action="{{ route('finance.balance-sheet') }}" method="GET" class="flex items-center">
                    @if($selectedBu)
                        <input type="hidden" name="business_unit" value="{{ $selectedBu }}">
                    @endif
                    @if($selectedEntity)
                        <input type="hidden" name="entity" value="{{ $selectedEntity }}">
                    @endif
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-[#faf9f5] border border-[#f0efe6] rounded-xl shadow-2xs">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#d97706] shrink-0"></i>
                        <label for="periodSelectBs" class="text-xs font-semibold text-slate-500">Periode:</label>
                        <select name="period" id="periodSelectBs" onchange="this.form.submit()" class="text-xs font-bold font-mono bg-transparent text-slate-900 focus:outline-none cursor-pointer">
                            @foreach($availablePeriods as $p)
                                <option value="{{ $p }}" {{ $selectedPeriod === $p ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($p)->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                @if($bs['is_balanced'])
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-500/15 text-amber-950 border border-amber-300 text-xs font-black shadow-2xs">
                    <i data-lucide="check-circle" class="w-4 h-4 text-amber-700"></i>
                    <span class="hidden sm:inline">NERACA SEIMBANG</span>
                    <span class="sm:hidden">BALANCED</span>
                </div>
                @else
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#fef9c3] text-[#854d0e] border border-[#fef08a] text-xs font-black shadow-2xs">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-[#d97706]"></i>
                    <span>ADA SELISIH</span>
                </div>
                @endif
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
                default => 'Cabang / Entitas',
            };
        @endphp
        @if(count($entities) > 0)
        <div class="pt-3 border-t border-[#f0efe6] flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-bold text-slate-700 mr-1 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $activeBu['color'] ?? '#d97706' }}"></span>
                    <span>{{ $entityLabel }} ({{ count($entities) }}):</span>
                </span>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ empty($selectedEntity) ? 'bg-amber-500/15 text-amber-950 border border-amber-300 shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        Semua Cabang
                    </a>
                    @foreach($entities as $e)
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu, 'entity' => $e['code']]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all {{ $selectedEntity === $e['code'] ? 'bg-amber-500/15 text-amber-950 font-bold border border-amber-300 shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        {{ $e['name'] }}
                    </a>
                    @endforeach
                </div>
            </div>

            @if($selectedEntity)
            <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}" class="text-xs text-rose-600 hover:text-rose-800 font-bold flex items-center gap-1">
                <i data-lucide="x" class="w-3.5 h-3.5"></i> Reset Cabang
            </a>
            @endif
        </div>
        @endif
        @endif
    </div>

    <!-- BALANCE SHEET COMPARISON GRID (AKTIVA VS PASIVA) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- LEFT COLUMN: AKTIVA (ASSETS) -->
        <div class="bg-white rounded-2xl border border-[#edf0ee] shadow-xs flex flex-col justify-between overflow-hidden">
            <div>
                <div class="p-4 bg-white border-b border-[#f0efe6] text-slate-900 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="briefcase" class="w-4 h-4 text-[#d97706]"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">AKTIVA (ASSETS)</h4>
                    </div>
                    <span class="text-xs font-mono text-amber-900 font-semibold px-2.5 py-1 rounded-md bg-amber-50 border border-amber-300">{{ $bs['period_label'] }}</span>
                </div>

                <div class="p-4 flex-1 overflow-y-auto max-h-[580px] pr-2 custom-balance-scroll">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 z-10 bg-white">
                                <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-200">
                                    <th class="pb-2 bg-white">Kode Akun</th>
                                    <th class="pb-2 bg-white">Nama Akun Aktiva</th>
                                    <th class="pb-2 text-right bg-white">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse($bs['assets']['items'] as $item)
                                <tr class="hover:bg-amber-50/40">
                                    <td class="py-2.5 font-mono text-[11px] text-slate-400">{{ $item->account_code ?? '1000' }}</td>
                                    <td class="py-2.5 text-slate-800">
                                        <div class="font-semibold">{{ $item->account_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase font-mono">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                    </td>
                                    <td class="py-2.5 text-right font-bold font-mono tracking-tight text-slate-900">
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

            <!-- Total Assets Footer (SOLID FLAT - NO GRADIENT) -->
            <div class="p-4 bg-amber-50/70 flex items-center justify-between text-slate-900 mt-auto">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL AKTIVA (ASSETS)</span>
                <span class="text-base font-black font-mono text-slate-900">Rp {{ number_format($bs['assets']['total'], 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- RIGHT COLUMN: PASIVA (LIABILITIES & EQUITY) -->
        <div class="bg-white rounded-2xl border border-[#f0efe6] shadow-xs flex flex-col justify-between overflow-hidden">
            <div class="flex-1 flex flex-col min-h-0">
                <div class="p-4 bg-white border-b border-[#edf0ee] text-slate-900 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="shield-check" class="w-4 h-4 text-amber-500"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">PASIVA (KEWAJIBAN & EKUITAS)</h4>
                    </div>
                    <span class="text-xs font-mono text-amber-800 font-semibold px-2.5 py-1 rounded-md bg-[#fef9c3] border border-[#fef08a]">{{ $bs['period_label'] }}</span>
                </div>

                <div class="p-4 flex-1 space-y-6 overflow-y-auto max-h-[580px] pr-2 custom-balance-scroll">
                    
                    <!-- 1. LIABILITIES -->
                    <div>
                        <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-xs text-[11px] font-extrabold uppercase text-slate-500 tracking-wider py-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>1. KEWAJIBAN (LIABILITIES)</span>
                            <span class="text-slate-800 font-mono font-bold">Rp {{ number_format($bs['liabilities']['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse text-xs">
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    @forelse($bs['liabilities']['items'] as $item)
                                    <tr class="hover:bg-[#fbfdfa]">
                                        <td class="py-2 font-mono text-[11px] text-slate-400 w-20">{{ $item->account_code ?? '2000' }}</td>
                                        <td class="py-2 text-slate-800">
                                            <div class="font-semibold">{{ $item->account_name }}</div>
                                            <div class="text-[10px] text-slate-400 uppercase">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                        </td>
                                        <td class="py-2 text-right font-bold text-slate-900 font-mono">
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
                    </div>

                    <!-- 2. EQUITY -->
                    <div>
                        <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-xs text-[11px] font-extrabold uppercase text-slate-500 tracking-wider py-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>2. EKUITAS & LABA DITAHAN (EQUITY)</span>
                            <span class="text-slate-800 font-mono font-bold">Rp {{ number_format($bs['equity']['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse text-xs">
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    @forelse($bs['equity']['items'] as $item)
                                    <tr class="hover:bg-[#fbfdfa]">
                                        <td class="py-2 font-mono text-[11px] text-slate-400 w-20">{{ $item->account_code ?? '3000' }}</td>
                                        <td class="py-2 text-slate-800">
                                            <div class="font-semibold">{{ $item->account_name }}</div>
                                            <div class="text-[10px] text-slate-400 uppercase">{{ $item->business_unit_code }} &bull; {{ $item->entity_code }}</div>
                                        </td>
                                        <td class="py-2 text-right font-bold text-slate-900 font-mono">
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
            </div>

            <!-- Total Liabilities & Equity Footer (SOLID FLAT - NO GRADIENT) -->
            <div class="p-4 bg-amber-50/70 flex items-center justify-between text-slate-900 mt-auto">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL PASIVA (KEWAJIBAN + EKUITAS)</span>
                <span class="text-base font-black font-mono text-slate-900">Rp {{ number_format($bs['total_liabilities_and_equity'], 0, ',', '.') }}</span>
            </div>
        </div>

    </div>

    @if(empty($selectedBu))
    <!-- INTERCOMPANY ELIMINATIONS TABLE -->
    <div class="bg-white rounded-2xl border border-[#edf0ee] shadow-sm overflow-hidden">
        <div class="p-5 border-b border-[#edf0ee] flex items-center justify-between bg-white">
            <div>
                <h4 class="font-bold text-slate-900 text-sm">Eliminasi Transaksi Antar Entitas Holding (Intercompany Eliminations)</h4>
                <p class="text-xs text-slate-400">Penyesuaian saldo piutang/hutang dan penjualan internal antar unit grup</p>
            </div>
            <div class="text-xs font-black text-slate-700">
                Total Dieliminasi: <span class="text-rose-600 font-mono font-bold">Rp {{ number_format($bs['eliminations']['total_elimination'], 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-[#f8faf8] text-slate-400 font-bold uppercase text-[10px] border-b border-slate-200">
                        <th class="p-3 pl-6">Entitas Asal</th>
                        <th class="p-3">Entitas Tujuan</th>
                        <th class="p-3">Kategori Eliminasi</th>
                        <th class="p-3">Deskripsi Transaksi</th>
                        <th class="p-3 text-right pr-6">Nominal Dieliminasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($bs['eliminations']['items'] as $elm)
                    <tr class="hover:bg-[#fbfdfa]">
                        <td class="p-3 pl-6 font-bold text-slate-800">{{ $elm->from_entity_code }}</td>
                        <td class="p-3 font-bold text-slate-800">{{ $elm->to_entity_code }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase">
                                {{ str_replace('_', ' ', $elm->category) }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-600">{{ $elm->description }}</td>
                        <td class="p-3 text-right pr-6 font-black font-mono text-rose-600">
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
    @endif

</div>
@endsection
