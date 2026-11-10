<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Get company name from settings.
     */
    public static function getCompanyName(): string
    {
        $fallback = (string) config('app.name', 'GudangToko');

        try {
            if (! Schema::hasTable('system_settings')) {
                return $fallback;
            }

            return (string) (static::where('key', 'company_name')
                ->where('group', 'general')
                ->value('value') ?? $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Get company logo path from settings.
     */
    public static function getCompanyLogo(): ?string
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return null;
            }

            $path = static::where('key', 'logo_path')
                ->where('group', 'general')
                ->value('value');
        } catch (Throwable) {
            return null;
        }

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * Get all printer & receipt settings with defaults.
     *
     * @return array<string, mixed>
     */
    public static function getPrinterSettings(): array
    {
        $defaults = [
            'printer_thermal_width' => '80mm',
            'printer_dotmatrix_paper' => 'half_letter',
            'printer_auto_print_pos' => true,
            'print_show_logo' => true,
            'print_show_store_header' => true,
            'print_show_cashier_name' => true,
            'print_show_customer_name' => true,
            'print_show_item_barcode' => true,
            'print_show_payment_details' => true,
            'print_show_signatures' => true,
            'print_receipt_footer_note' => 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan tanpa bukti transaksi resmi. Terima kasih atas kunjungan Anda.',
            'print_invoice_footer_note' => 'Pembayaran sah jika telah diterbitkan kuitansi atau tanda terima resmi dari kasir GudangToko.',
        ];

        try {
            if (! Schema::hasTable('system_settings')) {
                return $defaults;
            }

            $stored = static::where('group', 'printer')
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value])
                ->all();

            return array_merge($defaults, $stored);
        } catch (Throwable) {
            return $defaults;
        }
    }
}
