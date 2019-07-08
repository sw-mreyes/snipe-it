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
        // This should propably go into the blade / js / css
        $colors = [
            'sienna', 'MediumPurple', 'cyan', 'orange', 'teal',
            'fuchsia', 'olive', 'lightblue', 'DarkSlateBlue', 'DarkSlateGray'
        ];
        $color_index = 0;

        $array = array();
        foreach ($reservations as $res) {
            $array[] = [
                'id' => (int) $res->id,
                'title' => $res->name,
                'category' => 'time',
                'start' => explode(' ', $res->start . '')[0],
                'end' => explode(' ', $res->end . '')[0],
                'body' => $res->notes,
                'bgColor' => $colors[$color_index]
            ];
            $color_index = $color_index + 1;
            if ($color_index >= count($colors)) {
                $color_index = 0;
            }
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
