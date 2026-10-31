<?php

namespace App\Http\Requests\Warehouse;

use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLocationTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('destination_work_location_id') && $this->filled('source_work_location_id')) {
            $this->merge(['destination_work_location_id' => $this->input('source_work_location_id')]);
        }

        if (! $this->filled('reason')) {
            $this->merge(['reason' => 'Pindah rak internal']);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required_without:items', 'nullable', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity' => ['required_without:items', 'nullable', 'numeric', 'gt:0'],
            'items' => ['required_without:product_id', 'nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'source_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'source_warehouse_location_id' => ['required', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'destination_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'destination_warehouse_location_id' => ['required', 'different:source_warehouse_location_id', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateWarehouseLocationBelongsToWorkLocation(
                validator: $validator,
                warehouseLocationField: 'source_warehouse_location_id',
                workLocationField: 'source_work_location_id',
                label: 'Zona/Rak/Bin Sumber',
            );

            $this->validateWarehouseLocationBelongsToWorkLocation(
                validator: $validator,
                warehouseLocationField: 'destination_warehouse_location_id',
                workLocationField: 'destination_work_location_id',
                label: 'Zona/Rak/Bin Tujuan',
            );
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_id' => 'produk',
            'source_work_location_id' => 'lokasi kerja sumber',
            'source_warehouse_location_id' => 'zona/rak/bin sumber',
            'destination_work_location_id' => 'lokasi kerja tujuan',
            'destination_warehouse_location_id' => 'zona/rak/bin tujuan',
            'quantity' => 'qty',
            'reason' => 'alasan transfer',
        ];
    }

    private function validateWarehouseLocationBelongsToWorkLocation(
        Validator $validator,
        string $warehouseLocationField,
        string $workLocationField,
        string $label,
    ): void {
        $warehouseLocationId = $this->input($warehouseLocationField);
        $workLocationId = (int) $this->input($workLocationField);

        if (blank($warehouseLocationId) || $workLocationId <= 0) {
            return;
        }

        $warehouseLocation = WarehouseLocation::query()
            ->with(['warehouse:id,work_location_id', 'branch:id,work_location_id'])
            ->find($warehouseLocationId);

        if ($warehouseLocation === null) {
            return;
        }

        if ((int) $warehouseLocation->workLocationId() !== $workLocationId) {
            $validator->errors()->add($warehouseLocationField, "{$label} harus berada di dalam lokasi kerja yang dipilih.");
        }
    }
}
