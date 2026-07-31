<?php

use App\Http\Controllers\ReservationsController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
 * Asset reservations (custom fork feature)
 */
Route::group(['middleware' => ['auth']], function () {

    // Declared before the resource so it is not captured by the
    // reservations/{reservation} show route.
    Route::get('reservations/calendar', [ReservationsController::class, 'calendar'])
        ->name('reservations.calendar')
        ->breadcrumbs(fn (Trail $trail) => $trail
            ->parent('reservations.index')
            ->push(trans('reservations.calendar'), route('reservations.calendar')));

    Route::resource('reservations', ReservationsController::class)
        ->except(['index', 'create', 'show', 'edit']);

    Route::get('reservations', [ReservationsController::class, 'index'])
        ->name('reservations.index')
        ->breadcrumbs(fn (Trail $trail) => $trail
            ->parent('home')
            ->push(trans('reservations.reservations'), route('reservations.index')));

    Route::get('reservations/create', [ReservationsController::class, 'create'])
        ->name('reservations.create')
        ->breadcrumbs(fn (Trail $trail) => $trail
            ->parent('reservations.index')
            ->push(trans('reservations.create'), route('reservations.create')));

    Route::get('reservations/{reservation}', [ReservationsController::class, 'show'])
        ->name('reservations.show')
        ->breadcrumbs(fn (Trail $trail, $reservation) => $trail
            ->parent('reservations.index')
            ->push($reservation->name, route('reservations.show', ['reservation' => $reservation])));

    Route::get('reservations/{reservation}/edit', [ReservationsController::class, 'edit'])
        ->name('reservations.edit')
        ->breadcrumbs(fn (Trail $trail, $reservation) => $trail
            ->parent('reservations.show', $reservation)
            ->push(trans('reservations.update'), route('reservations.edit', ['reservation' => $reservation])));
});
