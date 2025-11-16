<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\AutomatedInvoiceNumberService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate invoice number if not provided
        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = app(AutomatedInvoiceNumberService::class)->generateInvoiceNumber();
        }

        return $data;
    }
}