<?php
declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\OffplanLocation;
use Illuminate\Database\Eloquent\Collection;

class SearchLocationsAction
{
    public function execute(?string $query): Collection
    {
        if (empty($query)) {
            return new Collection();
        }

        // 🔥 FAANG FIX: Use whereFullText to utilize the database Index (O(1) lookup instead of O(N) scan)
        // Note: boolean mode can be used if you want to support + and - operators in search
        return OffplanLocation::query()
            ->whereFullText(['city', 'community'], $query)
            ->select('id', 'city', 'community')
            ->limit(20)
            ->get();
    }
}