<?php

namespace App\Http\Requests\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use App\Models\ReturnDocument;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = [];
        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['product_id'] ?? null) || filled($item['source_item_id'] ?? null) || filled($item['quantity_requested'] ?? null)) {
                $items[] = $item;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('returns.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workLocationIds = $this->user()?->permittedWorkLocationIds() ?? [];
        $workLocationId = (int) $this->input('work_location_id');
        $boundReturn = $this->route('return');
        $remainingEvidenceSlots = $boundReturn instanceof ReturnDocument
            ? max(0, 5 - $boundReturn->attachments()->count())
            : 5;

        return [
            'work_location_id' => ['required', Rule::exists('work_locations', 'id')->where(fn ($query) => $query->where('is_active', true)->whereIn('id', $workLocationIds))],
            'source_type' => ['required', 'in:supplier,b2b,pos,transfer,manual'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'reference_id' => [Rule::requiredIf(fn (): bool => $this->input('source_type') !== 'manual'), 'nullable', 'integer', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:80'],
            'requested_resolution' => ['required', Rule::in(array_keys(ReturnResolution::options()))],
            'return_date' => ['required', 'date'],
            'evidence_files' => ['nullable', 'array', 'max:'.$remainingEvidenceSlots],
            'evidence_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('warehouse_id', Warehouse::query()->select('id')->where('work_location_id', $workLocationId)))],
            'items.*.source_item_id' => [Rule::requiredIf(fn (): bool => $this->input('source_type') !== 'manual'), 'nullable', 'integer'],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', Rule::in(array_keys(ReturnCondition::options()))],
            'items.*.reason' => ['nullable', 'string', 'max:80'],
            'items.*.resolution' => ['nullable', Rule::in(array_keys(ReturnResolution::options()))],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'work_location_id' => 'lokasi kerja',
            'source_type' => 'sumber retur',
            'reference_id' => 'dokumen asal',
            'reason' => 'alasan retur',
            'requested_resolution' => 'solusi yang diminta',
            'return_date' => 'tanggal retur',
            'evidence_files' => 'lampiran bukti',
            'evidence_files.*' => 'file bukti',
            'items' => 'item retur',
            'items.*.product_id' => 'produk',
            'items.*.source_item_id' => 'item dokumen asal',
            'items.*.warehouse_location_id' => 'lokasi/rak/bin tujuan',
            'items.*.quantity_requested' => 'qty retur',
            'items.*.condition' => 'kondisi barang',
            'items.*.notes' => 'catatan item',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_id.required' => 'Pilih dokumen asal sesuai sumber retur.',
            'items.min' => 'Tambahkan minimal satu item yang akan diretur.',
            'items.*.source_item_id.required' => 'Pilih item yang berasal dari dokumen asal.',
            'items.*.quantity_requested.gt' => 'Qty retur harus lebih dari nol.',
            'evidence_files.max' => 'Maksimal 5 file bukti dapat diunggah.',
            'evidence_files.*.mimes' => 'Bukti harus berupa JPG, JPEG, PNG, atau PDF.',
            'evidence_files.*.max' => 'Ukuran setiap file bukti maksimal 4 MB.',
        ];
    }
}
