<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A source file that has been downloaded and imported.
 *
 * @property int    $id
 * @property string $source
 * @property string $version
 * @property string $checksum
 * @property bool   $complete
 */
final class Snapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source',
        'version',
        'checksum',
        'fetched_at',
        'imported_at',
        'complete',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at'  => 'datetime',
            'imported_at' => 'datetime',
            'complete'    => 'boolean',
        ];
    }
}
