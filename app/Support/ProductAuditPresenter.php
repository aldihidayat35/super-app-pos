<?php

namespace App\Support;

use App\Enums\ProductStatus;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

final class ProductAuditPresenter
{
    /** @return array{label: string, badge: string, icon: string} */
    public static function action(Activity $activity): array
    {
        return match ($activity->description) {
            'product.created' => ['label' => 'Produk Dibuat', 'badge' => 'success', 'icon' => 'ki-plus-circle'],
            'product.updated' => ['label' => 'Produk Diperbarui', 'badge' => 'primary', 'icon' => 'ki-pencil'],
            'product.deactivated' => ['label' => 'Produk Dinonaktifkan', 'badge' => 'warning', 'icon' => 'ki-toggle-off-circle'],
            'product.photo.added' => ['label' => 'Foto Ditambahkan', 'badge' => 'success', 'icon' => 'ki-picture'],
            'product.photo.deleted' => ['label' => 'Foto Dihapus', 'badge' => 'danger', 'icon' => 'ki-trash'],
            'product.photo.primary_changed' => ['label' => 'Foto Utama Diubah', 'badge' => 'info', 'icon' => 'ki-star'],
            default => ['label' => 'Aktivitas Produk', 'badge' => 'secondary', 'icon' => 'ki-information-5'],
        };
    }

    /** @param array<string, array<int, string>> $relations
     * @return list<array{field: string, label: string, old: string, new: string, image: bool}>
     */
    public static function changes(Activity $activity, array $relations, bool $canViewSensitive): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $old = $properties['old'] ?? $properties['before'] ?? [];
        $new = $properties['attributes'] ?? $properties['after'] ?? [];
        $keys = array_unique([...array_keys(is_array($old) ? $old : []), ...array_keys(is_array($new) ? $new : [])]);
        $sensitive = ['cost_price', 'minimum_price'];
        $result = [];

        foreach ($keys as $key) {
            if (! $canViewSensitive && in_array($key, $sensitive, true)) {
                continue;
            }
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }
            $result[] = [
                'field' => $key,
                'label' => self::label($key),
                'old' => self::format($key, $oldValue, $relations),
                'new' => self::format($key, $newValue, $relations),
                'image' => in_array($key, ['primary_image', 'product_image', 'main_image_path'], true),
            ];
        }

        return $result;
    }

    private static function label(string $field): string
    {
        return [
            'name' => 'Nama Produk', 'sku' => 'SKU', 'category_id' => 'Kategori', 'subcategory_id' => 'Subkategori',
            'brand_id' => 'Merek', 'base_unit_id' => 'Satuan Dasar', 'default_warehouse_id' => 'Gudang Default',
            'status' => 'Status', 'cost_price' => 'HPP', 'minimum_price' => 'Harga Minimum', 'minimum_order' => 'Minimum Order',
            'minimum_stock' => 'Minimum Stok', 'safety_stock' => 'Safety Stock', 'description' => 'Deskripsi',
            'primary_image' => 'Foto Utama', 'product_image' => 'Foto Produk', 'main_image_path' => 'Foto Utama',
        ][$field] ?? str($field)->replace('_', ' ')->title()->toString();
    }

    /** @param array<string, array<int, string>> $relations */
    private static function format(string $field, mixed $value, array $relations): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (isset($relations[$field][(int) $value])) {
            return $relations[$field][(int) $value];
        }
        if (in_array($field, ['cost_price', 'minimum_price'], true)) {
            return CurrencyFormatter::rupiah((string) $value);
        }
        if ($field === 'status') {
            return ProductStatus::tryFrom((string) $value)?->label() ?? (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        if (str_ends_with($field, '_at')) {
            try {
                return Carbon::parse((string) $value)->translatedFormat('d F Y, H.i');
            } catch (\Throwable) {
            }
        }

        return (string) $value;
    }
}
