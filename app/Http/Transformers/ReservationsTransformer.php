<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

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

    public function transformReservation(Reservation $reservation)
    {
        $assets = [];
        foreach ($reservation->assets as $asset) {
            $assets[] = [
                'id' => (int) $asset->id,
                'asset_tag' => e($asset->asset_tag),
                'name' => e($asset->name),
            ];
        }

        $array = [
            'id' => (int) $reservation->id,
            'name' => e($reservation->name),
            'user' => ($reservation->user) ? [
                'id' => (int) $reservation->user->id,
                'name' => e($reservation->user->present()->fullName),
            ] : null,
            'assets' => $assets,
            // Display-formatted objects (used by bootstrap-table list view)…
            'start' => Helper::getFormattedDateObject($reservation->start, 'datetime'),
            'end' => Helper::getFormattedDateObject($reservation->end, 'datetime'),
            // …and raw ISO 8601 (consumed by the FullCalendar view).
            'start_iso' => $reservation->start?->toIso8601String(),
            'end_iso' => $reservation->end?->toIso8601String(),
            'notes' => ($reservation->notes != '') ? e($reservation->notes) : null,
            'created_at' => Helper::getFormattedDateObject($reservation->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($reservation->updated_at, 'datetime'),
        ];

        $permissions_array['available_actions'] = [
            'update' => Gate::allows('checkout', Asset::class),
            'delete' => Gate::allows('checkout', Asset::class),
        ];

        $array += $permissions_array;

        return $array;
    }
}
