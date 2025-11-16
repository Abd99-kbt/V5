@props([
    'customer' => null,
    'showCreditLimit' => true,
    'showOrderHistory' => true,
])

<x-filament-panels::modal
    id="customer-preview-modal"
    :open="$customer !== null"
    wire:model="customer"
    class="fi-modal"
>
    <div class="fi-modal-window">
        <div class="fi-modal-header">
            <div class="fi-modal-heading">
                <h2 class="fi-modal-heading-title">
                    {{ __('Customer Details') }}: {{ $customer?->name }}
                </h2>
            </div>

            <div class="fi-modal-actions">
                <x-filament::modal.actions.close />
            </div>
        </div>

        <div class="fi-modal-content">
            @if($customer)
                <div class="space-y-6">
                    <!-- Customer Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-semibold mb-3">{{ __('Basic Information') }}</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Name') }}:</span>
                                    <span class="font-medium">{{ $customer->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Phone') }}:</span>
                                    <span class="font-medium">{{ $customer->mobile_number ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Email') }}:</span>
                                    <span class="font-medium">{{ $customer->email ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Province') }}:</span>
                                    <span class="font-medium">{{ $customer->province ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Type') }}:</span>
                                    <span class="font-medium">{{ ucfirst($customer->customer_type) }}</span>
                                </div>
                            </div>
                        </div>

                        @if($showCreditLimit)
                        <div>
                            <h3 class="text-lg font-semibold mb-3">{{ __('Credit Information') }}</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Credit Limit') }}:</span>
                                    <span class="font-medium">{{ number_format($customer->credit_limit, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Outstanding Amount') }}:</span>
                                    <span class="font-medium">{{ number_format($customer->outstanding_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Available Credit') }}:</span>
                                    <span class="font-medium {{ $customer->credit_limit - $customer->outstanding_amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ number_format($customer->credit_limit - $customer->outstanding_amount, 2) }}
                                    </span>
                                </div>
                                @if($customer->credit_limit - $customer->outstanding_amount < 0)
                                    <div class="text-red-600 text-sm">
                                        ⚠️ {{ __('Credit limit exceeded') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Address -->
                    @if($customer->address)
                    <div>
                        <h3 class="text-lg font-semibold mb-3">{{ __('Address') }}</h3>
                        <p class="text-gray-700">{{ $customer->address }}</p>
                    </div>
                    @endif

                    <!-- Recent Orders -->
                    @if($showOrderHistory && $customer->orders && $customer->orders->count() > 0)
                    <div>
                        <h3 class="text-lg font-semibold mb-3">{{ __('Recent Orders') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">{{ __('Order #') }}</th>
                                        <th class="text-left py-2">{{ __('Date') }}</th>
                                        <th class="text-right py-2">{{ __('Amount') }}</th>
                                        <th class="text-center py-2">{{ __('Status') }}</th>
                                        <th class="text-center py-2">{{ __('Paid') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customer->orders as $order)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-2">{{ $order->order_number }}</td>
                                        <td class="py-2">{{ $order->order_date?->format('Y-m-d') }}</td>
                                        <td class="py-2 text-right">{{ number_format($order->total_amount, 2) }}</td>
                                        <td class="py-2 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                @if($order->status === 'delivered') bg-green-100 text-green-800
                                                @elseif($order->status === 'processing') bg-blue-100 text-blue-800
                                                @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-center">
                                            @if($order->is_paid)
                                                <span class="text-green-600">✓</span>
                                            @else
                                                <span class="text-red-600">✗</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @else
                        <div class="text-center text-gray-500 py-4">
                            {{ __('No recent orders found') }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::modal>