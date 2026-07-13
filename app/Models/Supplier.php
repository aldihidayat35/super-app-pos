<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'contact_name', 'phone_number', 'whatsapp_number', 'email', 'address', 'city', 'tax_number', 'bank_name', 'bank_account_name', 'bank_account_number', 'payment_term_days', 'last_price', 'performance_score', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['payment_term_days' => 'integer', 'last_price' => 'decimal:2', 'performance_score' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @return HasMany<SupplierContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /** @return HasMany<SupplierProduct, $this> */
    public function productsSupplied(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /** @return HasMany<SupplierDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
