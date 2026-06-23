<?php

namespace App\Notifications;

use App\Helpers\Helper;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\GoogleChat\Card;
use NotificationChannels\GoogleChat\GoogleChatChannel;
use NotificationChannels\GoogleChat\GoogleChatMessage;
use NotificationChannels\GoogleChat\Section;
use NotificationChannels\GoogleChat\Widgets\KeyValue;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsChannel;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsMessage;

/**
 * Sent when a reservation is placed (custom fork feature).
 *
 * Delivered as mail to the reserving user, and as a webhook message to the
 * configured team channel (routed via Notification::route in the service that
 * dispatches this). Channel selection mirrors the native checkout notifications.
 */
#[\AllowDynamicProperties]
class ReservationPlacedNotification extends Notification
{
    use Queueable;

    public Reservation $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
        $this->settings = Setting::getSettings();
    }

    public function via($notifiable): array
    {
        // A real user is notified by mail; the anonymous routed notifiable used
        // for the team webhook gets the configured webhook channel.
        if ($notifiable instanceof User) {
            return ['mail'];
        }

        $settings = Setting::getSettings();

        if ($settings->webhook_selected === 'google' && $settings->webhook_endpoint) {
            return [GoogleChatChannel::class];
        }

        if ($settings->webhook_selected === 'microsoft' && $settings->webhook_endpoint) {
            return [MicrosoftTeamsChannel::class];
        }

        if (($settings->webhook_selected === 'slack' || $settings->webhook_selected === 'general') && $settings->webhook_endpoint) {
            return [SlackWebhookChannel::class];
        }

        return [];
    }

    private function assetLines(): string
    {
        $lines = '';
        foreach ($this->reservation->assets as $asset) {
            $lines .= '• '.$asset->asset_tag.($asset->name ? ' ('.$asset->name.')' : '')."\n";
        }

        return $lines;
    }

    public function toMail(): MailMessage
    {
        $reservation = $this->reservation;

        $mail = (new MailMessage)
            ->subject(trans('reservations.mail.placed_subject', ['name' => $reservation->name]))
            ->greeting(trans('reservations.mail.placed_greeting'))
            ->line(trans('reservations.from').': '.Helper::getFormattedDateObject($reservation->start, 'datetime', false))
            ->line(trans('reservations.to').': '.Helper::getFormattedDateObject($reservation->end, 'datetime', false));

        foreach ($reservation->assets as $asset) {
            $mail->line('• '.$asset->asset_tag.($asset->name ? ' ('.$asset->name.')' : ''));
        }

        return $mail;
    }

    public function toSlack(): SlackMessage
    {
        $reservation = $this->reservation;
        $botname = $this->settings->webhook_botname ?: 'Snipe-Bot';
        $channel = $this->settings->webhook_channel ?: '';

        $fields = [
            trans('reservations.user') => optional($reservation->user)->present()->fullName ?? '',
            trans('reservations.from') => Helper::getFormattedDateObject($reservation->start, 'datetime', false),
            trans('reservations.to') => Helper::getFormattedDateObject($reservation->end, 'datetime', false),
        ];

        return (new SlackMessage)
            ->content(':calendar: '.trans('reservations.mail.placed_subject', ['name' => $reservation->name]))
            ->from($botname)
            ->to($channel)
            ->attachment(function ($attachment) use ($reservation, $fields) {
                $attachment->title($reservation->name)
                    ->fields($fields)
                    ->content($this->assetLines());
            });
    }

    public function toMicrosoftTeams()
    {
        $reservation = $this->reservation;

        if (! Str::contains($this->settings->webhook_endpoint, 'workflows')) {
            return MicrosoftTeamsMessage::create()
                ->to($this->settings->webhook_endpoint)
                ->type('success')
                ->title(trans('reservations.mail.placed_subject', ['name' => $reservation->name]))
                ->addStartGroupToSection('activityText')
                ->fact(trans('reservations.user'), optional($reservation->user)->present()->fullName ?? '')
                ->fact(trans('reservations.from'), Helper::getFormattedDateObject($reservation->start, 'datetime', false))
                ->fact(trans('reservations.to'), Helper::getFormattedDateObject($reservation->end, 'datetime', false));
        }

        $message = trans('reservations.mail.placed_subject', ['name' => $reservation->name]);
        $details = [
            trans('reservations.user') => optional($reservation->user)->present()->fullName ?? '',
            trans('reservations.from') => Helper::getFormattedDateObject($reservation->start, 'datetime', false),
            trans('reservations.to') => Helper::getFormattedDateObject($reservation->end, 'datetime', false),
        ];

        return [$message, $details];
    }

    public function toGoogleChat()
    {
        $reservation = $this->reservation;

        return GoogleChatMessage::create()
            ->to($this->settings->webhook_endpoint)
            ->card(
                Card::create()
                    ->header(
                        '<strong>'.trans('reservations.mail.placed_subject', ['name' => $reservation->name]).'</strong>',
                        optional($reservation->user)->present()->fullName ?? '',
                    )
                    ->section(
                        Section::create(
                            KeyValue::create(
                                trans('reservations.from'),
                                Helper::getFormattedDateObject($reservation->start, 'datetime', false),
                                Helper::getFormattedDateObject($reservation->end, 'datetime', false),
                            )
                        )
                    )
            );
    }
}
