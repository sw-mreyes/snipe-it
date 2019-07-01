<?php


Route::group(['prefix' => 'reservations', 'middleware' => ['auth']], function () {
    Route::get('index',                  ['as' => 'reservations.index',  'uses' => 'ReservationsController@index']);
    Route::get('create',                 ['as' => 'reservations.create', 'uses' => 'ReservationsController@create']);
    Route::get('{reservationID}view',    ['as' => 'reservations.view',   'uses' => 'ReservationsController@view']);
    Route::get('{reservationID}/edit',   ['as' => 'reservations.edit',   'uses' => 'ReservationsController@edit']);
    Route::get('{reservationID}/delete', ['as' => 'reservations.delete', 'uses' => 'ReservationsController@delete']);
});

Route::resource('reservations', 'ReservationsController', [
    'middleware' => ['auth'],
    'parameters' => ['reservation' => 'reservation_id']
]);
