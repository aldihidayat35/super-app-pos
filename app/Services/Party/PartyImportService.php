<?php

namespace App\Services\Party;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class PartyImportService
{
    /** @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function preview(string $type, UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $errors = [];

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, $type === 'suppliers' ? $this->supplierRules() : $this->customerRules());
            if ($validator->fails()) {
                $errors[$index + 2] = $validator->errors()->all();
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function commit(string $type, array $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($type, $rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $model = $type === 'suppliers' ? $this->upsertSupplier($row) : $this->upsertCustomer($row);
                $model->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /** @param array<string, mixed> $row */
    private function upsertSupplier(array $row): Supplier
    {
        return Supplier::query()->updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'contact_name' => $row['contact_name'] ?? null,
                'whatsapp_number' => $row['whatsapp_number'] ?? null,
                'email' => $row['email'] ?? null,
                'city' => $row['city'] ?? null,
                'address' => $row['address'] ?? null,
                'payment_term_days' => $row['payment_term_days'] ?: 0,
                'is_active' => true,
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private function upsertCustomer(array $row): Customer
    {
        return Customer::query()->updateOrCreate(
            ['code' => $row['code']],
            [
                'type' => $row['type'] ?: CustomerType::GENERAL->value,
                'business_name' => $row['business_name'],
                'owner_name' => $row['owner_name'] ?? null,
                'pic_name' => $row['pic_name'] ?? null,
                'whatsapp_number' => $row['whatsapp_number'] ?? null,
                'email' => $row['email'] ?? null,
                'city' => $row['city'] ?? null,
                'business_address' => $row['business_address'] ?? null,
                'price_category' => $row['price_category'] ?: 'retail',
                'minimum_order' => $row['minimum_order'] ?: 0,
                'payment_term_days' => $row['payment_term_days'] ?: 0,
                'credit_limit' => $row['credit_limit'] ?: 0,
                'verification_status' => $row['verification_status'] ?: CustomerStatus::PENDING_VERIFICATION->value,
                'account_status' => $row['account_status'] ?: CustomerStatus::PENDING_VERIFICATION->value,
                'is_active' => true,
            ],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        if (in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            $handle = fopen($file->getRealPath(), 'r');
            $headers = array_map(fn (mixed $header): string => trim((string) $header), fgetcsv($handle) ?: []);
            $rows = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (collect($row)->filter(fn (mixed $value): bool => filled($value))->isEmpty()) {
                    continue;
                }
                $rows[] = collect($headers)->mapWithKeys(fn (string $header, int $index): array => [$header => $row[$index] ?? null])->all();
            }

            fclose($handle);

            return $rows;
        }

        /** @var array<int, array<int, array<int|string, mixed>>> $sheets */
        $sheets = Excel::toArray(new class implements ToArray
        {
            /** @param array<int, array<int|string, mixed>> $array */
            public function array(array $array): void
            {
                //
            }
        }, $file);
        $sheet = $sheets[0] ?? [];
        $headers = collect(array_shift($sheet) ?: [])->map(fn (mixed $header): string => trim((string) $header))->all();

        return collect($sheet)
            ->filter(fn (array $row): bool => collect($row)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty())
            ->map(fn (array $row): array => collect($headers)->mapWithKeys(fn (string $header, int $index): array => [$header => $row[$index] ?? null])->all())
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function supplierRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'whatsapp_number' => ['nullable', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /** @return array<string, mixed> */
    private function customerRules(): array
    {
        return [
            'type' => ['nullable', 'in:general,retail_credit,b2b'],
            'code' => ['required', 'string', 'max:60'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'whatsapp_number' => ['nullable', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'price_category' => ['nullable', 'string', 'max:60'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'verification_status' => ['nullable', 'in:pending_verification,active,frozen,blacklisted,inactive'],
            'account_status' => ['nullable', 'in:pending_verification,active,frozen,blacklisted,inactive'],
        ];
    }
}
