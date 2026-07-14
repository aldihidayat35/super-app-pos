<?php

namespace App\Http\Requests;

use App\Models\B2bComplaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreB2bComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', B2bComplaint::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'b2b_order_id' => ['nullable', 'exists:b2b_orders,id'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'b2b_order_item_id' => ['nullable', 'exists:b2b_order_items,id'],
            'type' => ['required', Rule::in(['kurang', 'pecah', 'salah_barang', 'lainnya'])],
            'requested_solution' => ['nullable', Rule::in(['kirim_pengganti', 'refund', 'credit_note', 'diskusi'])],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,pdf', 'max:8192'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
