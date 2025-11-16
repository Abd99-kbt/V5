<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    // use Backpack\CRUD\app\Models\Traits\CrudTrait; // Temporarily disabled
    
    protected $fillable = [
        'name_en',
        'name_ar',
        'contact_person_en',
        'contact_person_ar',
        'email',
        'phone',
        'address_en',
        'address_ar',
        'tax_number',
        'commercial_register',
        'credit_limit',
        'payment_terms',
        'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'payment_terms' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the products for the supplier.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the supplier name based on current locale
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * Get the contact person based on current locale
     */
    public function getContactPersonAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->contact_person_ar : $this->contact_person_en;
    }

    /**
     * Get the address based on current locale
     */
    public function getAddressAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->address_ar : $this->address_en;
    }
}
