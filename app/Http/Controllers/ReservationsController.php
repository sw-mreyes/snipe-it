<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;
use stdClass;
use View;
use Illuminate\Http\Request;

use App\Models\Reservation;
use App\Models\Asset;
use App\Models\User;


class ReservationsController extends Controller
{

    private function _authorize(){
        $this->authorize('index', Asset::class);
    }

    public function index()
    {
        $this->_authorize();
        return view('reservations/index');
    }


    public function create()
    {
        $this->_authorize();
        $view = View::make('reservations/edit')
            ->with('item', new Reservation);
        return $view;
    }

    public function store(Request $request)
    {
        $this->_authorize();

        $user = User::find($request->input('user'));

        $res = new Reservation();
        $res->name  = $request->input('name');
        $res->start = $request->input('start');
        $res->end   = $request->input('end');
        $res->notes = $request->input('notes');
        $res->user()->associate($user);
        
        $res->save();
        
        foreach ($request->input('assets') as $assetID) {
            $res->assets()->save(Asset::find($assetID));
        }        
        return redirect()->back();
    }

    public function view($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return view('reservations/view', [
            'id' => $reservationID,
        ]);
    }

    public function edit($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return view('reservations/edit', [
            'id' => $reservationID,
        ]);
    }

    public function delete($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return redirect('reservations/index')->with('success', 'Delete message');
    }
}
