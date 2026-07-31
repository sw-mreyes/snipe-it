<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Gate;

/**
 * Validates creation of a reservation (custom fork feature).
 *
 * Authorization reuses the asset `checkout` permission — the project decision
 * is that reservations get no permission set of their own.
 */
class StoreReservationRequest extends Request
{
    public function authorize(): bool
    {
        return Gate::allows('checkout', Asset::class);
    }

    /**
     * `datetime-local` inputs submit "2026-08-01T09:00". Normalize the T to a
     * space so the value is a plain datetime for validation and storage.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'start' => $this->normalizeDateTime($this->input('start')),
            'end' => $this->normalizeDateTime($this->input('end')),
        ], fn ($value) => $value !== null));
    }

    private function normalizeDateTime($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return str_replace('T', ' ', trim($value));
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
     * The reservation to exclude from the overlap check: null on create, the
     * reservation being edited on update.
     */
    protected function excludedReservationId(): ?int
    {
        return null;
    }

    /**
     * Reject a window that overlaps an existing reservation for any selected
     * asset. Runs only once the per-field rules have passed, so start/end/assets
     * are known to be present and well-formed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['start', 'end', 'assets'])) {
                return;
            }

            $conflict = Reservation::conflictsExist(
                (array) $this->input('assets', []),
                $this->input('start'),
                $this->input('end'),
                $this->excludedReservationId(),
            );

            if ($conflict) {
                $validator->errors()->add('assets', trans('reservations.invalid_timeframe'));
            }
        });
    }
}
