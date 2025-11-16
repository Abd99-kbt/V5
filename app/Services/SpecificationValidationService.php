<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

class SpecificationValidationService
{
    /**
     * Validate delivery specifications for an order
     */
    public function validateDeliverySpecifications(Order $order): array
    {
        $errors = [];

        // Check if specifications are provided
        if (!$this->hasDeliverySpecifications($order)) {
            return $errors; // No specifications provided, no validation needed
        }

        // Validate dimensions
        if ($order->delivery_width && $order->delivery_width <= 0) {
            $errors[] = 'Delivery width must be greater than 0';
        }

        if ($order->delivery_length && $order->delivery_length <= 0) {
            $errors[] = 'Delivery length must be greater than 0';
        }

        if ($order->delivery_thickness && $order->delivery_thickness <= 0) {
            $errors[] = 'Delivery thickness must be greater than 0';
        }

        // Validate grammage
        if ($order->delivery_grammage && $order->delivery_grammage <= 0) {
            $errors[] = 'Delivery grammage must be greater than 0';
        }

        // Validate quantity
        if ($order->delivery_quantity && $order->delivery_quantity <= 0) {
            $errors[] = 'Delivery quantity must be greater than 0';
        }

        // Validate weight
        if ($order->delivery_weight && $order->delivery_weight <= 0) {
            $errors[] = 'Delivery weight must be greater than 0';
        }

        return $errors;
    }

    /**
     * Check specification compatibility between order and material
     */
    public function checkSpecificationCompatibility(Order $order, Product $material): array
    {
        $warnings = [];

        if (!$this->hasDeliverySpecifications($order)) {
            return $warnings;
        }

        // Check width compatibility
        if ($order->delivery_width && $material->width && abs($order->delivery_width - $material->width) > 0.1) {
            $warnings[] = "Delivery width ({$order->delivery_width} cm) differs from material width ({$material->width} cm)";
        }

        // Check grammage compatibility
        if ($order->delivery_grammage && $material->grammage && abs($order->delivery_grammage - $material->grammage) > 1) {
            $warnings[] = "Delivery grammage ({$order->delivery_grammage} g/m²) differs from material grammage ({$material->grammage} g/m²)";
        }

        // Check quality compatibility
        if ($order->delivery_quality && $material->quality && $order->delivery_quality !== $material->quality) {
            $warnings[] = "Delivery quality ({$order->delivery_quality}) differs from material quality ({$material->quality})";
        }

        return $warnings;
    }

    /**
     * Validate specifications for cutting process
     */
    public function validateCuttingSpecifications(Order $order): array
    {
        $errors = [];

        if (!$this->hasDeliverySpecifications($order)) {
            return $errors;
        }

        // For cutting, we need at least width and length
        if (!$order->delivery_width) {
            $errors[] = 'Delivery width is required for cutting specifications';
        }

        if (!$order->delivery_length) {
            $errors[] = 'Delivery length is required for cutting specifications';
        }

        // Check if dimensions are reasonable for cutting
        if ($order->delivery_width && $order->delivery_width > 500) {
            $errors[] = 'Delivery width seems too large for standard cutting equipment';
        }

        if ($order->delivery_length && $order->delivery_length > 10000) {
            $errors[] = 'Delivery length seems too large for standard cutting equipment';
        }

        return $errors;
    }

    /**
     * Validate specifications for sorting process
     */
    public function validateSortingSpecifications(Order $order): array
    {
        $errors = [];

        if (!$this->hasDeliverySpecifications($order)) {
            return $errors;
        }

        // For sorting, we need weight information
        if (!$order->delivery_weight && !$order->delivery_quantity) {
            $errors[] = 'Either delivery weight or quantity is required for sorting specifications';
        }

        return $errors;
    }

    /**
     * Get specification compatibility score (0-100)
     */
    public function getCompatibilityScore(Order $order, Product $material): float
    {
        if (!$this->hasDeliverySpecifications($order)) {
            return 100; // No specifications, assume full compatibility
        }

        $totalChecks = 0;
        $compatibleChecks = 0;

        // Check width
        if ($order->delivery_width) {
            $totalChecks++;
            if ($material->width && abs($order->delivery_width - $material->width) <= 0.1) {
                $compatibleChecks++;
            }
        }

        // Check grammage
        if ($order->delivery_grammage) {
            $totalChecks++;
            if ($material->grammage && abs($order->delivery_grammage - $material->grammage) <= 1) {
                $compatibleChecks++;
            }
        }

        // Check quality
        if ($order->delivery_quality) {
            $totalChecks++;
            if ($material->quality && $order->delivery_quality === $material->quality) {
                $compatibleChecks++;
            }
        }

        return $totalChecks > 0 ? ($compatibleChecks / $totalChecks) * 100 : 100;
    }

    /**
     * Check if order has delivery specifications
     */
    private function hasDeliverySpecifications(Order $order): bool
    {
        return $order->delivery_width ||
               $order->delivery_length ||
               $order->delivery_thickness ||
               $order->delivery_grammage ||
               $order->delivery_quality ||
               $order->delivery_quantity ||
               $order->delivery_weight;
    }

    /**
     * Get recommended materials based on specifications
     */
    public function getRecommendedMaterials(Order $order): array
    {
        if (!$this->hasDeliverySpecifications($order)) {
            return [];
        }

        $query = Product::query();

        // Filter by width if specified
        if ($order->delivery_width) {
            $query->where('width', '>=', $order->delivery_width - 0.5)
                  ->where('width', '<=', $order->delivery_width + 0.5);
        }

        // Filter by grammage if specified
        if ($order->delivery_grammage) {
            $query->where('grammage', '>=', $order->delivery_grammage - 5)
                  ->where('grammage', '<=', $order->delivery_grammage + 5);
        }

        // Filter by quality if specified
        if ($order->delivery_quality) {
            $query->where('quality', $order->delivery_quality);
        }

        return $query->get()->toArray();
    }
}
