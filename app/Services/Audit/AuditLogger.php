<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the who/what/when trail of admin changes for one rental.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(Rental $rental, ?User $user, string $action, ?Model $subject = null, array $changes = [], ?string $label = null): AuditLog
    {
        return AuditLog::create([
            'rental_id' => $rental->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label,
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * The attributes of a filled-but-unsaved model that differ from the database,
     * as `[attribute => [old, new]]`. Call it between fill() and save().
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function dirtyChanges(Model $model): array
    {
        $changes = [];

        foreach (array_keys($model->getDirty()) as $key) {
            if ($key === 'updated_at') {
                continue;
            }

            $changes[$key] = [$model->getOriginal($key), $model->getAttribute($key)];
        }

        return $changes;
    }
}
