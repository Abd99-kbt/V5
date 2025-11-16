<?php

namespace App\Filament\Resources\OrderProcessings\Pages;

use App\Filament\Resources\OrderProcessings\OrderProcessingResource;
use App\Models\WeightTransfer;
use App\Services\WeightTransferApprovalService;
use App\Services\SortingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOrderProcessing extends EditRecord
{
    protected static string $resource = OrderProcessingResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            DeleteAction::make(),
        ];

        $record = $this->getRecord();
        $user = Auth::user();

        // Add approval actions if there are pending weight transfers for this stage
        $pendingTransfers = WeightTransfer::where('order_id', $record->order_id)
            ->where('to_stage', $record->work_stage_id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingTransfers as $transfer) {
            $approvalService = new WeightTransferApprovalService();

            if ($approvalService->canUserApproveTransfer($user, $transfer)) {
                $actions[] = Action::make('approve_transfer_' . $transfer->id)
                    ->label(__('order_processings.approve_transfer'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_approve_transfer'))
                    ->modalDescription(__('order_processings.confirm_approve_transfer_desc', [
                        'weight' => $transfer->weight_transferred,
                        'from_stage' => $transfer->from_stage,
                        'to_stage' => $transfer->to_stage
                    ]))
                    ->modalSubmitActionLabel(__('order_processings.approve'))
                    ->action(function () use ($transfer, $approvalService) {
                        $result = $approvalService->approveTransfer(Auth::user(), $transfer);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.transfer_approved'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.transfer_approval_failed'))
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }

                        return redirect()->refresh();
                    });

                $actions[] = Action::make('reject_transfer_' . $transfer->id)
                    ->label(__('order_processings.reject_transfer'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_reject_transfer'))
                    ->modalDescription(__('order_processings.confirm_reject_transfer_desc'))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label(__('order_processings.rejection_reason'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->modalSubmitActionLabel(__('order_processings.reject'))
                    ->action(function (array $data) use ($transfer, $approvalService) {
                        $result = $approvalService->rejectTransfer(Auth::user(), $transfer, $data['reason']);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.transfer_rejected'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.transfer_rejection_failed'))
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }

                        return redirect()->refresh();
                    });
            }
        }

        // Add sorting-specific actions for Sorting stage
        if ($record->isSortingStage()) {
            $sortingService = new SortingService();

            // Approve sorting action
            if ($sortingService->canUserApproveSorting($user, $record) && !$record->sorting_approved) {
                $actions[] = Action::make('approve_sorting')
                    ->label(__('order_processings.approve_sorting'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_approve_sorting'))
                    ->modalDescription(__('order_processings.confirm_approve_sorting_desc'))
                    ->modalSubmitActionLabel(__('order_processings.approve'))
                    ->action(function () use ($record, $sortingService, $user) {
                        $result = $sortingService->approveSorting($record, $user);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.sorting_approved'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.sorting_approval_failed'))
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }

                        return redirect()->refresh();
                    });
            }

            // Transfer to destination action
            if ($record->sorting_approved && !$record->transfer_completed) {
                $actions[] = Action::make('transfer_to_destination')
                    ->label(__('order_processings.transfer_to_destination'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_transfer_to_destination'))
                    ->modalDescription(__('order_processings.confirm_transfer_to_destination_desc'))
                    ->form([
                        \Filament\Forms\Components\Select::make('destination_type')
                            ->label(__('order_processings.destination_type'))
                            ->options([
                                'cutting_warehouse' => __('order_processings.destination_cutting'),
                                'direct_delivery' => __('order_processings.destination_delivery'),
                                'other_warehouse' => __('order_processings.destination_other'),
                            ])
                            ->default('cutting_warehouse')
                            ->required(),
                        \Filament\Forms\Components\Select::make('destination_warehouse_id')
                            ->label(__('order_processings.destination_warehouse'))
                            ->relationship('destinationWarehouse', 'name')
                            ->required()
                            ->visible(fn ($get) => $get('destination_type') === 'other_warehouse'),
                    ])
                    ->modalSubmitActionLabel(__('order_processings.transfer'))
                    ->action(function (array $data) use ($record, $sortingService, $user) {
                        $result = $sortingService->transferToDestination(
                            $record,
                            $user,
                            $data['destination_warehouse_id'] ?? null,
                            $data['destination_type']
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.transfer_completed'))
                                ->body(__('order_processings.transfer_completed_desc', [
                                    'destination' => $result['destination'],
                                    'warehouse' => $result['warehouse']
                                ]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.transfer_failed'))
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }

                        return redirect()->refresh();
                    });
            }
        }

        return $actions;
    }
}