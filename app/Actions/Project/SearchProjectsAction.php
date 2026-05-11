<?php
declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\OffplanProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchProjectsAction
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = OffplanProject::with(['location', 'developer', 'images']);

        if (isset($filters['price_min'])) $query->where('price', '>=', $filters['price_min']);
        if (isset($filters['price_max'])) $query->where('price', '<=', $filters['price_max']);
        if (isset($filters['source_id'])) $query->where('source_id', $filters['source_id']);
        if (isset($filters['rooms'])) $query->whereIn('bedrooms', $filters['rooms']);
        if (isset($filters['developer_ids'])) $query->whereIn('developer_id', $filters['developer_ids']);
        if (isset($filters['locations_ids'])) $query->whereIn('location_id', $filters['locations_ids']);

        match ($filters['index'] ?? null) {
            'lowest_price' => $query->orderBy('price'),
            'highest_price' => $query->orderByDesc('price'),
            default => $query->latest()
        };

        // FAANG STANDARD: Let Laravel handle pagination math securely
        return $query->paginate(50); 
    }
}