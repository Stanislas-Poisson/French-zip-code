<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Change detected by comparing two snapshots (departments, regions, zip codes).
 *
 * @property int                       $id
 * @property string                    $entity_type
 * @property string                    $entity_code
 * @property ChangeType                $change_type
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 */
final class ReferenceChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_id',
        'entity_type',
        'entity_code',
        'change_type',
        'old_value',
        'new_value',
        'detected_at',
    ];

    /**
     * @return BelongsTo<Snapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'change_type' => ChangeType::class,
            'old_value'   => 'array',
            'new_value'   => 'array',
            'detected_at' => 'datetime',
        ];
    }
}
