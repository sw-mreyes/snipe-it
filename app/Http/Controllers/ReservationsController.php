<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;

class ReservationsController extends Controller
{

    public function index()
    {
        $this->authorize('index', Asset::class);
        return view('reservations/index');
    }


    public function create()
    {
        $this->authorize('index', Asset::class);
        return view('reservations/create');
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
