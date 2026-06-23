<?php

use App\Http\Controllers\ReservationsController;
use Illuminate\Support\Facades\Route;

/*
 * Reservations (custom fork feature)
 */
Route::group(['prefix' => 'reservations', 'middleware' => ['auth']], function () {
    // Must be declared before the resource routes so it is not captured by the
    // `reservations/{reservation}` show route.
    Route::get('calendar', [ReservationsController::class, 'calendar'])->name('reservations.calendar');
});

Route::resource('reservations', ReservationsController::class, [
    'middleware' => ['auth'],
]);
