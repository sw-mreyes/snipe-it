<?php

namespace App\Http\Requests;

/**
 * Validates an update to a reservation (custom fork feature).
 *
 * Identical to the store request except that the reservation being edited is
 * excluded from the overlap check, so it never conflicts with itself.
 */
class UpdateReservationRequest extends StoreReservationRequest
{
    protected function excludedReservationId(): ?int
    {
        $reservation = $this->route('reservation');

        return $reservation ? (int) (is_object($reservation) ? $reservation->id : $reservation) : null;
    }
}
