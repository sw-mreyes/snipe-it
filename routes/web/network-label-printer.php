<?php

use App\Http\Controllers\NetworkLabelPrinterController;
use Illuminate\Support\Facades\Route;

/*
 * Network label printing (custom fork feature)
 *
 * Sends a label to an external print-server, selecting the printer by the
 * item's location (or an explicit ?printer=<configured-name>).
 */
Route::group(['prefix' => 'network-label', 'middleware' => ['auth']], function () {
    Route::get('asset/{asset}', [NetworkLabelPrinterController::class, 'printAssetLabel'])->name('network-label.asset');
    Route::get('accessory/{accessory}', [NetworkLabelPrinterController::class, 'printAccessoryLabel'])->name('network-label.accessory');
    Route::get('component/{component}', [NetworkLabelPrinterController::class, 'printComponentLabel'])->name('network-label.component');
    Route::get('consumable/{consumable}', [NetworkLabelPrinterController::class, 'printConsumableLabel'])->name('network-label.consumable');
    Route::get('location/{location}', [NetworkLabelPrinterController::class, 'printLocationLabel'])->name('network-label.location');
});
