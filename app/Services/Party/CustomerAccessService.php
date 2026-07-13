<?php

namespace App\Services\Party;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerAccessService
{
    /** @param array<int, array<string, mixed>> $addresses */
    public function syncAddresses(Customer $customer, array $addresses, ?int $primaryIndex): void
    {
        DB::transaction(function () use ($customer, $addresses, $primaryIndex): void {
            $customer->addresses()->update(['is_primary' => false, 'primary_scope' => null]);

            foreach ($addresses as $index => $address) {
                $isPrimary = $index === ($primaryIndex ?? 0);
                $customer->addresses()->updateOrCreate(
                    ['id' => $address['id'] ?? null],
                    [
                        'label' => $address['label'],
                        'recipient_name' => $address['recipient_name'] ?? null,
                        'phone_number' => $address['phone_number'] ?? null,
                        'address' => $address['address'],
                        'city' => $address['city'] ?? null,
                        'postal_code' => $address['postal_code'] ?? null,
                        'directions' => $address['directions'] ?? null,
                        'is_primary' => $isPrimary,
                        'primary_scope' => $isPrimary ? 'primary' : null,
                    ],
                );
            }
        });
    }

    /** @param array<int, array<string, mixed>> $users */
    public function syncUsers(Customer $customer, array $users): void
    {
        DB::transaction(function () use ($customer, $users): void {
            foreach ($users as $row) {
                $user = filled($row['id'] ?? null)
                    ? User::query()->findOrFail($row['id'])
                    : User::query()->create([
                        'name' => $row['name'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'password' => Str::password(24),
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'email_verified_at' => now(),
                    ]);

                $role = $row['role'];
                $user->syncRoles([$role]);

                $customer->users()->syncWithoutDetaching([
                    $user->id => [
                        'role' => $role,
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'blocked_at' => ($row['is_active'] ?? true) ? null : now(),
                        'blocked_reason' => $row['blocked_reason'] ?? null,
                    ],
                ]);
            }
        });
    }
}
