<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrinterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.settings.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'printer_thermal_width' => ['required', Rule::in(['80mm', '58mm'])],
            'printer_dotmatrix_paper' => ['required', Rule::in(['half_letter', 'folio', 'a4'])],
            'printer_auto_print_pos' => ['nullable', 'boolean'],
            'print_show_logo' => ['nullable', 'boolean'],
            'print_show_store_header' => ['nullable', 'boolean'],
            'print_show_cashier_name' => ['nullable', 'boolean'],
            'print_show_customer_name' => ['nullable', 'boolean'],
            'print_show_item_barcode' => ['nullable', 'boolean'],
            'print_show_payment_details' => ['nullable', 'boolean'],
            'print_show_signatures' => ['nullable', 'boolean'],
            'print_receipt_footer_note' => ['nullable', 'string', 'max:500'],
            'print_invoice_footer_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'printer_auto_print_pos' => $this->boolean('printer_auto_print_pos'),
            'print_show_logo' => $this->boolean('print_show_logo'),
            'print_show_store_header' => $this->boolean('print_show_store_header'),
            'print_show_cashier_name' => $this->boolean('print_show_cashier_name'),
            'print_show_customer_name' => $this->boolean('print_show_customer_name'),
            'print_show_item_barcode' => $this->boolean('print_show_item_barcode'),
            'print_show_payment_details' => $this->boolean('print_show_payment_details'),
            'print_show_signatures' => $this->boolean('print_show_signatures'),
        ]);
    }
}
