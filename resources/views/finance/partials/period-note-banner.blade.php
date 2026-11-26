@php
    $curReportType = $reportType ?? 'pnl';
    $curBuCode = $selectedBu ?: 'all';
    $status = $periodNote?->closing_status; // null jika belum diset user
    $hasNote = !empty($periodNote?->notes);
    $canEditNote = $canEditNote ?? (auth()->user()?->canEditPeriodNotes() ?? false);
@endphp

<div class="rounded-2xl border transition-all duration-200 overflow-hidden shadow-xs
    @if($status === 'closed') border-[#c2e7cb] bg-[#edf5ee]/70
    @elseif($status === 'unclosed' || $status === 'draft') border-amber-300 bg-amber-50/60
    @else border-[#e9e5d6] bg-white @endif"
    id="periodNoteContainer">

    <!-- DISPLAY MODE (VIEW) -->
    <div id="periodNoteViewBox" class="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5 flex-1 min-w-0">
            <!-- STATUS ICON -->
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs
                @if($status === 'closed') bg-emerald-100 text-emerald-700 border border-emerald-300
                @elseif($status === 'unclosed' || $status === 'draft') bg-amber-100 text-amber-800 border border-amber-300
                @else bg-slate-100 text-slate-500 border border-slate-200 @endif">
                @if($status === 'closed')
                    <i data-lucide="check-check" class="w-5 h-5 text-emerald-600"></i>
                @elseif($status === 'unclosed' || $status === 'draft')
                    <i data-lucide="alert-circle" class="w-5 h-5 text-amber-600"></i>
                @else
                    <i data-lucide="minus" class="w-5 h-5 text-slate-400"></i>
                @endif
            </div>

            <!-- STATUS INFO & USER NOTE -->
            <div class="space-y-1 min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider
                        @if($status === 'closed') text-emerald-800
                        @elseif($status === 'unclosed' || $status === 'draft') text-amber-900
                        @else text-slate-600 @endif">
                        Status Closing:
                    </span>

                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold shadow-2xs
                        @if($status === 'closed') bg-emerald-100 text-emerald-800 border border-emerald-300
                        @elseif($status === 'unclosed' || $status === 'draft') bg-amber-100 text-amber-900 border border-amber-300
                        @else bg-slate-100 text-slate-600 border border-slate-200 @endif">
                        @if($status === 'closed')
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                            Sudah Selesai
                        @elseif($status === 'unclosed' || $status === 'draft')
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                            Belum Closing
                        @else
                            -
                        @endif
                    </span>

                    <span class="text-[11px] font-mono text-slate-600 bg-black/5 px-2 py-0.5 rounded">
                        {{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('F Y') }}
                        @if($selectedBu) • {{ strtoupper($selectedBu) }} @else • Konsolidasi Holding @endif
                    </span>
                </div>

                <!-- NOTE CONTENT -->
                @if($hasNote)
                <div class="pt-0.5">
                    <p class="text-xs sm:text-sm font-semibold text-slate-800 whitespace-pre-line leading-relaxed bg-white/80 p-2.5 rounded-xl border border-black/5 shadow-2xs">
                        "{{ $periodNote->notes }}"
                    </p>
                </div>
                @else
                <p class="text-xs text-slate-400 italic">
                    Belum ada catatan kendala / status closing untuk periode ini.
                </p>
                @endif

                <!-- META INFO -->
                @if($periodNote?->user_name)
                <p class="text-[11px] text-slate-600 flex items-center gap-1 pt-0.5">
                    <i data-lucide="user" class="w-3 h-3 text-slate-500"></i>
                    <span>Diperbarui oleh: <strong>{{ $periodNote->user_name }}</strong></span>
                    <span>•</span>
                    <i data-lucide="clock" class="w-3 h-3 text-slate-500"></i>
                    <span>{{ $periodNote->updated_at?->translatedFormat('d M Y, H:i') }}</span>
                </p>
                @endif
            </div>
        </div>

        <!-- ACTION BUTTON -->
        <div class="shrink-0 flex items-center gap-2 self-end md:self-center">
            @if($canEditNote)
            <button type="button" onclick="togglePeriodNoteForm()"
                class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs hover:shadow-xs cursor-pointer">
                <i data-lucide="edit-3" class="w-3.5 h-3.5 text-[#ca8a04]"></i>
                <span>{{ $hasNote || $periodNote ? 'Ubah Catatan & Status' : '+ Isi Catatan Closing' }}</span>
            </button>
            @endif
        </div>
    </div>

    <!-- EDIT FORM (COLLAPSIBLE) -->
    @if($canEditNote)
    <div id="periodNoteFormBox" class="hidden border-t border-[#e9e5d6] bg-white p-4 sm:p-5">
        <form action="{{ route('finance.period-note.save') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
            <input type="hidden" name="business_unit_code" value="{{ $curBuCode }}">
            <input type="hidden" name="report_type" value="{{ $curReportType }}">

            <div class="border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="file-signature" class="w-4 h-4 text-[#ca8a04]"></i>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                        Input Status & Catatan Penutupan Buku (Closing)
                    </h4>
                </div>
            </div>

            <!-- RADIO PILIHAN STATUS (HANYA 2 OPSI) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Status Penutupan Laporan:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="relative flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-[#fbfdfa] hover:bg-white cursor-pointer transition has-checked:border-amber-400 has-checked:bg-amber-50/50">
                        <input type="radio" name="closing_status" value="unclosed" class="text-amber-600 focus:ring-amber-500 w-4 h-4 cursor-pointer"
                            {{ in_array($status, ['unclosed', 'draft', null]) ? 'checked' : '' }}>
                        <div>
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Belum Closing
                            </span>
                            <p class="text-[11px] text-slate-400 mt-0.5">Laporan masih berjalan / ada pending data & kendala</p>
                        </div>
                    </label>

                    <label class="relative flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-[#fbfdfa] hover:bg-white cursor-pointer transition has-checked:border-emerald-400 has-checked:bg-emerald-50/50">
                        <input type="radio" name="closing_status" value="closed" class="text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer"
                            {{ $status === 'closed' ? 'checked' : '' }}>
                        <div>
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Sudah Selesai
                            </span>
                            <p class="text-[11px] text-slate-400 mt-0.5">Angka laporan sudah final dan penutupan buku selesai</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- TEXTAREA CATATAN -->
            <div>
                <label for="periodNotesTextarea" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Alasan Belum Selesai / Catatan Khusus Periode Ini:
                </label>
                <textarea name="notes" id="periodNotesTextarea" rows="3"
                    class="w-full text-xs font-medium text-slate-800 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-[#ca8a04] focus:ring-2 focus:ring-[#ca8a04]/20 transition"
                    placeholder="Contoh: Belum closing karena menunggu rekonsiliasi mutasi bank cabang CCOD Bandung, audit fisik opname gudang, atau penyelesaian selisih faktur...">{{ $periodNote?->notes ?? '' }}</textarea>
                <p class="text-[11px] text-slate-400 mt-1">
                    Catatan ini akan tampil jelas di bagian atas laporan agar tim manajemen, direksi, dan auditor langsung mengetahui status dan kendala laporan periode ini.
                </p>
            </div>

            <!-- BUTTONS -->
            <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-slate-100">
                <div>
                    @if($periodNote)
                    <button type="button" onclick="deletePeriodNoteRecord({{ $periodNote->id }})"
                        class="text-xs text-rose-600 hover:text-rose-700 font-bold flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                        title="Hapus catatan dan kembalikan status ke default (-)">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        <span>Hapus Catatan (Reset ke -)</span>
                    </button>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="togglePeriodNoteForm()"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl bg-[#ca8a04] hover:bg-[#b07803] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        <span>Simpan Catatan Periode</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
    function togglePeriodNoteForm() {
        const formBox = document.getElementById('periodNoteFormBox');
        if (!formBox) return;
        if (formBox.classList.contains('hidden')) {
            formBox.classList.remove('hidden');
            const textarea = document.getElementById('periodNotesTextarea');
            if (textarea) textarea.focus();
        } else {
            formBox.classList.add('hidden');
        }
    }

    function deletePeriodNoteRecord(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus catatan periode ini dan mengembalikan status ke default (-)?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/period-note/' + id;

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
