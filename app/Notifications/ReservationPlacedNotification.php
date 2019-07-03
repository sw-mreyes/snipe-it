<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;
use App\Models\Setting;

class ReservationPlacedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return [
            //
        ];
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {
        $settings = Setting::getSettings();
        $res = $this->reservation;

        $asset_list = '';
        foreach ($this->reservation->assets as $asset) {
            $asset_list = $asset_list . '* <' . $asset->present()->viewUrl() . '|' . $asset->present()->fullName . ">\n";
        }

        return (new SlackMessage)
            ->from($settings->slack_botname, ':heart:')
            ->to($settings->slack_channel)
            ->content('**Reservation Placed**')
            ->attachment(function ($att) use ($res, $asset_list) {
                $start = \App\Helpers\Helper::getFormattedDateObject($res->start, 'datetime', false);
                $end = \App\Helpers\Helper::getFormattedDateObject($res->end, 'datetime', false);

                $att->fallback = 'Reservation for ' . count($res->assets) . ' Assets placed by ' . $res->user->present()->fullName;
                $att->color = "#C4C4C4";
                $att->title($res->name, url('/') . 'reservations/' . $res->id);
                $att->author = $res->user->present()->fullName; //, $res->user->present()->viewUrl());
                $att->fields = [
                    'By' => '<' . $res->user->present()->viewUrl() . '|' . $res->user->present()->fullName . '>'
                ];
                $att->field(function ($fld) use ($asset_list) {
                    $fld->title('Assets');
                    $fld->long();
                    $fld->content($asset_list);
                });
                $att->field(function ($fld) use ($start) {
                    $fld->title('From');
                    $fld->content($start);
                });
                $att->field(function ($fld) use ($end) {
                    $fld->title('To');
                    $fld->content($end);
                });
            });
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
