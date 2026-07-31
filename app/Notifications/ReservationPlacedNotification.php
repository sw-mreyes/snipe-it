<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the reserving user (and the team webhook) that a reservation was
 * placed (custom fork feature).
 */
class ReservationPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation)
    {
    }

    /**
     * Mail when delivered to a user, Slack/webhook when routed to a channel.
     */
    public function via($notifiable): array
    {
        return method_exists($notifiable, 'routeNotificationForSlack') && ! isset($notifiable->email)
            ? ['slack']
            : ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $reservation = $this->reservation->loadMissing('assets', 'user');

        $message = (new MailMessage)
            ->subject(trans('reservations.mail.placed_subject', ['name' => $reservation->name]))
            ->greeting(trans('reservations.mail.placed_greeting'))
            ->line(trans('reservations.reserved_window', [
                'start' => $reservation->start?->format('Y-m-d H:i'),
                'end' => $reservation->end?->format('Y-m-d H:i'),
            ]));

        foreach ($reservation->assets as $asset) {
            $message->line('• '.($asset->name ?: $asset->asset_tag));
        }

        if ($reservation->notes) {
            $message->line($reservation->notes);
        }

        return $message->action(
            trans('reservations.reservation'),
            route('reservations.show', ['reservation' => $reservation->id])
        );
    }

    public function toSlack($notifiable): SlackMessage
    {
        $reservation = $this->reservation->loadMissing('assets', 'user');

        $assets = $reservation->assets
            ->map(fn ($asset) => $asset->name ?: $asset->asset_tag)
            ->implode(', ');

        return (new SlackMessage)
            ->success()
            ->content(trans('reservations.mail.placed_subject', ['name' => $reservation->name]))
            ->attachment(function ($attachment) use ($reservation, $assets) {
                $attachment->title($reservation->name, route('reservations.show', ['reservation' => $reservation->id]))
                    ->fields(array_filter([
                        trans('reservations.user') => $reservation->user?->present()->fullName,
                        trans('reservations.from') => $reservation->start?->format('Y-m-d H:i'),
                        trans('reservations.to') => $reservation->end?->format('Y-m-d H:i'),
                        trans('reservations.assets') => $assets,
                    ]));
            });
    }
}
