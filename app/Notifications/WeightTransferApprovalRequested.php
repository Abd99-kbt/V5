<?php

namespace App\Notifications;

use App\Models\WeightTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeightTransferApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected WeightTransfer $weightTransfer;

    /**
     * Create a new notification instance.
     */
    public function __construct(WeightTransfer $weightTransfer)
    {
        $this->weightTransfer = $weightTransfer;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('filament.resources.order-processings.edit', $this->weightTransfer->order_id);

        return (new MailMessage)
            ->subject('Weight Transfer Approval Required')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A weight transfer is ready for your approval.')
            ->line('**Transfer Details:**')
            ->line('Order: #' . $this->weightTransfer->order->order_number)
            ->line('From Stage: ' . $this->weightTransfer->from_stage)
            ->line('To Stage: ' . $this->weightTransfer->to_stage)
            ->line('Weight: ' . $this->weightTransfer->weight_transferred . ' kg')
            ->action('Review Transfer', $url)
            ->line('Please approve or reject this transfer to continue the order processing.')
            ->salutation('Best regards, Order Processing System');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Weight Transfer Approval Required',
            'message' => 'A weight transfer of ' . $this->weightTransfer->weight_transferred . ' kg from ' . $this->weightTransfer->from_stage . ' to ' . $this->weightTransfer->to_stage . ' requires your approval.',
            'weight_transfer_id' => $this->weightTransfer->id,
            'order_id' => $this->weightTransfer->order_id,
            'action_url' => route('filament.resources.order-processings.edit', $this->weightTransfer->order_id),
            'type' => 'weight_transfer_approval',
        ];
    }
}
