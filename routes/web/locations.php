<?php

# Location Management / added so we dont have to copy the f'in label printer controller 
# why is locations the only thing thats not using web routes?
# .. ?!
Route::group([ 'prefix' => 'locations', 'middleware' => ['auth'] ], function () {

    Route::get( '{location}/printlabel',  [
        'as' => 'locations.printlabel',
        'uses' => 'LabelPrinterController@printLocationLabel'
    ]);
});

Route::resource('locations', 'LocationsController', [
    'middleware' => ['auth'],
    'parameters' => ['location' => 'location_id']
]);
