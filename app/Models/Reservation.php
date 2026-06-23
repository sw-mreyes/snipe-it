<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

/**
 * Model for asset reservations (custom fork feature).
 *
 * Reserves one or more assets for a user over a future time window, independent
 * of checkout. Backed by the custom, sw_-prefixed tables so it never collides
 * with native Snipe-IT schema.
 *
 * @version v2.0
 */
class Reservation extends SnipeModel
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Custom (fork) table. Prefixed so a future upstream `reservations` table
     * cannot collide with ours.
     *
     * @var string
     */
    protected $table = 'sw_reservations';

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    /**
     * Validation rules. The no-overlap timeframe check is enforced separately
     * in the controller / Form Request (Helper::is_valid_timeframe) because it
     * spans multiple rows and the selected assets.
     *
     * @var array
     */
    public $rules = [
        'name' => 'required|string|max:191',
        'user_id' => 'required|integer|exists:users,id',
        'start' => 'required|date',
        'end' => 'required|date|after:start',
        'notes' => 'nullable|string',
    ];

    /**
     * Whether the model should inject its identifier to the unique validation
     * rules before attempting validation.
     *
     * @var bool
     */
    protected $injectUniqueIdentifier = true;

    use ValidatingTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'user_id',
        'start',
        'end',
        'notes',
    ];

    use Searchable;

    /**
     * The attributes that should be included when searching the model.
     *
     * @var array
     */
    protected $searchableAttributes = [
        'name',
        'notes',
        'start',
        'end',
    ];

    /**
     * The relations and their attributes that should be included when searching the model.
     *
     * @var array
     */
    protected $searchableRelations = [
        'user' => ['first_name', 'last_name', 'username'],
    ];

    /**
     * The user the assets are reserved for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * The assets included in this reservation.
     *
     * Pivot table is pinned explicitly: Eloquent's default convention would be
     * `asset_reservation`, but our custom pivot is `sw_asset_reservation`.
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Asset::class, 'sw_asset_reservation', 'reservation_id', 'asset_id')
            ->withTimestamps();
    }
}
