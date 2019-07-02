<?php

namespace App\Http\Transformers;

use \App\Models\Reservation;
use \App\Http\Transformers\AssetTransformer;

class ReservationsTransformer
{
    public function transformReservations($reservations, $total)
    {
        $array = array();
        foreach ($reservations as $res) {
            $array[] = self::transformReservation($res);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformReservation(Reservation $res)
    {
        $array = [
            'id' => (int) $res->id,
            'name' => $res->name,
            'user' => [
                'id' => $res->user->id,
                'name' => $res->user->name,
            ],
            'start' => $res->start,
            'end' => $res->end,
            'notes' => $res->notes,
            'created' => $res->created_at,
            'updated' => $res->updated_at,
            'assets' => (new AssetTransformer())->transformAssets($res->assets)
        ];
        return $array;
    }
}
