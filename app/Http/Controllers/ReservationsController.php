<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;
use stdClass;
use App\Models\Reservation;
use View;

/**TODO: REMOVE */

class ReservationsController extends Controller
{

    public function index()
    {
        $this->authorize('index', Asset::class);
        return view('reservations/index');
    }


    public function create()
    {
        //$this->authorize('create', Asset::class);
        $view = View::make('reservations/edit')
            ->with('item', new Reservation);
        return $view;
    }

    public function view($reservationID)
    {
        $this->authorize('index', Asset::class);
        return view('reservations/view', [
            'id' => $reservationID,
        ]);
    }

    public function edit($reservationID)
    {
        $this->authorize('index', Asset::class);
        return view('reservations/edit', [
            'id' => $reservationID,
        ]);
    }

    public function delete($reservationID)
    {
        $this->authorize('index', Asset::class);
        return redirect('reservations/index')->with('success', 'Delete message');
    }
}
