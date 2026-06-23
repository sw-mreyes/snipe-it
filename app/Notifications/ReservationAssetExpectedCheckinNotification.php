<?php

namespace App\Notifications;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the user currently responsible for an asset when that asset gets
 * reserved (custom fork feature), so they know a hand-off is expected.
 *
 * Mail only — addressed to a specific responsible user, not the team channel.
 */
#[\AllowDynamicProperties]
class ReservationAssetExpectedCheckinNotification extends Notification
{
    use Queueable;

    public Reservation $reservation;

    public Asset $asset;

    public function __construct(Reservation $reservation, Asset $asset)
    {
        $this->reservation = $reservation;
        $this->asset = $asset;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $reservation = $this->reservation;
        $asset = $this->asset;

        return (new MailMessage)
            ->subject(trans('reservations.mail.expected_checkin_subject', [
                'tag' => $asset->asset_tag,
            ]))
            ->greeting(trans('reservations.mail.expected_checkin_greeting'))
            ->line(trans('reservations.assets').': '.$asset->asset_tag.($asset->name ? ' ('.$asset->name.')' : ''))
            ->line(trans('reservations.from').': '.Helper::getFormattedDateObject($reservation->start, 'datetime', false))
            ->line(trans('reservations.to').': '.Helper::getFormattedDateObject($reservation->end, 'datetime', false));
    }
}
