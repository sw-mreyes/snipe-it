<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever currently holds an asset that someone has reserved it, so they
 * know it is expected back (custom fork feature).
 */
class ReservationAssetExpectedCheckinNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation, public Asset $asset)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $reservation = $this->reservation->loadMissing('user');

        return (new MailMessage)
            ->subject(trans('reservations.mail.expected_checkin_subject', [
                'tag' => $this->asset->asset_tag,
            ]))
            ->greeting(trans('reservations.mail.expected_checkin_greeting'))
            ->line($this->asset->name ?: $this->asset->asset_tag)
            ->line(trans('reservations.reserved_window', [
                'start' => $reservation->start?->format('Y-m-d H:i'),
                'end' => $reservation->end?->format('Y-m-d H:i'),
            ]))
            ->line(trans('reservations.user').': '.$reservation->user?->present()->fullName)
            ->action(
                trans('reservations.reservation'),
                route('reservations.show', ['reservation' => $reservation->id])
            );
    }
}
