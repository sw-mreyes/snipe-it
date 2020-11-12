<?php


    # Consumables
    Route::group([ 'prefix' => 'consumables', 'middleware' => ['auth']], function () {
        Route::get(
            '{consumableID}/checkout',
            [ 'as' => 'checkout/consumable','uses' => 'Consumables\ConsumableCheckoutController@create' ]
        );
        Route::post(
            '{consumableID}/checkout',
            [ 'as' => 'checkout/consumable', 'uses' => 'Consumables\ConsumableCheckoutController@store' ]
        );
        Route::get(
            '{consumableID}/printlabel',
            [ 'as' => 'consumables.printlabel', 'uses' => 'LabelPrinterController@printConsumableLabel' ]
        );
    });

    Route::resource('consumables', 'Consumables\ConsumablesController', [
        'middleware' => ['auth'],
        'parameters' => ['consumable' => 'consumable_id']
    ]);
