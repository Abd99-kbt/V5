<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;

class CustomerValidationService
{
    /**
     * Validate if customer can place an order based on credit limit
     */
    public function validateCreditLimit(Customer $customer, float $orderAmount): array
    {
        $availableCredit = $customer->credit_limit - $customer->outstanding_amount;

        if ($availableCredit < $orderAmount) {
            return [
                'valid' => false,
                'message' => __('Customer credit limit exceeded. Available credit: :available, Order amount: :order', [
                    'available' => number_format($availableCredit, 2),
                    'order' => number_format($orderAmount, 2)
                ]),
                'available_credit' => $availableCredit,
                'required_credit' => $orderAmount,
            ];
        }

        return [
            'valid' => true,
            'message' => __('Credit limit validation passed'),
            'available_credit' => $availableCredit,
            'remaining_credit' => $availableCredit - $orderAmount,
        ];
    }

    /**
     * Check if customer is active
     */
    public function validateCustomerStatus(Customer $customer): array
    {
        if (!$customer->is_active) {
            return [
                'valid' => false,
                'message' => __('Customer account is inactive'),
            ];
        }

        return [
            'valid' => true,
            'message' => __('Customer is active'),
        ];
    }

    /**
     * Comprehensive customer validation for order creation
     */
    public function validateCustomerForOrder(Customer $customer, float $orderAmount): array
    {
        $results = [];

        // Check customer status
        $statusValidation = $this->validateCustomerStatus($customer);
        $results['status'] = $statusValidation;

        // Check credit limit
        $creditValidation = $this->validateCreditLimit($customer, $orderAmount);
        $results['credit'] = $creditValidation;

        // Overall validation
        $isValid = $statusValidation['valid'] && $creditValidation['valid'];

        return [
            'valid' => $isValid,
            'customer' => $customer,
            'order_amount' => $orderAmount,
            'validations' => $results,
            'warnings' => $this->getWarnings($customer, $orderAmount),
            'recommendations' => $this->getRecommendations($customer, $orderAmount),
        ];
    }

    /**
     * Get warnings for customer order
     */
    protected function getWarnings(Customer $customer, float $orderAmount): array
    {
        $warnings = [];

        $availableCredit = $customer->credit_limit - $customer->outstanding_amount;

        // Warning if order amount is more than 80% of available credit
        if ($orderAmount > ($availableCredit * 0.8)) {
            $warnings[] = __('Order amount is high relative to available credit');
        }

        // Warning if customer has overdue payments
        $overdueOrders = $customer->orders()
            ->where('is_paid', false)
            ->where('required_date', '<', now())
            ->count();

        if ($overdueOrders > 0) {
            $warnings[] = __('Customer has :count overdue order(s)', ['count' => $overdueOrders]);
        }

        return $warnings;
    }

    /**
     * Get recommendations for customer order
     */
    protected function getRecommendations(Customer $customer, float $orderAmount): array
    {
        $recommendations = [];

        $availableCredit = $customer->credit_limit - $customer->outstanding_amount;

        // Recommend payment if credit is low
        if ($availableCredit < ($customer->credit_limit * 0.2)) {
            $recommendations[] = __('Consider requesting payment before processing this order');
        }

        // Recommend credit limit increase if frequently hitting limits
        $recentOrders = $customer->orders()
            ->where('created_at', '>=', now()->subMonths(3))
            ->count();

        if ($recentOrders > 10) {
            $recommendations[] = __('Customer has high order frequency - consider credit limit review');
        }

        return $recommendations;
    }

    /**
     * Get customer credit summary
     */
    public function getCreditSummary(Customer $customer): array
    {
        return [
            'credit_limit' => $customer->credit_limit,
            'outstanding_amount' => $customer->outstanding_amount,
            'available_credit' => $customer->credit_limit - $customer->outstanding_amount,
            'utilization_percentage' => $customer->credit_limit > 0
                ? round(($customer->outstanding_amount / $customer->credit_limit) * 100, 2)
                : 0,
            'total_orders_value' => $customer->total_orders_value,
            'total_paid' => $customer->total_paid,
        ];
    }
}