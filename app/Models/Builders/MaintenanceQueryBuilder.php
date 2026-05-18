<?php

namespace App\Models\Builders;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceQueryBuilder extends Builder
{
    public function dueForCompletion(Setting $settings): static
    {
        $interval = (int) ($settings->audit_warning_days ?? 0);
        $today = Carbon::now();

        return $this->whereNotNull('maintenances.completion_date')
            ->whereNull('maintenances.completed_at')
            ->whereBetween('maintenances.completion_date', [
                $today->format('Y-m-d'),
                $today->copy()->addDays($interval)->format('Y-m-d'),
            ]);
    }

    public function overdueForCompletion(): static
    {
        return $this->whereNotNull('maintenances.completion_date')
            ->whereNull('maintenances.completed_at')
            ->where('maintenances.completion_date', '<', Carbon::now()->format('Y-m-d'));
    }

    public function dueOrOverdueForCompletion(Setting $settings): static
    {
        return $this->where(fn ($q) => $q->overdueForCompletion())
            ->orWhere(fn ($q) => $q->dueForCompletion($settings));
    }
}
