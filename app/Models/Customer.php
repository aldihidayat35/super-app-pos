<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['type', 'code', 'business_name', 'owner_name', 'pic_name', 'whatsapp_number', 'email', 'business_address', 'city', 'price_category', 'minimum_order', 'payment_term_days', 'credit_limit', 'receivable_balance', 'verification_status', 'account_status', 'status_reason', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'minimum_order' => 'decimal:2',
            'payment_term_days' => 'integer',
            'credit_limit' => 'decimal:2',
            'receivable_balance' => 'decimal:2',
            'verification_status' => CustomerStatus::class,
            'account_status' => CustomerStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_users')
            ->withPivot('role', 'is_active', 'blocked_at', 'blocked_reason')
            ->withTimestamps();
    }

    /** @return HasMany<CustomerDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /** @return HasMany<CustomerPriceOverride, $this> */
    public function priceOverrides(): HasMany
    {
        return $this->hasMany(CustomerPriceOverride::class);
    }

    /** @return HasMany<B2bCart, $this> */
    public function b2bCarts(): HasMany
    {
        return $this->hasMany(B2bCart::class);
    }

    /** @return HasMany<B2bOrder, $this> */
    public function b2bOrders(): HasMany
    {
        return $this->hasMany(B2bOrder::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasOne<CreditLimit, $this> */
    public function creditLimit(): HasOne
    {
        return $this->hasOne(CreditLimit::class);
    }
}
