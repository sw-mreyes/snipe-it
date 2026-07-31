<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

/**
 * Asset reservations (custom fork feature).
 *
 * Books one or more assets for a user over a time window, independently of
 * checkout. A reservation never blocks anything: it records intent and warns at
 * checkout time.
 *
 * Backed by sw_-prefixed tables so a future upstream `reservations` table
 * cannot collide with ours.
 */
class Reservation extends SnipeModel
{
    use HasFactory;
    use Presentable;
    use Searchable;
    use SoftDeletes;
    use ValidatingTrait;

    protected $presenter = \App\Presenters\ReservationPresenter::class;

    /**
     * Custom (fork) table, prefixed to avoid any future upstream collision.
     */
    protected $table = 'sw_reservations';

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    /**
     * The no-overlap check spans multiple rows and the selected assets, so it
     * lives in the Form Request rather than here.
     */
    public $rules = [
        'name' => 'required|string|max:191',
        'user_id' => 'required|integer|exists:users,id',
        'start' => 'required|date',
        'end' => 'required|date|after:start',
        'notes' => 'nullable|string',
    ];

    protected $injectUniqueIdentifier = true;

    protected $fillable = [
        'name',
        'user_id',
        'start',
        'end',
        'notes',
    ];

    protected $searchableAttributes = [
        'name',
        'notes',
        'start',
        'end',
    ];

    protected $searchableRelations = [
        'user' => ['first_name', 'last_name', 'username'],
    ];

    /**
     * The user the assets are reserved for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The assets included in this reservation.
     *
     * The pivot table is pinned explicitly: Eloquent's convention would guess
     * `asset_reservation`, but ours is `sw_asset_reservation`.
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'sw_asset_reservation', 'reservation_id', 'asset_id')
            ->withTimestamps();
    }

    /**
     * Reservations that include the given asset.
     */
    public function scopeForAsset(Builder $query, $assetId): Builder
    {
        return $query->whereHas('assets', fn ($q) => $q->where('assets.id', $assetId));
    }

    /**
     * Reservations that have not ended yet.
     */
    public function scopeCurrentAndUpcoming(Builder $query): Builder
    {
        return $query->where('end', '>=', now());
    }

    /**
     * Whether the asset has any current or upcoming reservation. Used to warn —
     * never to block — at checkout time.
     */
    public static function assetHasUpcomingReservation($assetId): bool
    {
        return static::forAsset($assetId)->currentAndUpcoming()->exists();
    }

    /**
     * The soonest current or upcoming reservation for an asset, or null.
     */
    public static function nextReservationFor($assetId): ?self
    {
        return static::with('user')
            ->forAsset($assetId)
            ->currentAndUpcoming()
            ->orderBy('start')
            ->first();
    }

    /**
     * Whether any of the given assets already has a reservation overlapping
     * [$start, $end].
     *
     * Two windows overlap iff start1 <= end2 AND start2 <= end1. Soft-deleted
     * reservations are excluded by the global scope. Pass $excludeId to ignore
     * the reservation being edited, so it never conflicts with itself.
     *
     * @param  array<int, int|string>  $assetIds
     */
    public static function conflictsExist(array $assetIds, $start, $end, ?int $excludeId = null): bool
    {
        if (empty($assetIds)) {
            return false;
        }

        return static::query()
            ->when($excludeId, fn (Builder $query) => $query->whereKeyNot($excludeId))
            ->whereHas('assets', fn (Builder $query) => $query->whereIn('assets.id', $assetIds))
            ->where('start', '<=', $end)
            ->where('end', '>=', $start)
            ->exists();
    }
}
