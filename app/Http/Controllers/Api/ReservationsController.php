<?php

namespace App\Http\Controllers\Api;

use \App\Models\Reservation;
use Illuminate\Http\Request;
use \App\Http\Transformers\ReservationsTransformer;

class ReservationsController extends Controller
{
    public function index(Request $request)
    {

        $reservations = Reservation::all();
        $offset = (($reservations) && (request('offset') > $reservations->count())) ? 0 : request('offset', 0);
        $limit = $request->input('limit', 50);
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $total = $reservations->count();
        $reservations->skip($offset)->take($limit)->orderBy('id', $order)->get();
        return (new ReservationsTransformer)->transformReservations($reservations, $total);
    }
}
