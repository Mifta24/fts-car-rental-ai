<?php

namespace App\Notifications;

use App\Models\HandoverRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells rental staff that the AI Rental Agent handed a conversation over to them.
 */
class NewHandoverRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HandoverRequest $handover)
    {
        $this->afterCommit();
        $this->locale($handover->conversation->rental->default_locale);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.new_handover.subject'))
            ->line(__('notifications.new_handover.intro'))
            ->line(__('notifications.new_handover.reason', ['reason' => str_replace('_', ' ', $this->handover->reason)]))
            ->line($this->handover->summary)
            ->action(__('notifications.new_handover.action'), route('admin.handovers.show', $this->handover));
    }
}
