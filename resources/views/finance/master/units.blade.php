@extends('layouts.app')

@section('title', 'Master Data Unit Usaha & Entitas - Mekar Jaya Group')

@section('content')
<div class="space-y-6">

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-[#edf0ee] shadow-xs">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d] uppercase tracking-wide">
                    Portofolio & Organisasi
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-500">Mekar Jaya Group Holding</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Master Data Unit Usaha & Entitas</h1>
            <p class="text-xs text-slate-500">
                Pusat pengaturan portofolio bisnis, pendaftaran cabang/entitas baru, dan pengunduhan template resmi import finansial.
            </p>
        </div>
        
        <div class="flex items-center gap-2.5 flex-wrap">
            <button onclick="document.getElementById('modalAddUnit').classList.remove('hidden')" 
                class="px-4 py-2.5 rounded-xl bg-[#10e58f] hover:bg-[#0fd684] text-slate-950 font-bold text-xs transition flex items-center gap-2 shadow-2xs cursor-pointer">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                Tambah Unit Usaha Baru
            </button>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2 shadow-2xs">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2 shadow-2xs">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1 shadow-2xs">
        <div class="font-bold flex items-center gap-1.5 text-rose-900">
            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
            Terdapat kesalahan pada input:
        </div>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- STATS OVERVIEW -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-[#edf0ee] shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#ebf8c8] text-[#3f6212] flex items-center justify-center font-black">
                <i data-lucide="building-2" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Unit Usaha</p>
                <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $stats['total_units'] }} <span class="text-xs font-normal text-slate-400">Bisnis</span></h3>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#edf0ee] shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-black border border-emerald-200">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Entitas / Cabang</p>
                <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $stats['total_entities'] }} <span class="text-xs font-normal text-slate-400">Cabang</span></h3>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#edf0ee] shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#fef9c3] text-[#854d0e] flex items-center justify-center font-black border border-[#fef08a]">
                <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sumber Berkas Excel</p>
                <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $stats['excel_units'] }} <span class="text-xs font-normal text-slate-400">Unit</span></h3>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#edf0ee] shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center font-black border border-rose-200">
                <i data-lucide="database" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sumber Database Sync</p>
                <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $stats['db_units'] }} <span class="text-xs font-normal text-slate-400">Unit</span></h3>
            </div>
        </div>
    </div>

    <!-- BUSINESS UNITS ACCORDION / LIST -->
    <div class="space-y-4">
        <div class="flex items-center justify-between px-1">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="briefcase" class="w-4 h-4 text-emerald-600"></i>
                Daftar Unit Usaha & Portofolio Bisnis
            </h2>
            <span class="text-xs text-slate-400">Klik unit untuk melihat rincian cabang & pengaturan</span>
        </div>

        @foreach($units as $unit)
        <div class="bg-white rounded-2xl border border-[#edf0ee] shadow-xs overflow-hidden transition-all hover:border-slate-300">
            <!-- UNIT HEADER CARD -->
            <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border-b border-[#edf0ee]">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-slate-900 shrink-0 font-black shadow-2xs"
                         style="background-color: {{ $unit->color ?: '#ebf8c8' }}20; border: 1.5px solid {{ $unit->color ?: '#10e58f' }}; color: {{ $unit->color ?: '#166534' }};">
                        <i data-lucide="{{ $unit->icon ?: 'building-2' }}" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-black text-slate-900">{{ $unit->name }}</h3>
                            <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded-md bg-slate-100 text-slate-700 border border-slate-200 uppercase">
                                {{ $unit->code }}
                            </span>
                            @if($unit->is_active)
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                            </span>
                            @else
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200">
                                Nonaktif
                            </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                            {{ $unit->description ?: 'Tidak ada deskripsi tambahan.' }}
                        </p>
                        <div class="flex items-center gap-3 mt-2 text-[11px] text-slate-400">
                            <span class="inline-flex items-center gap-1 text-slate-600 font-medium">
                                <i data-lucide="{{ $unit->data_source === 'database' ? 'database' : 'file-spreadsheet' }}" class="w-3.5 h-3.5 text-slate-400"></i>
                                Sumber Data: <strong class="text-slate-800 uppercase">{{ $unit->data_source }}</strong>
                            </span>
                            <span>•</span>
                            <span class="text-slate-600 font-medium">
                                Cabang / Entitas: <strong class="text-slate-800">{{ $unit->entities->count() }}</strong>
                            </span>
                            <span>•</span>
                            <span class="text-slate-600 font-medium">
                                Data Finansial: <strong class="text-slate-800 font-mono">{{ number_format($unit->records_count, 0, ',', '.') }} rows</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- UNIT ACTION BUTTONS -->
                <div class="flex items-center gap-2 shrink-0 flex-wrap">
                    <a href="{{ route('finance.master.units.template', $unit->code) }}" 
                       class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                       title="Unduh Template Excel untuk import data unit ini">
                        <i data-lucide="download" class="w-3.5 h-3.5 text-emerald-600"></i>
                        Unduh Template Excel
                    </a>

                    <button type="button" 
                        onclick="openEditUnitModal({{ $unit->id }}, '{{ addslashes($unit->name) }}', '{{ addslashes($unit->description ?? '') }}', '{{ $unit->color }}', '{{ $unit->icon }}', '{{ $unit->data_source }}', {{ $unit->is_active ? 1 : 0 }})"
                        class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                        <i data-lucide="pencil" class="w-3.5 h-3.5 text-slate-500"></i>
                        Edit
                    </button>

                    @if($unit->records_count == 0)
                    <form action="{{ route('finance.master.units.destroy', $unit->id) }}" method="POST" class="inline"
                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus unit usaha {{ $unit->name }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer" title="Hapus Unit Usaha">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <!-- CABANG / ENTITAS SECTION -->
            <div class="p-5 bg-[#fafbfa]">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <i data-lucide="network" class="w-3.5 h-3.5 text-slate-500"></i>
                        Daftar Cabang / Entitas Terdaftar ({{ $unit->entities->count() }})
                    </h4>
                    <button type="button" onclick="openAddEntityModal({{ $unit->id }}, '{{ addslashes($unit->name) }}')"
                        class="px-2.5 py-1 rounded-md bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-[11px] font-bold transition flex items-center gap-1 shadow-2xs cursor-pointer">
                        <i data-lucide="plus" class="w-3 h-3 text-emerald-600"></i>
                        Tambah Cabang
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($unit->entities as $entity)
                    <div class="bg-white p-3 rounded-xl border border-[#edf0ee] shadow-2xs flex items-center justify-between">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $entity->code }}
                                </span>
                                <h5 class="text-xs font-bold text-slate-800">{{ $entity->name }}</h5>
                            </div>
                            <p class="text-[11px] text-slate-400 flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                {{ $entity->location ?: 'Pusat / Operasional' }}
                            </p>
                        </div>

                        @if($unit->entities->count() > 1)
                        <form action="{{ route('finance.master.entities.destroy', $entity->id) }}" method="POST"
                            onsubmit="return confirm('Hapus cabang {{ $entity->name }} ({{ $entity->code }})?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-300 hover:text-rose-600 rounded transition cursor-pointer" title="Hapus Cabang">
                                <i data-lucide="trash" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                    @empty
                    <div class="col-span-full py-4 text-center text-xs text-slate-400">
                        Belum ada cabang terdaftar.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endforeach
    </div>

