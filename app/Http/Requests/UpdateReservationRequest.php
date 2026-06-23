<?php

namespace App\Http\Requests;

use App\Models\Reservation;

/**
 * Validates updates to a reservation (custom fork feature).
 *
 * Identical to creation except the reservation being edited is excluded from
 * the overlap check so it never conflicts with itself.
 */
class UpdateReservationRequest extends StoreReservationRequest
{
    protected function excludedReservationId(): ?int
    {
        $routeReservation = $this->route('reservation');

        if ($routeReservation instanceof Reservation) {
            return $routeReservation->id;
        }

        return $routeReservation !== null ? (int) $routeReservation : null;
    }
}
