<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
 * Global cross-entity search (custom fork feature)
 *
 * The navbar search box posts here with ?search=<term>.
 */
Route::get('search', [SearchController::class, 'index'])
    ->middleware(['auth'])
    ->name('search')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('home')
        ->push(trans('global-search.results'), route('search')));
