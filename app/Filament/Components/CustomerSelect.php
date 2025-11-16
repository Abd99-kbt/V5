<?php

namespace App\Filament\Components;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

class CustomerSelect extends Select
{
    protected bool $showPreview = true;
    protected bool $showCreditLimit = true;
    protected bool $showOrderHistory = true;
    protected int $recentOrdersLimit = 5;

    public function showPreview(bool $condition = true): static
    {
        $this->showPreview = $condition;
        return $this;
    }

    public function showCreditLimit(bool $condition = true): static
    {
        $this->showCreditLimit = $condition;
        return $this;
    }

    public function showOrderHistory(bool $condition = true): static
    {
        $this->showOrderHistory = $condition;
        return $this;
    }

    public function recentOrdersLimit(int $limit): static
    {
        $this->recentOrdersLimit = $limit;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable()
            ->getSearchResultsUsing(function (string $search): array {
                return Customer::where('is_active', true)
                    ->where(function ($query) use ($search) {
                        $query->where('name_en', 'like', "%{$search}%")
                            ->orWhere('name_ar', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(function (Customer $customer) {
                        return [
                            $customer->id => $this->formatCustomerOption($customer),
                        ];
                    })
                    ->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                if (!$value) return '';

                $customer = Customer::find($value);
                return $customer ? $this->formatCustomerOption($customer) : '';
            })
            ->options(function (): array {
                return Customer::where('is_active', true)
                    ->orderBy('name_en')
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(function (Customer $customer) {
                        return [
                            $customer->id => $this->formatCustomerOption($customer),
                        ];
                    })
                    ->toArray();
            });

        if ($this->showPreview) {
            $this->suffixAction(
                Action::make('preview')
                    ->label('معاينة')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(function (array $arguments, $set, $get, $state) {
                        $customerId = $get($this->getName());
                        if (!$customerId) return;

                        $customer = Customer::with(['orders' => function ($query) {
                            $query->latest()->limit($this->recentOrdersLimit);
                        }])->find($customerId);

                        if (!$customer) return;

                        $this->dispatch('open-customer-preview-modal', [
                            'customer' => $customer,
                            'showCreditLimit' => $this->showCreditLimit,
                            'showOrderHistory' => $this->showOrderHistory,
                        ]);
                    })
                    ->visible(fn ($get) => !empty($get($this->getName())))
            );
        }
    }

    protected function formatCustomerOption(Customer $customer): string
    {
        $name = $customer->name;
        $phone = $customer->mobile_number;
        $province = $customer->province;

        $parts = [$name];
        if ($phone) $parts[] = $phone;
        if ($province) $parts[] = $province;

        return implode(' - ', $parts);
    }

    public function getCustomerData($customerId): ?array
    {
        if (!$customerId) return null;

        $customer = Customer::with(['orders' => function ($query) {
            $query->latest()->limit($this->recentOrdersLimit);
        }])->find($customerId);

        if (!$customer) return null;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->mobile_number,
            'email' => $customer->email,
            'address' => $customer->address,
            'province' => $customer->province,
            'credit_limit' => $customer->credit_limit,
            'outstanding_amount' => $customer->outstanding_amount,
            'available_credit' => $customer->credit_limit - $customer->outstanding_amount,
            'customer_type' => $customer->customer_type,
            'recent_orders' => $customer->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'order_date' => $order->order_date?->format('Y-m-d'),
                    'is_paid' => $order->is_paid,
                ];
            })->toArray(),
        ];
    }
}