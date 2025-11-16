<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view categories', 'web');
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('view categories', 'web');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create categories', 'web');
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        if (!$user->hasPermissionTo('edit categories', 'web')) {
            return false;
        }

        // Warehouse keepers can update categories
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any category
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        // Check if category has products
        if ($category->products()->exists()) {
            return false;
        }

        // Warehouse keepers can delete categories (with permission)
        if ($user->hasRole('أمين_مستودع') && $user->hasPermissionTo('delete categories', 'web')) {
            return true;
        }

        // General managers can delete any category
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage category hierarchy.
     */
    public function manageHierarchy(User $user): bool
    {
        // Warehouse keepers can manage category hierarchy
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can manage category hierarchy
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view category reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports', 'web') &&
                ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can export category data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports', 'web') &&
                ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }
}