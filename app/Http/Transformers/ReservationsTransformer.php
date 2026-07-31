<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Reservation;
use App\Services\AssetLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Transforms reservations for the API (custom fork feature).
 *
 * Each row carries dates twice on purpose: display-formatted objects for the
 * bootstrap-table list, and raw ISO 8601 for the calendar and the client-side
 * conflict checker.
 */
class ReservationsTransformer
{
    public function transformReservations(Collection $reservations, $total)
    {
        $array = [];

        foreach ($reservations as $reservation) {
            $array[] = self::transformReservation($reservation);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformReservation(Reservation $reservation): array
    {
        $assets = [];

        foreach ($reservation->assets as $asset) {
            $assets[] = [
                'id' => (int) $asset->id,
                'asset_tag' => e($asset->asset_tag),
                'name' => e($asset->name),
                // What the UI should actually print: name, else tag, else #id.
                'label' => e(AssetLabel::for($asset)),
            ];
        }

        return [
            'id' => (int) $reservation->id,
            'name' => e($reservation->name),
            'user' => $reservation->user ? [
                'id' => (int) $reservation->user->id,
                'name' => e($reservation->user->present()->fullName),
            ] : null,
            'assets' => $assets,
            'start' => Helper::getFormattedDateObject($reservation->start, 'datetime'),
            'end' => Helper::getFormattedDateObject($reservation->end, 'datetime'),
            'start_iso' => $reservation->start?->toIso8601String(),
            'end_iso' => $reservation->end?->toIso8601String(),
            'status' => $this->statusFor($reservation),
            'notes' => ($reservation->notes != '') ? e($reservation->notes) : null,
            'created_at' => Helper::getFormattedDateObject($reservation->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($reservation->updated_at, 'datetime'),
            'available_actions' => [
                'update' => Gate::allows('checkout', Asset::class),
                'delete' => Gate::allows('checkout', Asset::class),
            ],
        ];
    }

    /**
     * Where the reservation sits relative to now, so the UI can badge it
     * without re-deriving the comparison in JavaScript.
     */
    private function statusFor(Reservation $reservation): string
    {
        if ($reservation->end && $reservation->end->isPast()) {
            return 'past';
        }

        if ($reservation->start && $reservation->start->isFuture()) {
            return 'upcoming';
        }

        return 'active';
    }
}
