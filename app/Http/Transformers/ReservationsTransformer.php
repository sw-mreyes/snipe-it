<?php

namespace App\Http\Transformers;

use \App\Models\Reservation;

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

    public function transformReservationsCalendar($reservations, $total)
    {
        $array = array();
        foreach ($reservations as $res) {
            $array[] = [
                'id' => (int) $res->id,
                'title' => $res->name,
                'category' => 'time',
                'start' => explode(' ', $res->start . '')[0],
                'end' => explode(' ', $res->end . '')[0],
                'body' => $res->notes,
            ];
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
                'username' => $res->user->username,
                'full_name' => $res->user->name
            ],
            'start' => $res->start,
            'end' => $res->end,
            'notes' => $res->notes,
            'created' => $res->created_at,
            'updated' => $res->updated_at,
            'assets' => (int) count($res->assets)
        ];
        return $array;
    }
}
