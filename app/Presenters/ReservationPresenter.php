<?php

namespace App\Presenters;

/**
 * Presenter for the Reservation model (custom fork feature).
 */
class ReservationPresenter extends Presenter
{
    /**
     * JSON column layout for the reservations bootstrap-table.
     */
    public static function dataTableLayout(): string
    {
        $layout = [
            [
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('reservations.name'),
                'visible' => true,
                'formatter' => 'reservationsLinkFormatter',
            ], [
                'field' => 'user',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('reservations.user'),
                'visible' => true,
                'formatter' => 'reservationUserFormatter',
            ], [
                'field' => 'assets',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('reservations.assets'),
                'visible' => true,
                'formatter' => 'reservationAssetsFormatter',
            ], [
                'field' => 'start',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('reservations.start'),
                'visible' => true,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'end',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('reservations.end'),
                'visible' => true,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'status',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('general.status'),
                'visible' => true,
                'formatter' => 'reservationStatusFormatter',
            ], [
                'field' => 'notes',
                'searchable' => true,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('reservations.notes'),
                'visible' => false,
            ], [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
                'visible' => true,
                'formatter' => 'reservationsActionsFormatter',
            ],
        ];

        return json_encode($layout);
    }

    /**
     * Link to the reservation's detail page.
     */
    public function viewUrl(): string
    {
        return route('reservations.show', ['reservation' => $this->id]);
    }

    public function name(): string
    {
        return (string) $this->model->name;
    }

    public function fullName(): string
    {
        return $this->name();
    }
}
