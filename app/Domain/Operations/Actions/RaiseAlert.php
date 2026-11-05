<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertStatus;
use App\Domain\Operations\Enums\AlertType;
use App\Domain\Operations\Models\Alert;
use Illuminate\Database\Eloquent\Model;

class RaiseAlert
{
    /**
     * Open (or refresh) an alert. Alerts are de-duplicated by type + subject so
     * a flapping device does not create thousands of rows.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        AlertType $type,
        string $title,
        ?string $message = null,
        AlertSeverity $severity = AlertSeverity::Warning,
        ?Model $subject = null,
        array $metadata = [],
    ): Alert {
        $alert = Alert::query()->firstOrNew([
            'type' => $type->value,
            'alertable_type' => $subject?->getMorphClass(),
            'alertable_id' => $subject?->getKey(),
            'status' => AlertStatus::Open->value,
        ]);

        $alert->fill([
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata ?: null,
            'triggered_at' => $alert->exists ? $alert->triggered_at : now(),
        ]);

        $alert->save();

        return $alert;
    }

    public function resolve(AlertType $type, ?Model $subject = null): void
    {
        Alert::query()
            ->where('type', $type->value)
            ->where('alertable_type', $subject?->getMorphClass())
            ->where('alertable_id', $subject?->getKey())
            ->where('status', AlertStatus::Open->value)
            ->update([
                'status' => AlertStatus::Resolved->value,
                'resolved_at' => now(),
            ]);
    }
}
