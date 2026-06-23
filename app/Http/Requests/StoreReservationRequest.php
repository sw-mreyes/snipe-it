<?php

namespace App\Http\Requests;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Gate;

/**
 * Validates creation of a reservation (custom fork feature).
 *
 * Authorization reuses the asset `checkout` permission per project decision
 * (no dedicated reservation permission set).
 */
class StoreReservationRequest extends Request
{
    public function authorize(): bool
    {
        return Gate::allows('checkout', Asset::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'user_id' => 'required|integer|exists:users,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'notes' => 'nullable|string',
            'assets' => 'required|array|min:1',
            'assets.*' => 'integer|exists:assets,id',
        ];
    }

    /**
     * The reservation id to exclude from the overlap check. Null on create;
     * the reservation being edited on update.
     */
    protected function excludedReservationId(): ?int
    {
        return null;
    }

    /**
     * Reject windows that overlap an existing reservation for any selected
     * asset. Runs only once the per-field rules above have passed, so we know
     * start/end/assets are present and well-formed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['start', 'end', 'assets'])) {
                return;
            }

            $valid = Helper::is_valid_timeframe(
                $this->input('start'),
                $this->input('end'),
                (array) $this->input('assets', []),
                $this->excludedReservationId(),
            );

            if (! $valid) {
                $validator->errors()->add('assets', trans('reservations.invalid_timeframe'));
            }
        });
    }
}
