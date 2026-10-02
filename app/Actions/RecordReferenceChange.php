<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ChangeType;
use App\Enums\ReferenceEntity;
use App\Models\ReferenceChange;
use App\Models\Snapshot;

final class RecordReferenceChange
{
    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function execute(
        Snapshot $snapshot,
        ReferenceEntity $referenceEntity,
        string $code,
        ChangeType $changeType,
        ?array $old = null,
        ?array $new = null,
    ): ReferenceChange {
        return ReferenceChange::query()->create([
            'snapshot_id' => $snapshot->id,
            'entity_type' => $referenceEntity->value,
            'entity_code' => $code,
            'change_type' => $changeType,
            'old_value'   => $old,
            'new_value'   => $new,
            'detected_at' => now(),
        ]);
    }
}
