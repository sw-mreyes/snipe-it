<?php

namespace App\Services\Reservations;

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
 *
 *  1. mail to the reserving user,
 *  2. a message to the configured team webhook,
 *  3. mail to whoever currently holds each reserved asset.
 *
 * Webhook delivery mirrors the native checkout notifications (channel selection
 * plus Notification::route) and never throws out to the caller: a misconfigured
 * webhook must not stop a reservation being placed.
 */
class ReservationNotifier
{
    public function notifyPlaced(Reservation $reservation): void
    {
        $reservation->loadMissing('user', 'assets');

        if ($reservation->user) {
            $this->attempt(
                fn () => $reservation->user->notify(new ReservationPlacedNotification($reservation)),
                'reserving user'
            );
        }

        $this->sendWebhook($reservation);

        foreach ($reservation->assets as $asset) {
            if ($responsible = $this->findResponsibleUser($asset)) {
                $this->attempt(
                    fn () => $responsible->notify(new ReservationAssetExpectedCheckinNotification($reservation, $asset)),
                    'asset holder for '.$asset->asset_tag
                );
            }
        }
    }

    /**
     * Send a notification without letting delivery problems escape.
     *
     * An unreachable or misconfigured mail server must not fail the request:
     * the reservation is already saved by this point, so throwing here would
     * show the user an error for something that actually succeeded.
     */
    private function attempt(callable $send, string $recipient): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Reservation notification to '.$recipient.' failed: '.$e->getMessage());
        }
    }

    /**
     * Resolve the user currently responsible for an asset:
     *
     *   not checked out         -> nobody
     *   checked out to a user   -> that user
     *   checked out to location -> that location's manager, walking up parents
     *   checked out to an asset -> recurse into that asset's holder
     */
    public function findResponsibleUser(Asset $asset, int $depth = 0): ?User
    {
        // Guard against a cyclic asset->asset assignment chain.
        if ($depth > 10 || $asset->availableForCheckout()) {
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
            return $this->managerOf($target);
        }

        if ($asset->assigned_type === Asset::class) {
            return $this->findResponsibleUser($target, $depth + 1);
        }

        return null;
    }

    /**
     * The nearest manager at or above a location.
     */
    private function managerOf(?Location $location): ?User
    {
        $seen = [];

        while ($location && ! $location->manager) {
            if (in_array($location->id, $seen, true)) {
                return null;
            }

            $seen[] = $location->id;
            $location = $location->parent;
        }

        return $location?->manager;
    }

    private function sendWebhook(Reservation $reservation): void
    {
        $settings = Setting::getSettings();

        if (! $settings->webhook_endpoint || ! $settings->webhook_selected) {
            return;
        }

        $channel = in_array($settings->webhook_selected, ['slack', 'general'], true)
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
