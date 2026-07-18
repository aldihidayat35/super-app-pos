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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'source_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'source_warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'destination_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'destination_warehouse_location_id' => ['nullable', 'different:source_warehouse_location_id', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
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
            ->with('warehouse:id,work_location_id')
            ->find($warehouseLocationId);

        if ($warehouseLocation === null) {
            return;
        }

        if ((int) $warehouseLocation->warehouse?->work_location_id !== $workLocationId) {
            $validator->errors()->add($warehouseLocationField, "{$label} harus berada di dalam lokasi kerja yang dipilih.");
        }
    }
}
