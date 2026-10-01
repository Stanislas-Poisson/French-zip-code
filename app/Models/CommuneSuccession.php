<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SuccessionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link between a commune code and the code that follows it at a given date.
 * It is derived from the events and used to resolve an old code.
 *
 * @property int            $id
 * @property string         $from_code
 * @property string|null    $to_code
 * @property SuccessionKind $kind
 */
final class CommuneSuccession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'commune_event_id',
        'from_code',
        'to_code',
        'kind',
        'effective_date',
    ];

    /**
     * @return BelongsTo<CommuneEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CommuneEvent::class, 'commune_event_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind'           => SuccessionKind::class,
            'effective_date' => 'date',
        ];
    }
}
