<?php

namespace App\Http\Controllers;

use App\Models\GlobalBusinessUnit;
use App\Models\GlobalEntity;
use App\Models\GlobalFinancialRecord;
use App\Services\GlobalFinance\ExcelIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterBusinessUnitController extends Controller
{
    /**
     * Tampilkan halaman Master Data Unit Usaha & Entitas Cabang.
     */
    public function index(): View
    {
        $units = GlobalBusinessUnit::with(['entities' => function ($q) {
            $q->orderBy('sort_order');
        }])
            ->withCount('records')
            ->orderBy('sort_order')
            ->get();

        $stats = [
            'total_units' => $units->count(),
            'active_units' => $units->where('is_active', true)->count(),
            'total_entities' => GlobalEntity::count(),
            'excel_units' => $units->where('data_source', 'excel')->count(),
            'db_units' => $units->where('data_source', 'database')->count(),
        ];

        return view('finance.master.units', compact('units', 'stats'));
    }

    /**
     * Tambah Unit Usaha Baru ke Holding.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|alpha_dash|max:50|unique:global_business_units,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'data_source' => 'required|in:excel,database,api',
            'initial_entities' => 'nullable|string',
        ]);

        $maxOrder = GlobalBusinessUnit::max('sort_order') ?? 0;
        $unit = GlobalBusinessUnit::create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?: '#d97706',
            'icon' => $validated['icon'] ?: 'building-2',
            'data_source' => $validated['data_source'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        // Daftarkan cabang / entitas awal jika diisi
        if (! empty($validated['initial_entities'])) {
            $lines = preg_split('/[\r\n,]+/', $validated['initial_entities']);
            $eOrder = 1;
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $parts = array_map('trim', explode(':', $line));
                $eCode = strtoupper(str_replace(' ', '_', $parts[0]));
                $eName = $parts[1] ?? ucwords(strtolower(str_replace('_', ' ', $eCode)));
                $eLoc = $parts[2] ?? null;

                GlobalEntity::create([
                    'business_unit_id' => $unit->id,
                    'code' => $eCode,
                    'name' => $eName,
                    'location' => $eLoc,
                    'sort_order' => $eOrder++,
                    'is_active' => true,
                ]);
            }
        } else {
            // Default: buat satu entitas pusat
            GlobalEntity::create([
                'business_unit_id' => $unit->id,
                'code' => strtoupper($unit->code).'_PUSAT',
                'name' => $unit->name.' (Pusat)',
                'location' => 'Pusat',
                'sort_order' => 1,
                'is_active' => true,
            ]);
        }

        return back()->with('success', "Unit Bisnis '{$unit->name}' ({$unit->code}) berhasil ditambahkan ke Master Data Holding.");
    }

    /**
     * Perbarui data Unit Usaha.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $unit = GlobalBusinessUnit::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'data_source' => 'required|in:excel,database,api',
            'is_active' => 'nullable|boolean',
        ]);

        $unit->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?: $unit->color,
            'icon' => $validated['icon'] ?: $unit->icon,
            'data_source' => $validated['data_source'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Data unit bisnis '{$unit->name}' berhasil diperbarui.");
    }

    /**
     * Hapus Unit Usaha (Hanya jika belum memiliki data transaksi).
     */
    public function destroy(int $id): RedirectResponse
    {
        $unit = GlobalBusinessUnit::findOrFail($id);
        $name = $unit->name;

        $recordsCount = GlobalFinancialRecord::where('business_unit_code', $unit->code)->count();
        if ($recordsCount > 0) {
            return back()->with('error', "Unit Bisnis '{$name}' tidak dapat dihapus karena memiliki {$recordsCount} baris data finansial di sistem holding. Silakan nonaktifkan unit jika tidak ingin ditampilkan.");
        }

        $unit->delete();

        return back()->with('success', "Unit Bisnis '{$name}' berhasil dihapus dari Master Data.");
    }

    /**
     * Tambah Cabang / Entitas ke Unit Usaha tertentu.
     */
    public function storeEntity(Request $request, int $unitId): RedirectResponse
    {
        $unit = GlobalBusinessUnit::findOrFail($unitId);

        $validated = $request->validate([
            'code' => 'required|string|alpha_dash|max:50|unique:global_entities,code',
            'name' => 'required|string|max:150',
            'location' => 'nullable|string|max:150',
        ]);

        $maxOrder = $unit->entities()->max('sort_order') ?? 0;
        $entity = GlobalEntity::create([
            'business_unit_id' => $unit->id,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('success', "Cabang '{$entity->name}' ({$entity->code}) berhasil ditambahkan ke unit {$unit->name}.");
    }

    /**
     * Hapus Cabang / Entitas.
     */
    public function destroyEntity(int $id): RedirectResponse
    {
        $entity = GlobalEntity::findOrFail($id);
        $name = $entity->name;

        $recordsCount = GlobalFinancialRecord::where('entity_code', $entity->code)->count();
        if ($recordsCount > 0) {
            return back()->with('error', "Cabang '{$name}' tidak dapat dihapus karena memiliki {$recordsCount} baris transaksi finansial tercatat.");
        }

        $entity->delete();

        return back()->with('success', "Cabang '{$name}' berhasil dihapus.");
    }

    /**
     * Unduh Template Excel Resmi Holding untuk Unit Usaha Tertentu.
     */
    public function downloadTemplate(string $code, ExcelIngestionService $service): StreamedResponse
    {
        $unit = GlobalBusinessUnit::where('code', $code)->firstOrFail();
        $spreadsheet = $service->generateTemplate($code);
        $filename = "Template_Keuangan_Holding_{$unit->code}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
