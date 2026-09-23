<?php

namespace App\Notifications;

use App\Models\BookingSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAvailabilityDetected extends Notification
{
    use Queueable;

    public function __construct(public BookingSearch $search) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->search->availability_url ?: config('services.vatican.ticket_url');

        return (new MailMessage)
            ->subject('Vatican ticket availability needs your review')
            ->line(($this->search->availability_title ?: 'Booking availability detected')." for {$this->search->visit_date->format('d F Y')}.")
            ->action('Open booking page', $url)
            ->line('Please complete the official booking flow manually.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_search_id' => $this->search->id,
            'visit_date' => $this->search->visit_date->toDateString(),
            'schedules' => $this->search->schedules,
            'title' => $this->search->availability_title,
            'url' => $this->search->availability_url ?: config('services.vatican.ticket_url'),
        ];
    }
}
