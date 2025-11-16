<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    protected $fillable = [
        'name_en',
        'name_ar',
        'province_en',
        'province_ar',
        'mobile_number',
        'follow_up_person_en',
        'follow_up_person_ar',
        'address_en',
        'address_ar',
        'email',
        'tax_number',
        'credit_limit',
        'customer_type',
        'is_active',
        // New fields for business requirements
        'customer_location',
        'account_representative',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the orders for the customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the customer name based on current locale
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * Get the province based on current locale
     */
    public function getProvinceAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->province_ar : $this->province_en;
    }

    /**
     * Get the follow-up person based on current locale
     */
    public function getFollowUpPersonAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->follow_up_person_ar : $this->follow_up_person_en;
    }

    /**
     * Get the address based on current locale
     */
    public function getAddressAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->address_ar : $this->address_en;
    }

    /**
     * Get total orders value
     */
    public function getTotalOrdersValueAttribute(): float
    {
        return $this->orders()->sum('total_amount');
    }

    /**
     * Get total paid amount
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->invoices()->where('is_paid', true)->sum('total_amount');
    }

    /**
     * Get outstanding amount
     */
    public function getOutstandingAmountAttribute(): float
    {
        return $this->total_orders_value - $this->total_paid;
    }

    /**
     * Get customer location (sales employee field)
     */
    public function getCustomerLocationAttribute(): ?string
    {
        return $this->getAttributes()['customer_location'] ?? null;
    }

    /**
     * Get account representative (sales employee field)
     */
    public function getAccountRepresentativeAttribute(): ?string
    {
        return $this->getAttributes()['account_representative'] ?? null;
    }

    /**
     * Scope for filtering by location
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('customer_location', 'like', "%{$location}%");
    }

    /**
     * Scope for filtering by account representative
     */
    public function scopeByRepresentative($query, $representative)
    {
        return $query->where('account_representative', 'like', "%{$representative}%");
    }
}
