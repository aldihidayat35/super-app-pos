<?php

namespace App\Services\Organization;

use App\Enums\CustomerType;
use App\Models\DocumentSequence;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /** @var array<string, string> */
    public const DEFAULT_PREFIXES = [
        'purchase_request' => 'PR',
        'restock_request' => 'RST',
        'po' => 'PO',
        'receipt' => 'RCV',
        'transfer' => 'TRF',
        'opname' => 'OPN',
        'sale' => 'SAL',
        'order' => 'ORD',
        'invoice' => 'INV',
        'payment' => 'PAY',
        'shipment' => 'SHP',
        'complaint' => 'CMP',
        'receivable' => 'AR',
        'credit_note' => 'CN',
        'return' => 'RET',
        'loss' => 'LOS',
    ];

    public function next(string $documentType, ?WorkLocation $location = null, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $locationType = $location?->type;
        $locationId = $location?->id;
        $scopeKey = $this->scopeKey($location);

        return DB::transaction(function () use ($documentType, $location, $locationType, $locationId, $scopeKey, $year): string {
            DocumentSequence::query()->insertOrIgnore([
                'document_type' => $documentType,
                'location_type' => $locationType,
                'location_id' => $locationId,
                'scope_key' => $scopeKey,
                'year' => $year,
                'prefix' => self::DEFAULT_PREFIXES[$documentType] ?? strtoupper($documentType),
                'next_number' => 1,
                'padding' => 5,
                'reset_yearly' => true,
                'format' => '{prefix}/{location}/{year}/{sequence}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $this->format($sequence, $location, $sequence->next_number);
            $sequence->increment('next_number');

            return $number;
        });
    }

    public function nextCustomerCode(CustomerType|string $type, ?Carbon $date = null): string
    {
        $type = $type instanceof CustomerType ? $type : CustomerType::from((string) $type);
        $date ??= now();
        $prefix = $this->customerPrefix($type);
        $datePart = $date->format('Ymd');
        $year = (int) $date->format('Y');
        $scopeKey = "customer:{$prefix}:{$datePart}";

        return DB::transaction(function () use ($prefix, $datePart, $year, $scopeKey): string {
            DocumentSequence::query()->insertOrIgnore([
                'document_type' => 'customer_code',
                'location_type' => null,
                'location_id' => null,
                'scope_key' => $scopeKey,
                'year' => $year,
                'prefix' => $prefix,
                'next_number' => 1,
                'padding' => 4,
                'reset_yearly' => false,
                'format' => '{prefix}-{date}-{sequence}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('document_type', 'customer_code')
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $number = strtr($sequence->format, [
                '{prefix}' => $sequence->prefix,
                '{date}' => $datePart,
                '{sequence}' => str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT),
            ]);
            $sequence->increment('next_number');

            return $number;
        });
    }

    public function preview(DocumentSequence $sequence, ?WorkLocation $location = null): string
    {
        return $this->format($sequence, $location, $sequence->next_number);
    }

    private function format(DocumentSequence $sequence, ?WorkLocation $location, int $number): string
    {
        $sequenceNumber = str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);

        return strtr($sequence->format, [
            '{prefix}' => $sequence->prefix,
            '{location}' => $location->code ?? 'GLOBAL',
            '{year}' => (string) $sequence->year,
            '{month}' => now()->format('m'),
            '{sequence}' => $sequenceNumber,
        ]);
    }

    private function scopeKey(?WorkLocation $location): string
    {
        return $location ? "{$location->type}:{$location->id}" : 'global';
    }

    private function customerPrefix(CustomerType $type): string
    {
        return match ($type) {
            CustomerType::GENERAL => 'UM',
            CustomerType::RETAIL_CREDIT => 'RP-T',
            CustomerType::B2B => 'B2B',
        };
    }
}
