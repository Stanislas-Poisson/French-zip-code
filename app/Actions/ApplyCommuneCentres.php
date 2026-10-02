<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Postal\GeoCommuneRecord;
use App\Models\Commune;
use Illuminate\Support\Facades\DB;

final class ApplyCommuneCentres
{
    /**
     * Stores the centre of each current commune.
     *
     * @param iterable<GeoCommuneRecord> $records
     *
     * @return int number of communes updated
     */
    public function execute(iterable $records): int
    {
        $count = 0;

        DB::transaction(function () use ($records, &$count): void {
            foreach ($records as $record) {
                $count += Commune::query()
                    ->current()
                    ->where('insee_code', $record->inseeCode)
                    ->update([
                        'centre_latitude'  => $record->latitude,
                        'centre_longitude' => $record->longitude,
                    ]);
            }
        });

        return $count;
    }
}
