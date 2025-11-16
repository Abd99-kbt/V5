<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\License;
use App\Models\User;
use Carbon\Carbon;

class SalesService
{
    protected $pricing = [
        'trial' => [
            'price' => 0,
            'duration_days' => 30,
            'features' => ['basic_features', 'trial_watermark']
        ],
        'basic' => [
            'price' => 99,
            'duration_days' => 365,
            'features' => ['basic_features', 'email_support']
        ],
        'professional' => [
            'price' => 299,
            'duration_days' => 365,
            'features' => ['all_features', 'phone_support', 'api_access']
        ],
        'enterprise' => [
            'price' => 999,
            'duration_days' => 365,
            'features' => ['all_features', 'api_access', 'custom_integrations', 'priority_support', 'white_label']
        ]
    ];

    /**
     * Process a new sale
     */
    public function processSale(array $saleData)
    {
        try {
            // Validate sale data
            $this->validateSaleData($saleData);

            // Create or update customer
            $customer = $this->createOrUpdateCustomer($saleData);

            // Generate license
            $license = $this->generateLicenseForSale($saleData, $customer);

            // Process payment
            $paymentResult = $this->processPayment($saleData);

            if (!$paymentResult['success']) {
                Log::error('Payment processing failed', ['sale_data' => $saleData]);
                return ['success' => false, 'message' => 'Payment processing failed'];
            }

            // Send confirmation emails
            $this->sendSaleConfirmation($license, $customer, $saleData);

            // Log the sale
            Log::info('Sale processed successfully', [
                'license_key' => $license->license_key,
                'customer_email' => $customer->email,
                'amount' => $saleData['amount']
            ]);

            return [
                'success' => true,
                'license' => $license,
                'customer' => $customer,
                'payment' => $paymentResult
            ];

        } catch (\Exception $e) {
            Log::error('Sale processing failed', [
                'error' => $e->getMessage(),
                'sale_data' => $saleData
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate license for sale
     */
    protected function generateLicenseForSale($saleData, $customer)
    {
        $licenseType = $saleData['license_type'];
        $pricing = $this->pricing[$licenseType];

        $licenseData = [
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'license_type' => $licenseType,
            'max_users' => $saleData['max_users'] ?? $pricing['max_users'] ?? 10,
            'max_installations' => $saleData['max_installations'] ?? $pricing['max_installations'] ?? 1,
            'expires_at' => isset($saleData['subscription']) && $saleData['subscription']
                ? null // Lifetime for subscriptions
                : now()->addDays($pricing['duration_days']),
            'features' => $pricing['features'],
            'metadata' => [
                'sale_date' => now(),
                'payment_method' => $saleData['payment_method'] ?? 'unknown',
                'source' => $saleData['source'] ?? 'direct',
                'discount_code' => $saleData['discount_code'] ?? null
            ]
        ];

        return app(LicenseService::class)->generateLicense($licenseData);
    }

    /**
     * Process payment
     */
    protected function processPayment($saleData)
    {
        // This would integrate with payment processors like Stripe, PayPal, etc.
        // For now, we'll simulate a successful payment

        $amount = $saleData['amount'];
        $paymentMethod = $saleData['payment_method'] ?? 'credit_card';

        // Simulate payment processing
        sleep(1); // Simulate processing time

        return [
            'success' => true,
            'transaction_id' => 'txn_' . strtoupper(uniqid()),
            'amount' => $amount,
            'currency' => $saleData['currency'] ?? 'USD',
            'payment_method' => $paymentMethod,
            'processed_at' => now()
        ];
    }

    /**
     * Send sale confirmation emails
     */
    protected function sendSaleConfirmation($license, $customer, $saleData)
    {
        try {
            // Send email to customer
            Mail::to($customer->email)->send(new \App\Mail\LicensePurchased($license, $customer, $saleData));

            // Send notification to sales team
            Mail::to(config('mail.sales_team', 'sales@yourcompany.com'))
                ->send(new \App\Mail\SaleNotification($license, $customer, $saleData));

        } catch (\Exception $e) {
            Log::error('Failed to send sale confirmation emails', [
                'license_key' => $license->license_key,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate pricing with discounts
     */
    public function calculatePricing($licenseType, $discountCode = null, $quantity = 1)
    {
        if (!isset($this->pricing[$licenseType])) {
            return ['error' => 'Invalid license type'];
        }

        $basePrice = $this->pricing[$licenseType]['price'];
        $total = $basePrice * $quantity;

        $discount = 0;
        if ($discountCode) {
            $discount = $this->calculateDiscount($discountCode, $total);
        }

        $finalTotal = $total - $discount;

        return [
            'license_type' => $licenseType,
            'base_price' => $basePrice,
            'quantity' => $quantity,
            'subtotal' => $total,
            'discount' => $discount,
            'total' => $finalTotal,
            'currency' => 'USD'
        ];
    }

    /**
     * Calculate discount
     */
    protected function calculateDiscount($discountCode, $total)
    {
        // This would check against a discount codes database
        $discounts = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10],
            'SAVE50' => ['type' => 'fixed', 'value' => 50],
            'ENTERPRISE20' => ['type' => 'percentage', 'value' => 20, 'min_purchase' => 500],
        ];

        if (!isset($discounts[$discountCode])) {
            return 0;
        }

        $discount = $discounts[$discountCode];

        if (isset($discount['min_purchase']) && $total < $discount['min_purchase']) {
            return 0;
        }

        if ($discount['type'] === 'percentage') {
            return ($total * $discount['value']) / 100;
        } elseif ($discount['type'] === 'fixed') {
            return min($discount['value'], $total);
        }

        return 0;
    }

    /**
     * Get sales statistics
     */
    public function getSalesStatistics($period = '30 days')
    {
        $startDate = Carbon::parse("-{$period}");

        $licenses = License::where('created_at', '>=', $startDate)->get();

        $stats = [
            'period' => $period,
            'total_sales' => $licenses->count(),
            'total_revenue' => $licenses->sum(function ($license) {
                return $this->pricing[$license->license_type]['price'] ?? 0;
            }),
            'sales_by_type' => $licenses->groupBy('license_type')->map(function ($group) {
                return $group->count();
            }),
            'new_customers' => $licenses->unique('customer_email')->count(),
            'conversion_rate' => $this->calculateConversionRate($startDate),
        ];

        return $stats;
    }

    /**
     * Calculate conversion rate (simplified)
     */
    protected function calculateConversionRate($startDate)
    {
        // This would require tracking leads/trials vs purchases
        // For now, return a placeholder
        return 0.15; // 15% conversion rate
    }

    /**
     * Handle refunds
     */
    public function processRefund($licenseKey, $reason, $amount = null)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        // Mark license as refunded
        $license->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => 'Refunded: ' . $reason
        ]);

        // Process refund with payment processor
        $refundAmount = $amount ?? $this->pricing[$license->license_type]['price'];

        // Send refund confirmation email
        try {
            Mail::to($license->customer_email)->send(new \App\Mail\RefundProcessed($license, $refundAmount, $reason));
        } catch (\Exception $e) {
            Log::error('Failed to send refund confirmation', ['license_key' => $licenseKey]);
        }

        Log::info('Refund processed', [
            'license_key' => $licenseKey,
            'amount' => $refundAmount,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'refund_amount' => $refundAmount,
            'license' => $license
        ];
    }

    /**
     * Validate sale data
     */
    protected function validateSaleData($data)
    {
        $required = ['license_type', 'customer_email', 'customer_name', 'amount'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!isset($this->pricing[$data['license_type']])) {
            throw new \InvalidArgumentException("Invalid license type: {$data['license_type']}");
        }
    }

    /**
     * Create or update customer
     */
    protected function createOrUpdateCustomer($data)
    {
        // This would typically create/update a Customer model
        // For now, return a simple object
        return (object) [
            'email' => $data['customer_email'],
            'name' => $data['customer_name'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];
    }

    /**
     * Get available license types
     */
    public function getLicenseTypes()
    {
        return $this->pricing;
    }

    /**
     * Generate sales report
     */
    public function generateSalesReport($startDate, $endDate)
    {
        $licenses = License::whereBetween('created_at', [$startDate, $endDate])->get();

        $report = [
            'period' => [$startDate, $endDate],
            'total_licenses' => $licenses->count(),
            'revenue_by_type' => [],
            'licenses_by_month' => [],
            'top_customers' => [],
        ];

        foreach ($licenses as $license) {
            $type = $license->license_type;
            $price = $this->pricing[$type]['price'] ?? 0;

            if (!isset($report['revenue_by_type'][$type])) {
                $report['revenue_by_type'][$type] = 0;
            }
            $report['revenue_by_type'][$type] += $price;

            $month = $license->created_at->format('Y-m');
            if (!isset($report['licenses_by_month'][$month])) {
                $report['licenses_by_month'][$month] = 0;
            }
            $report['licenses_by_month'][$month]++;
        }

        return $report;
    }
}