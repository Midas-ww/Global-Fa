@extends('layouts.app')

@section('title', 'Neraca Konsolidasi')

@section('content')
<div class="space-y-6 pb-12">

    @if(session('success'))
    <div class="p-4 rounded-xl bg-[#edf5ee] border border-[#c2e7cb] text-[#14532d] text-xs font-semibold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    @endif

    <!-- FILTER TABS & STATUS BAR -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-[#e9e5d6] shadow-xs space-y-4">
        <!-- TOP: Quick Tabs for Business Unit -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <!-- ALL / CONSOLIDATED TAB -->
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ empty($selectedBu) ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-white hover:bg-[#fef9c3] text-slate-700 hover:text-[#713f12] border border-slate-200' }}">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="layers" class="w-3.5 h-3.5 {{ empty($selectedBu) ? 'text-[#ca8a04]' : 'text-slate-400' }}"></i>
                        <span>Semua (Konsolidasi Holding)</span>
                    </span>
                </a>

                @foreach($businessUnits as $bu)
                <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $bu['code']]) }}"
                   class="px-3.5 py-1.5 rounded-tl-[1rem] rounded-br-[1rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1rem] hover:rounded-bl-[1rem] hover:rounded-tl-none hover:rounded-br-none text-xs font-bold transition-all duration-300 cursor-pointer {{ $selectedBu === $bu['code'] ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-white hover:bg-[#fef9c3] text-slate-700 hover:text-[#713f12] border border-slate-200' }}">
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
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-[#faf8f0] border border-[#e9e5d6] rounded-xl shadow-2xs">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#ca8a04] shrink-0"></i>
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

                @if($selectedBu === 'spbu')
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

                @if($bs['is_balanced'])
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#edf5ee] text-[#14532d] border border-[#c2e7cb] text-xs font-black shadow-2xs">
                    <i data-lucide="check-circle" class="w-4 h-4 text-[#15803d]"></i>
                    <span class="hidden sm:inline">NERACA SEIMBANG</span>
                    <span class="sm:hidden">BALANCED</span>
                </div>
                @else
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#fef9c3] text-[#713f12] border border-[#fde047] text-xs font-black shadow-2xs">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-[#ca8a04]"></i>
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
                'kandang' => 'Kandang',
                default => 'Cabang / Entitas',
            };
        @endphp
        @if(count($entities) > 0 && ($hasEntitySpecificBs ?? true))
        <div class="pt-3 border-t border-[#e9e5d6] flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-bold text-slate-700 mr-1 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $activeBu['color'] ?? '#ca8a04' }}"></span>
                    <span>{{ $entityLabel }} ({{ count($entities) }}):</span>
                </span>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ empty($selectedEntity) ? 'bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
                        Semua Cabang
                    </a>
                    @foreach($entities as $e)
                    <a href="{{ route('finance.balance-sheet', ['period' => $selectedPeriod, 'business_unit' => $selectedBu, 'entity' => $e['code']]) }}"
                       class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all {{ $selectedEntity === $e['code'] ? 'bg-[#ca8a04]/10 text-[#713f12] font-bold border border-[#fde047] shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200' }}">
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
        <div class="bg-white rounded-2xl border border-[#e9e5d6] shadow-xs flex flex-col justify-between overflow-hidden">
            <div>
                <div class="p-4 bg-white border-b border-[#e9e5d6] text-slate-900 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="briefcase" class="w-4 h-4 text-[#ca8a04]"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">AKTIVA (ASSETS)</h4>
                    </div>
                    <span class="text-xs font-mono text-[#713f12] font-semibold px-2.5 py-1 rounded-md bg-[#fef9c3] border border-[#fde047]">{{ $bs['period_label'] }}</span>
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
                                <tr class="hover:bg-[#fef9c3]/40">
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
            <div class="p-4 bg-[#fef9c3]/70 flex items-center justify-between text-slate-900 mt-auto">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL AKTIVA (ASSETS)</span>
                <span class="text-base font-black font-mono text-slate-900">Rp {{ number_format($bs['assets']['total'], 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- RIGHT COLUMN: PASIVA (LIABILITIES & EQUITY) -->
        <div class="bg-white rounded-2xl border border-[#e9e5d6] shadow-xs flex flex-col justify-between overflow-hidden">
            <div class="flex-1 flex flex-col min-h-0">
                <div class="p-4 bg-white border-b border-[#e9e5d6] text-slate-900 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="shield-check" class="w-4 h-4 text-[#ca8a04]"></i>
                        <h4 class="font-bold text-sm tracking-wide uppercase">PASIVA (KEWAJIBAN & EKUITAS)</h4>
                    </div>
                    <span class="text-xs font-mono text-[#713f12] font-semibold px-2.5 py-1 rounded-md bg-[#fef9c3] border border-[#fde047]">{{ $bs['period_label'] }}</span>
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
            <div class="p-4 bg-[#fef9c3]/70 flex items-center justify-between text-slate-900 mt-auto">
                <span class="font-black uppercase tracking-wide text-xs">TOTAL PASIVA (KEWAJIBAN + EKUITAS)</span>
                <span class="text-base font-black font-mono text-slate-900">Rp {{ number_format($bs['total_liabilities_and_equity'], 0, ',', '.') }}</span>
            </div>
        </div>

    </div>

    @if(empty($selectedBu))
    <!-- INTERCOMPANY ELIMINATIONS TABLE -->
    <div class="bg-white rounded-2xl border border-[#e9e5d6] shadow-xs overflow-hidden">
        <div class="p-5 border-b border-[#e9e5d6] flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="font-bold text-slate-900 text-sm">Eliminasi Transaksi Antar Entitas Holding (Intercompany Eliminations)</h4>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#edf5ee] text-[#14532d] border border-[#c2e7cb] flex items-center gap-1.5 shadow-2xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Otomatisasi Rule-Based Aktif
                    </span>
                </div>
                <p class="text-xs text-slate-500">Penyesuaian saldo piutang/hutang dan transaksi internal antar unit anak perusahaan grup</p>
            </div>
            
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" onclick="document.getElementById('modalEliminationRules').classList.remove('hidden')"
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-slate-500"></i>
                    Kelola Aturan ({{ count($bs['eliminations']['rules'] ?? []) }})
                </button>

                <form action="{{ route('finance.eliminations.sync') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                        class="px-3 py-1.5 rounded-lg bg-[#ca8a04]/10 hover:bg-[#ca8a04]/20 text-[#713f12] border border-[#fde047] text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        title="Perbarui & sinkronkan ulang seluruh data eliminasi antar entitas">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-[#ca8a04]"></i>
                        Sinkronkan Ulang
                    </button>
                </form>

                <div class="text-xs font-black text-slate-700 pl-2 border-l border-slate-200">
                    Total Dieliminasi: <span class="text-rose-600 font-mono font-bold">Rp {{ number_format($bs['eliminations']['total_elimination'], 0, ',', '.') }}</span>
                </div>
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
                        <td class="p-3 pl-6 font-bold text-slate-800">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-mono font-bold text-[11px] border border-slate-200">
                                {{ $elm->from_entity_code }}
                            </span>
                        </td>
                        <td class="p-3 font-bold text-slate-800">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-mono font-bold text-[11px] border border-slate-200">
                                {{ $elm->to_entity_code }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#fef9c3] text-[#713f12] border border-[#fde047] uppercase">
                                {{ str_replace('_', ' ', $elm->category) }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-600 font-normal">{{ $elm->description }}</td>
                        <td class="p-3 text-right pr-6 font-black font-mono text-rose-600">
                            Rp {{ number_format($elm->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-400 italic">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <i data-lucide="info" class="w-5 h-5 text-slate-300"></i>
                                <span>Tidak ada transaksi eliminasi antar entitas yang aktif pada periode ini.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL KELOLA ATURAN ELIMINASI INTERCOMPANY -->
    <div id="modalEliminationRules" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#ca8a04]/10 text-[#713f12] border border-[#fde047] flex items-center justify-center font-bold">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4 text-[#ca8a04]"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Daftar Aturan Eliminasi Otomatis (Intercompany Rules)</h3>
                        <p class="text-[11px] text-slate-400">Aturan pemetaan akun internal yang otomatis ditarik dan dieliminasi tiap bulan</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('modalEliminationRules').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto custom-scrollbar">
                @foreach(($bs['eliminations']['rules'] ?? []) as $rule)
                <div class="p-3.5 rounded-xl border {{ $rule->is_active ? 'border-slate-200 bg-white' : 'border-slate-100 bg-slate-50/60 opacity-60' }} flex items-start justify-between gap-3 shadow-2xs">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $rule->from_entity_code }} ➔ {{ $rule->to_entity_code }}
                            </span>
                            <h5 class="text-xs font-bold text-slate-900">{{ $rule->name }}</h5>
                            @if($rule->is_active)
                                <span class="px-2 py-0.2 text-[9px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                            @else
                                <span class="px-2 py-0.2 text-[9px] font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200">Nonaktif</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Akun Sumber: <code class="font-mono font-bold text-slate-700 bg-slate-100 px-1 py-0.5 rounded text-[10px]">{{ $rule->account_name_pattern }}</code>
                            • Laporan: <span class="font-semibold text-slate-600 uppercase">{{ $rule->source_report_type }} ({{ $rule->source_category }})</span>
                        </p>
                    </div>

                    <form action="{{ route('finance.eliminations.rules.toggle', $rule->id) }}" method="POST">
                        @csrf
                        <button type="submit" 
                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer border {{ $rule->is_active ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200' }}">
                            {{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <span class="text-[11px] text-slate-400">Aturan dieksekusi otomatis setiap ada data baru diimpor atau disinkronkan.</span>
                <button type="button" onclick="document.getElementById('modalEliminationRules').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
