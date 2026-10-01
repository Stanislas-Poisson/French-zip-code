<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommuneKind;
use App\Enums\EventModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A raw event of the INSEE COG movements file (merger, creation, code change...).
 *
 * @property int              $id
 * @property Carbon           $effective_date
 * @property EventModality    $modality
 * @property CommuneKind|null $kind_before
 * @property string|null      $code_before
 * @property CommuneKind|null $kind_after
 * @property string|null      $code_after
 */
final class CommuneEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_id',
        'modality',
        'effective_date',
        'kind_before',
        'code_before',
        'name_before',
        'kind_after',
        'code_after',
        'name_after',
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
            'modality'       => EventModality::class,
            'effective_date' => 'date',
            'kind_before'    => CommuneKind::class,
            'kind_after'     => CommuneKind::class,
        ];
    }
}