</div>

<!-- MODAL TAMBAH UNIT USAHA BARU -->
<div id="modalAddUnit" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-[#ebf8c8] text-[#3f6212] flex items-center justify-center">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Tambah Unit Usaha Baru</h3>
                    <p class="text-[11px] text-slate-400">Daftarkan bisnis baru ke dalam portofolio holding</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modalAddUnit').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form action="{{ route('finance.master.units.store') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Kode Unit Usaha <span class="text-rose-500">*</span></label>
                    <input type="text" name="code" required placeholder="misal: hotel, cafe, properti" 
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono font-semibold focus:outline-none focus:ring-2 focus:ring-[#10e58f]"
                        oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')">
                    <p class="text-[10px] text-slate-400">Huruf kecil & angka (contoh: <code class="text-slate-600">hotel</code>)</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Sumber Data Laporan <span class="text-rose-500">*</span></label>
                    <select name="data_source" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
                        <option value="excel">Excel Upload (Spreadsheet)</option>
                        <option value="database">Database Sync (Direct SQL)</option>
                        <option value="api">REST API</option>
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Nama Perusahaan / Unit Usaha <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required placeholder="misal: PT Mekar Hospitality Jaya" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Deskripsi Singkat</label>
                <textarea name="description" rows="2" placeholder="Bidang usaha, lingkup operasional, atau keterangan lainnya..." 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#10e58f]"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Warna Aksen Visual</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="color" value="#10e58f" class="w-8 h-8 rounded-lg cursor-pointer border-0 p-0">
                        <span class="text-xs text-slate-500 font-mono">Pilih warna badge</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Ikon Lucide</label>
                    <input type="text" name="icon" value="building-2" placeholder="building-2, store, hotel, truck..." 
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
                </div>
            </div>

            <div class="space-y-1 pt-1 border-t border-slate-100">
                <label class="text-xs font-bold text-slate-700">Cabang / Entitas Awal (Opsional)</label>
                <textarea name="initial_entities" rows="2" placeholder="KUNINGAN:Cabang Kuningan:Kuningan&#10;CIREBON:Cabang Cirebon:Cirebon" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono text-slate-600 focus:outline-none focus:ring-2 focus:ring-[#10e58f]"></textarea>
                <p class="text-[10px] text-slate-400">Format per baris: <code class="text-slate-600">KODE_CABANG:Nama Cabang:Kota Lokasi</code> (bisa ditambahkan nanti).</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalAddUnit').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                    class="px-4 py-2 rounded-xl bg-[#10e58f] hover:bg-[#0fd684] text-slate-950 font-bold text-xs transition shadow-2xs cursor-pointer">
                    Simpan Unit Usaha
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT UNIT USAHA -->
<div id="modalEditUnit" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Edit Unit Usaha</h3>
                    <p class="text-[11px] text-slate-400">Perbarui data konfigurasi unit usaha</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modalEditUnit').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formEditUnit" method="POST" class="p-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Nama Perusahaan / Unit Usaha <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="editUnitName" required 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Deskripsi Singkat</label>
                <textarea name="description" id="editUnitDesc" rows="2" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#10e58f]"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Sumber Data</label>
                    <select name="data_source" id="editUnitDataSource" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
                        <option value="excel">Excel Upload</option>
                        <option value="database">Database Sync</option>
                        <option value="api">REST API</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Warna Aksen</label>
                    <input type="color" name="color" id="editUnitColor" class="w-8 h-8 rounded-lg cursor-pointer border-0 p-0">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Ikon Lucide</label>
                <input type="text" name="icon" id="editUnitIcon" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="editUnitActive" value="1" class="w-4 h-4 rounded text-[#10e58f] focus:ring-[#10e58f] cursor-pointer">
                <label for="editUnitActive" class="text-xs font-bold text-slate-700 cursor-pointer select-none">
                    Status Aktif (Ditampilkan dalam konsolidasi holding)
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalEditUnit').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                    class="px-4 py-2 rounded-xl bg-[#10e58f] hover:bg-[#0fd684] text-slate-950 font-bold text-xs transition shadow-2xs cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH CABANG / ENTITAS -->
<div id="modalAddEntity" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-[#ebf8c8] text-[#3f6212] flex items-center justify-center">
                    <i data-lucide="network" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Tambah Cabang / Entitas</h3>
                    <p class="text-[11px] text-slate-400" id="entityModalUnitTitle">Untuk Unit Usaha</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modalAddEntity').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formAddEntity" method="POST" class="p-5 space-y-4">
            @csrf

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Kode Cabang / Entitas <span class="text-rose-500">*</span></label>
                <input type="text" name="code" required placeholder="misal: KNG, CRB, DEPO_01" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono font-bold uppercase focus:outline-none focus:ring-2 focus:ring-[#10e58f]"
                    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')">
                <p class="text-[10px] text-slate-400">Kode unik cabang (contoh: <code class="text-slate-600">CABANG_KNG</code>)</p>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Nama Lengkap Cabang <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required placeholder="misal: Depo Cabang Kuningan" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Kota / Lokasi</label>
                <input type="text" name="location" placeholder="misal: Kuningan / Jawa Barat" 
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#10e58f]">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('modalAddEntity').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                    class="px-4 py-2 rounded-xl bg-[#10e58f] hover:bg-[#0fd684] text-slate-950 font-bold text-xs transition shadow-2xs cursor-pointer">
                    Simpan Cabang
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditUnitModal(id, name, desc, color, icon, dataSource, isActive) {
        const form = document.getElementById('formEditUnit');
        form.action = `/master/units/${id}`;
        document.getElementById('editUnitName').value = name;
        document.getElementById('editUnitDesc').value = desc;
        document.getElementById('editUnitColor').value = color || '#10e58f';
        document.getElementById('editUnitIcon').value = icon || 'building-2';
        document.getElementById('editUnitDataSource').value = dataSource || 'excel';
        document.getElementById('editUnitActive').checked = (isActive === 1);
        document.getElementById('modalEditUnit').classList.remove('hidden');
    }

    function openAddEntityModal(unitId, unitName) {
        const form = document.getElementById('formAddEntity');
        form.action = `/master/units/${unitId}/entities`;
        document.getElementById('entityModalUnitTitle').innerText = 'Untuk Unit: ' + unitName;
        document.getElementById('modalAddEntity').classList.remove('hidden');
    }
</script>
@endsection
