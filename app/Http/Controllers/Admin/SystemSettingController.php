<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('view', SystemSetting::class);

        return view('admin.settings.general', [
            'settings' => $this->generalSettings(),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')?->store('logos', 'public');
        }

        unset($data['logo']);

        DB::transaction(function () use ($request, $data): void {
            foreach ($data as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => 'general'],
                );
            }

            activity()->causedBy($request->user())->log('admin.settings.general_updated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Pengaturan umum berhasil disimpan.',
        ]);
    }

    /** @return array<string, mixed> */
    private function generalSettings(): array
    {
        $defaults = [
            'company_name' => 'GudangToko',
            'company_address' => null,
            'company_phone' => null,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 2,
            'default_minimum_margin_percent' => '10',
            'overpricing_tolerance_percent' => '20',
            'invoice_template' => 'default',
            'receipt_template' => 'default',
            'logo_path' => null,
        ];

        $stored = SystemSetting::query()
            ->where('group', 'general')
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value])
            ->all();

        return array_merge($defaults, $stored);
    }
}
