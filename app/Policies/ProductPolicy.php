<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view materials', 'web') ||
               $user->hasPermissionTo('manage materials', 'web') ||
               $user->hasRole('مدير_شامل');
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('view materials');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create materials');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        if (!$user->hasPermissionTo('edit materials')) {
            return false;
        }

        // Warehouse keepers can edit materials in their warehouses
        if ($user->hasRole('أمين_مستودع')) {
            return true; // They can manage materials across warehouses
        }

        // Cutting and sorting managers can update materials in their processing stages
        if (in_array($user->getRoleNames()->first(), ['مسؤول_قصاصة', 'مسؤول_فرازة'])) {
            return in_array($product->current_stage, ['فرز', 'قص']);
        }

        // General managers can edit any material
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Only materials with no reservations can be deleted
        if ($product->reserved_weight > 0) {
            return false;
        }

        // Warehouse keepers can delete materials
        if ($user->hasRole('أمين_مستودع') && $user->hasPermissionTo('delete materials')) {
            return true;
        }

        // General managers can delete any material
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can reserve the product.
     */
    public function reserve(User $user, Product $product): bool
    {
        // Warehouse keepers can reserve materials
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can reserve materials for orders
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can release reservation on the product.
     */
    public function releaseReservation(User $user, Product $product): bool
    {
        // Warehouse keepers can release reservations
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can release reservations
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can extract the product from warehouse.
     */
    public function extract(User $user, Product $product): bool
    {
        // Warehouse keepers can extract materials
        if ($user->hasRole('أمين_مستودع')) {
            return $product->status === 'متاح';
        }

        return false;
    }

    /**
     * Determine whether the user can transfer the product.
     */
    public function transfer(User $user, Product $product): bool
    {
        // Warehouse keepers can transfer materials
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Cutting managers can transfer to cutting warehouse
        if ($user->hasRole('مسؤول_قصاصة') && $product->current_stage === 'فرز') {
            return true;
        }

        // Sorting managers can transfer to sorting warehouse
        if ($user->hasRole('مسؤول_فرازة') && $product->current_stage === 'مستودع_أصلي') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can record waste for the product.
     */
    public function recordWaste(User $user, Product $product): bool
    {
        // Cutting managers can record waste during cutting
        if ($user->hasRole('مسؤول_قصاصة') && $product->current_stage === 'قص') {
            return true;
        }

        // Sorting managers can record waste during sorting
        if ($user->hasRole('مسؤول_فرازة') && $product->current_stage === 'فرز') {
            return true;
        }

        // Warehouse keepers can record waste
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can return the product to warehouse.
     */
    public function returnToWarehouse(User $user, Product $product): bool
    {
        // Warehouse keepers can return materials to warehouse
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Delivery managers can return materials after delivery
        if ($user->hasRole('مسؤول_تسليم')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can process the product (cutting/sorting).
     */
    public function process(User $user, Product $product): bool
    {
        // Cutting managers can process materials in cutting stage
        if ($user->hasRole('مسؤول_قصاصة') && $product->current_stage === 'قص') {
            return true;
        }

        // Sorting managers can process materials in sorting stage
        if ($user->hasRole('مسؤول_فرازة') && $product->current_stage === 'فرز') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view product reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports');
    }
}