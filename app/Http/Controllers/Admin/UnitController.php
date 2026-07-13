<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);
        $search = trim((string) $request->query('q'));

        $units = Unit::query()
            ->withCount('productUnits')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('symbol', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.units.index', compact('units', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Unit::class);

        return view('admin.units.create', ['unit' => new Unit(['is_active' => true, 'precision' => 0])]);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $unit = Unit::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
        activity()->causedBy($request->user())->performedOn($unit)->log('product.unit.created');

        return redirect()->route('admin.units.index')->with('notification', ['type' => 'success', 'message' => "Satuan {$unit->name} berhasil dibuat."]);
    }

    public function edit(Unit $unit): View
    {
        $this->authorize('update', $unit);

        return view('admin.units.edit', compact('unit'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($request, $unit): void {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            if ($unit->has_transactions && $unit->code !== $data['code']) {
                abort(422, 'Kode satuan yang sudah dipakai transaksi tidak boleh diubah.');
            }
            $unit->fill($data)->save();
            activity()->causedBy($request->user())->performedOn($unit)->log('product.unit.updated');
        });

        return redirect()->route('admin.units.index')->with('notification', ['type' => 'success', 'message' => 'Satuan berhasil diperbarui.']);
    }

    public function deactivate(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);
        $unit->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($unit)->log('product.unit.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Satuan berhasil dinonaktifkan.']);
    }
}
