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

        // ── Text search ──────────────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('developer', fn($d) => $d->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('location', fn($l) => $l->where('community', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%"));
            });
        }

        // ── Price range ───────────────────────────────────────────────────────
        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // ── Area range ────────────────────────────────────────────────────────
        if (isset($filters['area_min'])) {
            $query->where('area_min', '>=', $filters['area_min']);
        }
        if (isset($filters['area_max'])) {
            $query->where('area_max', '<=', $filters['area_max']);
        }

        // ── Type / category ───────────────────────────────────────────────────
        if (!empty($filters['types'])) {
            $types = (array) $filters['types'];
            $query->whereIn('type_main', $types);
        }

        // ── Status ────────────────────────────────────────────────────────────
        if (!empty($filters['status'])) {
            $query->where('completion_status', $filters['status']);
        }

        // ── Handover years ────────────────────────────────────────────────────
        if (!empty($filters['handover_years'])) {
            $years = (array) $filters['handover_years'];
            // If "2028+" is in list, treat as ">= 2028"
            $hasPlus = collect($years)->contains(fn($y) => str_ends_with((string)$y, '+'));
            $numericYears = collect($years)->map(fn($y) => (int) $y)->filter()->toArray();

            $query->where(function ($q) use ($numericYears, $hasPlus) {
                if (!empty($numericYears)) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('YEAR(completion_date)'), $numericYears);
                }
                if ($hasPlus) {
                    $maxYear = max($numericYears ?: [2028]);
                    $q->orWhereYear('completion_date', '>=', $maxYear);
                }
            });
        }

        // ── Bedrooms (0 = Studio) ─────────────────────────────────────────────
        if (!empty($filters['bedrooms'])) {
            $beds = (array) $filters['bedrooms'];
            $query->where(function ($q) use ($beds) {
                foreach ($beds as $bed) {
                    $q->orWhereJsonContains('rooms', (int) $bed);
                }
            });
        }
        // Legacy: rooms filter
        if (!empty($filters['rooms'])) {
            $query->whereIn('bedrooms', (array) $filters['rooms']);
        }

        // ── Developer filter ──────────────────────────────────────────────────
        if (!empty($filters['developer_id'])) {
            $query->whereHas('developer', fn($d) => $d->where('source_id', $filters['developer_id']));
        }
        if (!empty($filters['developer_ids'])) {
            $query->whereIn('developer_id', (array) $filters['developer_ids']);
        }

        // ── Location filter ───────────────────────────────────────────────────
        if (!empty($filters['location'])) {
            $loc = $filters['location'];
            $query->whereHas('location', fn($l) => $l->where('community', 'like', "%{$loc}%")
                ->orWhere('city', 'like', "%{$loc}%"));
        }
        if (!empty($filters['locations_ids'])) {
            $query->whereIn('location_id', (array) $filters['locations_ids']);
        }

        // ── Investment score ──────────────────────────────────────────────────
        if (!empty($filters['min_invest_score'])) {
            $query->where('investment_score', '>=', (int) $filters['min_invest_score']);
        }

        // ── Min yield ────────────────────────────────────────────────────────
        if (!empty($filters['min_yield'])) {
            $query->where('estimated_yield', '>=', (float) $filters['min_yield']);
        }

        // ── Sorting ───────────────────────────────────────────────────────────
        match ($filters['sort_by'] ?? $filters['index'] ?? null) {
            'price_asc', 'price-asc', 'lowest_price'    => $query->orderBy('price'),
            'price_desc', 'price-desc', 'highest_price' => $query->orderByDesc('price'),
            'most_units', 'units'                       => $query->orderByDesc('units_count'),
            'name_az', 'name-asc'                       => $query->orderBy('title'),
            'hottest', 'hot'                            => $query->orderByDesc('investment_score'),
            'yield'                                     => $query->orderByDesc('estimated_yield'),
            'dld'                                       => $query->orderByDesc('dld_transactions_count'),
            'price-sqft'                                => $query->orderBy('dld_avg_price_sqft'),
            'completion'                                => $query->orderByDesc('investment_score'), // using score as proxy
            'handover-soon', 'newest'                   => $query->orderBy('completion_date'),
            'developer'                                 => $query->join('offplan_developers', 'offplan_projects.developer_id', '=', 'offplan_developers.id')
                                                             ->orderBy('offplan_developers.name')
                                                             ->select('offplan_projects.*'),
            'area_asc'                                  => $query->orderBy('area_min'),
            'area_desc'                                 => $query->orderByDesc('area_max'),
            default                                     => $query->latest(),
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
