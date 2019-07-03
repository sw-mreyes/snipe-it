<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;
use App\Models\Setting;

class ReservationAssetExpectedCheckinNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($res, $asset, $user)
    {
        $this->reservation = $res;
        $this->asset = $asset;
        $this->user = $user;
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
        $asset = $this->asset;
        $user = $this->user;

        return (new SlackMessage)
            ->from($settings->slack_botname, ':heart:')
            ->to(/*'@'.$user->username*/$settings->slack_channel)
            ->content('**Hey there @' . $user->username . " :sunglasses: **\n**A reservation was placed for one of your Assets :monkas: **")
            ->attachment(function ($att) use ($res, $asset) {
                $start = \App\Helpers\Helper::getFormattedDateObject($res->start, 'datetime', false);
                $end = \App\Helpers\Helper::getFormattedDateObject($res->end, 'datetime', false);
                $att->color = "#961111";
                $att->title($asset->present()->name, $asset->present()->viewUrl());
                $att->fields = [
                    'From' => $start,
                    'To' => $end
                ];
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
