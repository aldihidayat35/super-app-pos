<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $documents = [];
        $inputDocuments = is_array($this->input('documents')) ? $this->input('documents') : [];
        $fileDocuments = is_array($this->file('documents')) ? $this->file('documents') : [];

        $indexes = array_unique(array_merge(array_keys($inputDocuments), array_keys($fileDocuments)));

        foreach ($indexes as $index) {
            $document = $inputDocuments[$index] ?? [];
            $document = is_array($document) ? $document : [];
            $hasFile = data_get($fileDocuments, "{$index}.file") !== null;
            $hasDetails = collect($document)
                ->except(['file', 'type'])
                ->contains(fn (mixed $value): bool => filled($value));

            if ($hasFile || $hasDetails) {
                $documents[$index] = $document;
            }
        }

        $this->merge(['documents' => $documents]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'price_category' => ['required', 'string', 'max:60'],
            'minimum_order' => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'verification_status' => ['required', Rule::enum(CustomerStatus::class)],
            'account_status' => ['required', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'documents' => ['array'],
            'documents.*.type' => ['required', 'string', 'max:60'],
            'documents.*.name' => ['nullable', 'string', 'max:255'],
            'documents.*.document_number' => ['nullable', 'string', 'max:120'],
            'documents.*.issued_at' => ['nullable', 'date'],
            'documents.*.expires_at' => ['nullable', 'date', 'after_or_equal:documents.*.issued_at'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'documents.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
