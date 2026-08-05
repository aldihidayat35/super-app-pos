<?php

namespace App\Support;

final class PriceWarningPresenter
{
    /** @return array{label: string, description: string, badge: string, icon: string} */
    public static function get(string $code): array
    {
        return match ($code) {
            'below_minimum' => [
                'label' => 'Di Bawah Harga Minimum',
                'description' => 'Harga rekomendasi lebih rendah daripada batas harga minimum dan memerlukan persetujuan.',
                'badge' => 'danger',
                'icon' => 'ki-arrow-down',
            ],
            'overpricing' => [
                'label' => 'Di Atas Batas Maksimum',
                'description' => 'Harga rekomendasi melebihi toleransi harga maksimum dan memerlukan persetujuan.',
                'badge' => 'warning',
                'icon' => 'ki-arrow-up',
            ],
            'discount_exceeds_cap' => [
                'label' => 'Diskon Melebihi Batas',
                'description' => 'Diskon melampaui batas yang diizinkan oleh aturan harga aktif.',
                'badge' => 'warning',
                'icon' => 'ki-percentage',
            ],
            default => [
                'label' => 'Harga Sesuai Aturan',
                'description' => 'Harga berada dalam batas minimum dan maksimum yang berlaku.',
                'badge' => 'success',
                'icon' => 'ki-check-circle',
            ],
        };
    }
}
