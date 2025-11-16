<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $user = auth()->user();

        // Role-based filtering for warehouse managers (أمين_مستودع)
        if ($user && $user->hasRole('أمين_مستودع')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('current_stage', 'حجز_المواد')
                  ->orWhere('assigned_to', $user->id);
            });
        }

        return $query;
    }
}
