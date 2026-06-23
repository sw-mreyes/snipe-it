<?php

namespace App\Presenters;

/**
 * Presenter for the Reservation model (custom fork feature).
 */
class ReservationPresenter extends Presenter
{
    /**
     * Link to the reservation's detail page.
     */
    public function viewUrl(): string
    {
        return route('reservations.show', ['reservation' => $this->id]);
    }

    /**
     * Display name for the reservation.
     */
    public function name(): string
    {
        return $this->model->name;
    }
}
