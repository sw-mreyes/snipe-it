<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
 * Global cross-entity search (custom fork feature)
 */
Route::get('search', [SearchController::class, 'globalSearch'])
    ->middleware('auth')
    ->name('search');
