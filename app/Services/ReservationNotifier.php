<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReservationAssetExpectedCheckinNotification;
use App\Notifications\ReservationPlacedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatches reservation notifications (custom fork feature):
 *  - mail to the reserving user,
 *  - a webhook message to the configured team channel,
 *  - mail to whoever currently holds each reserved asset.
 *
 * Webhook delivery mirrors the native checkout notifications (channel selection
 * + Notification::route), and never throws out to the caller: a misconfigured
 * webhook must not break placing a reservation.
 */
class ReservationNotifier
{
    public function notifyPlaced(Reservation $reservation): void
    {
        $reservation->loadMissing('user', 'assets');

        // 1. Mail the reserving user.
        if ($reservation->user) {
            $reservation->user->notify(new ReservationPlacedNotification($reservation));
        }

        // 2. Post to the team webhook, if configured.
        $this->sendWebhook($reservation);

        // 3. Mail whoever currently holds each reserved asset.
        foreach ($reservation->assets as $asset) {
            $responsible = $this->findResponsibleUser($asset);
            if ($responsible) {
                $responsible->notify(new ReservationAssetExpectedCheckinNotification($reservation, $asset));
            }
        }
    }

    /**
     * Resolve the user currently responsible for an asset:
     *  - not checked out         -> null
     *  - checked out to a user   -> that user
     *  - checked out to location -> that location's manager, walking up parents
     *  - checked out to an asset -> recurse into that asset's holder
     */
    public function findResponsibleUser(Asset $asset): ?User
    {
        if ($asset->availableForCheckout()) {
            return null;
        }

        $target = $asset->assignedTo;

        if (! $target) {
            return null;
        }

        if ($asset->assigned_type === User::class) {
            return $target;
        }

        if ($asset->assigned_type === Location::class) {
            $location = $target;
            while ($location && ! $location->manager) {
                $location = $location->parent;
            }

            return $location?->manager;
        }

        if ($asset->assigned_type === Asset::class) {
            return $this->findResponsibleUser($target);
        }

        return null;
    }

    private function sendWebhook(Reservation $reservation): void
    {
        $settings = Setting::getSettings();

        if (! $settings->webhook_endpoint || ! $settings->webhook_selected) {
            return;
        }

        $channel = ($settings->webhook_selected === 'slack' || $settings->webhook_selected === 'general')
            ? 'slack'
            : $settings->webhook_selected;

        try {
            Notification::route($channel, $settings->webhook_endpoint)
                ->notify(new ReservationPlacedNotification($reservation));
        } catch (\Throwable $e) {
            // A bad webhook config must never block placing a reservation.
            Log::warning('Reservation webhook notification failed: '.$e->getMessage());
        }
    }
}
