<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryPasswordNotification extends Notification
{
    public function __construct(public string $temporaryPassword) {}

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
            ->subject('Your temporary NFA GMR password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An Administrator created your NFA GMR account.')
            ->line('Temporary password: '.$this->temporaryPassword)
            ->line('This temporary password expires after 24 hours.')
            ->line('You must change it immediately after your first login.')
            ->action('Sign in to NFA GMR', route('login'))
            ->line('If you did not expect this account, contact the Administrator.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
