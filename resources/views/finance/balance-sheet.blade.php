@extends('layouts.app')

@section('title', 'Neraca Konsolidasi')

@section('content')
<div class="space-y-6 pb-12">

    <!-- FILTER TABS & STATUS BAR -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-[#edf0ee] shadow-xs space-y-4">
        <!-- TOP: Quick Tabs for Business Unit -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <!-- ALL / CONSOLIDATED TAB -->
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ empty($selectedBu) ? 'bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d] shadow-2xs' : 'bg-white hover:bg-[#ebf8c8]/50 text-slate-700 hover:text-[#3f6212] border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="layers" class="w-3.5 h-3.5 {{ empty($selectedBu) ? 'text-[#3f6212]' : 'text-slate-400' }}"></i>
                        <span>Semua (Konsolidasi Holding)</span>
                    </span>
                </a>

                @foreach($businessUnits as $bu)
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $bu['code']]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ $selectedBu === $bu['code'] ? 'bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d] shadow-2xs' : 'bg-white hover:bg-[#ebf8c8]/50 text-slate-700 hover:text-[#3f6212] border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $bu['color'] }}"></span>
                        <span>{{ $bu['name'] }}</span>
                    </span>
                </a>
                @endforeach
            </div>

            <!-- Period Selector & Balance Status Badge & Print -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <form action="{{ route('finance.balance-sheet') }}" method="GET" class="flex items-center">
                    @if($selectedBu)
                        <input type="hidden" name="business_unit" value="{{ $selectedBu }}">
                    @endif
                    @if($selectedEntity)
                        <input type="hidden" name="entity" value="{{ $selectedEntity }}">
                    @endif
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-[#f8faf8] border border-[#edf0ee] rounded-xl shadow-2xs">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#ffd800] shrink-0"></i>
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
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d] text-xs font-black shadow-2xs">
                    <i data-lucide="check-circle" class="w-4 h-4 text-[#16a34a]"></i>
                    <span class="hidden sm:inline">NERACA SEIMBANG</span>
                    <span class="sm:hidden">BALANCED</span>
                </div>
                @else
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#fef9c3] text-[#854d0e] border border-[#fef08a] text-xs font-black shadow-2xs">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-[#d97706]"></i>
                    <span>ADA SELISIH</span>
                </div>
                @endif

                <button onclick="window.print()" class="px-3.5 py-2 rounded-xl border border-[#edf0ee] bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 flex items-center gap-1.5 shadow-2xs transition-all cursor-pointer">
                    <i data-lucide="printer" class="w-3.5 h-3.5 text-slate-500"></i> Cetak
                </button>
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
        <div class="pt-3 border-t border-[#edf0ee] flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-bold text-slate-700 mr-1 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $activeBu['color'] ?? '#10e58f' }}"></span>
                    <span>{{ $entityLabel }} ({{ count($entities) }}):</span>
                </span>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ empty($selectedEntity) ? 'bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        Semua Cabang
                    </a>
                    @foreach($entities as $e)
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu, 'entity' => $e['code']]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all {{ $selectedEntity === $e['code'] ? 'bg-[#ebf8c8] text-[#3f6212] font-bold border border-[#d9f99d] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
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
                <div class="p-4 bg-white border-b border-[#edf0ee] text-slate-900 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="briefcase" class="w-4 h-4 text-emerald-600"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">AKTIVA (ASSETS)</h4>
                    </div>
                    <span class="text-xs font-mono text-emerald-700 font-semibold px-2.5 py-1 rounded-md bg-[#ebf8c8] border border-[#d9f99d]">{{ $bs['period_label'] }}</span>
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
                                <tr class="hover:bg-[#fbfdfa]">
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
            <div class="p-4 bg-[#f5f9ed] border-t-2 border-[#d9f99d] flex items-center justify-between text-slate-900">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL AKTIVA (ASSETS)</span>
                <span class="text-base font-black font-mono text-[#15803d]">Rp {{ number_format($bs['assets']['total'], 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- RIGHT COLUMN: PASIVA (LIABILITIES & EQUITY) -->
        <div class="bg-white rounded-2xl border border-[#edf0ee] shadow-xs flex flex-col justify-between overflow-hidden">
            <div>
                <div class="p-4 bg-white border-b border-[#edf0ee] text-slate-900 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="shield-check" class="w-4 h-4 text-amber-500"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">PASIVA (KEWAJIBAN & EKUITAS)</h4>
                    </div>
                    <span class="text-xs font-mono text-amber-800 font-semibold px-2.5 py-1 rounded-md bg-[#fef9c3] border border-[#fef08a]">{{ $bs['period_label'] }}</span>
                </div>

                <div class="p-4 space-y-6">
                    
                    <!-- 1. LIABILITIES -->
                    <div>
                        <div class="text-[11px] font-extrabold uppercase text-slate-500 tracking-wider pb-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>1. KEWAJIBAN (LIABILITIES)</span>
                            <span class="text-slate-800 font-mono font-bold">Rp {{ number_format($bs['liabilities']['total'], 0, ',', '.') }}</span>
                        </div>
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

                    <!-- 2. EQUITY -->
                    <div>
                        <div class="text-[11px] font-extrabold uppercase text-slate-500 tracking-wider pb-1.5 border-b border-slate-200 mb-2 flex items-center justify-between">
                            <span>2. EKUITAS & LABA DITAHAN (EQUITY)</span>
                            <span class="text-slate-800 font-mono font-bold">Rp {{ number_format($bs['equity']['total'], 0, ',', '.') }}</span>
                        </div>
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

            <!-- Total Liabilities & Equity Footer (SOLID FLAT - NO GRADIENT) -->
            <div class="p-4 bg-[#fefce8] border-t-2 border-[#fef08a] flex items-center justify-between text-slate-900">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL PASIVA (KEWAJIBAN + EKUITAS)</span>
                <span class="text-base font-black font-mono text-[#854d0e]">Rp {{ number_format($bs['total_liabilities_and_equity'], 0, ',', '.') }}</span>
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

        <div class="overflow-x-auto">
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
