<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'reservations';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'start',
        'end',
    ];

    protected $guarded = [
        'note',
    ];

    public function user()
    {
        return $this->belongsTo('\App\Models\User');
    }

    public function assets(){
        return $this->hasMany('\App\Models\Asset');
    }

}
