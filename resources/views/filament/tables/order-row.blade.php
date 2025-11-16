@php
    $stages = [
        'إنشاء' => 'gray',
        'مراجعة' => 'yellow',
        'حجز_المواد' => 'blue',
        'فرز' => 'purple',
        'قص' => 'orange',
        'تعبئة' => 'indigo',
        'فوترة' => 'green',
        'تسليم' => 'emerald',
    ];

    $currentStageIndex = array_search($record->current_stage, array_keys($stages)) + 1;
    $totalStages = count($stages);
    $progressPercentage = ($currentStageIndex / $totalStages) * 100;
@endphp

<div class="space-y-2 p-4 bg-white rounded-lg shadow-sm border">
    <!-- First Line: Order Number, Type, Current Stage -->
    <div class="flex flex-wrap items-center gap-4">
        <div class="font-semibold text-lg">{{ $record->order_number }}</div>
        <div class="badge badge-{{ $record->type == 'sale' ? 'success' : 'info' }}">{{ __('orders.type_options.' . $record->type) }}</div>
        <div class="badge badge-{{ $stages[$record->current_stage] ?? 'gray' }}">{{ __('orders.current_stage_options.' . $record->current_stage) }}</div>
    </div>

    <!-- Second Line: Customer/Supplier Info -->
    <div class="text-sm text-gray-600">
        @if($record->customer)
            <span>{{ __('orders.customer_name') }}: {{ $record->customer->name ?? $record->customer_name }}</span>
            @if($record->customer_phone)
                <span class="ml-4">{{ __('orders.customer_phone') }}: {{ $record->customer_phone }}</span>
            @endif
        @elseif($record->supplier)
            <span>{{ __('orders.supplier_id') }}: {{ $record->supplier->name ?? 'N/A' }}</span>
        @endif
    </div>

    <!-- Third Line: Material Specs -->
    <div class="text-sm text-gray-600">
        <span>{{ __('orders.material_type') }}: {{ __('orders.material_type_options.' . $record->material_type) }}</span>
        @if($record->required_weight)
            <span class="ml-4">{{ __('orders.required_weight') }}: {{ $record->required_weight }} kg</span>
        @endif
        @if($record->required_length)
            <span class="ml-4">{{ __('orders.required_length') }}: {{ $record->required_length }} m</span>
        @endif
        @if($record->required_width)
            <span class="ml-4">{{ __('orders.required_width') }}: {{ $record->required_width }} m</span>
        @endif
        @if($record->required_plates)
            <span class="ml-4">{{ __('orders.required_plates') }}: {{ $record->required_plates }}</span>
        @endif
    </div>

    <!-- Fourth Line: Financial Summary -->
    <div class="text-sm text-gray-600">
        <span>{{ __('orders.total_amount') }}: ${{ number_format($record->total_amount, 2) }}</span>
        <span class="ml-4">{{ __('orders.paid_amount') }}: ${{ number_format($record->paid_amount, 2) }}</span>
        <span class="ml-4">{{ __('orders.remaining_amount') }}: ${{ number_format($record->remaining_amount, 2) }}</span>
    </div>

    <!-- Progress Bar -->
    <div class="progress-bar mt-4">
        <div class="flex justify-between text-xs text-gray-500 mb-1">
            @foreach($stages as $stage => $color)
                <span class="{{ $stage == $record->current_stage ? 'font-semibold text-' . $color . '-600' : '' }}">{{ __('orders.current_stage_options.' . $stage) }}</span>
            @endforeach
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-gradient-to-r from-gray-400 via-yellow-400 via-blue-400 via-purple-400 via-orange-400 via-indigo-400 via-green-400 to-emerald-400 h-2 rounded-full transition-all duration-300" style="width: {{ $progressPercentage }}%"></div>
        </div>
    </div>
</div>