<?php

namespace App\Filament\Resources\OrderProcessings\Pages;

use App\Filament\Resources\OrderProcessings\OrderProcessingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderProcessing extends CreateRecord
{
    protected static string $resource = OrderProcessingResource::class;
}