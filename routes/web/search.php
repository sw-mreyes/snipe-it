<?php
/*
|--------------------------------------------------------------------------
| Pagewide Search Route(s)
|--------------------------------------------------------------------------
|
|
*/
Route::group(
    ['prefix' => 'search','middleware' => ['auth']],
    function () {
        Route::get('/global', [
            'as'   => 'search/global',
            'uses' => 'SearchController@globalSearch'
        ]);
    }
);