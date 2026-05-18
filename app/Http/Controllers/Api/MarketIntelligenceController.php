<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldTransaction;
use App\Models\OffplanProject;
use App\Models\OffplanDeveloper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketIntelligenceController extends Controller
{
    // Analytics are cached for 24 hours – heavy aggregations don't need realtime precision.
    private const CACHE_TTL = 86400; // 24 hours in seconds

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/market-summary
    // ──────────────────────────────────────────────────────────────────────────
    public function marketSummary(Request $request): JsonResponse
    {
        $year = $request->query('year');

        $cacheKey = "analytics:market_summary:{$year}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $base = DldTransaction::query();
            if ($year) {
                $base->whereYear('instance_date', $year);
            }

            $total        = (clone $base)->count();
            $totalValue   = (clone $base)->sum('trans_value');
            $avgValue     = $total > 0 ? round($totalValue / $total, 2) : 0;

            $offplanCount = (clone $base)->where('is_offplan_en', 'Off-Plan')->count();
            $readyCount   = $total - $offplanCount;

            // Mortgage vs Cash — DLD group_en typically contains "Mortgage" keyword
            $mortgageCount = (clone $base)->where('group_en', 'like', '%Mortgage%')->count();
            $cashCount     = $total - $mortgageCount;

            return [
                'total_transactions'  => $total,
                'total_sales_value'   => round($totalValue, 2),
                'avg_transaction_value' => $avgValue,
                'off_plan_vs_ready'   => [
                    'off_plan' => ['count' => $offplanCount, 'percent' => $total > 0 ? round(($offplanCount / $total) * 100, 1) : 0],
                    'ready'    => ['count' => $readyCount,   'percent' => $total > 0 ? round(($readyCount   / $total) * 100, 1) : 0],
                ],
                'mortgage_vs_cash'    => [
                    'mortgage' => ['count' => $mortgageCount, 'percent' => $total > 0 ? round(($mortgageCount / $total) * 100, 1) : 0],
                    'cash'     => ['count' => $cashCount,     'percent' => $total > 0 ? round(($cashCount     / $total) * 100, 1) : 0],
                ],
                'generated_at' => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/distributions
    // ──────────────────────────────────────────────────────────────────────────
    public function distributions(Request $request): JsonResponse
    {
        $year     = $request->query('year');
        $cacheKey = "analytics:distributions:{$year}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $base = DldTransaction::query();
            if ($year) {
                $base->whereYear('instance_date', $year);
            }

            // ── Price Distribution ──────────────────────────────────────────────
            $priceBuckets = [
                ['label' => '<500K',     'min' => 0,          'max' => 500000],
                ['label' => '500K-1M',   'min' => 500000,     'max' => 1000000],
                ['label' => '1M-2M',     'min' => 1000000,    'max' => 2000000],
                ['label' => '2M-5M',     'min' => 2000000,    'max' => 5000000],
                ['label' => '5M-10M',    'min' => 5000000,    'max' => 10000000],
                ['label' => '>10M',      'min' => 10000000,   'max' => PHP_INT_MAX],
            ];

            $priceDistribution = collect($priceBuckets)->map(function ($bucket) use ($base) {
                $count = (clone $base)
                    ->where('trans_value', '>=', $bucket['min'])
                    ->where('trans_value', '<',  $bucket['max'] === PHP_INT_MAX ? 999999999999 : $bucket['max'])
                    ->count();
                return ['range' => $bucket['label'], 'count' => $count];
            })->toArray();

            // ── Room Demand ────────────────────────────────────────────────────
            $roomDemand = (clone $base)
                ->select('rooms_en', DB::raw('count(*) as count'))
                ->whereNotNull('rooms_en')
                ->groupBy('rooms_en')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn($r) => ['room' => $r->rooms_en, 'count' => $r->count])
                ->toArray();

            return [
                'price_distribution' => $priceDistribution,
                'room_demand'        => $roomDemand,
                'generated_at'       => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/top-areas
    // ──────────────────────────────────────────────────────────────────────────
    public function topAreas(Request $request): JsonResponse
    {
        $year  = $request->query('year');
        $limit = min((int) ($request->query('limit', 10)), 30);
        $cacheKey = "analytics:top_areas:{$year}:{$limit}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year, $limit) {
            $base = DldTransaction::query();
            if ($year) {
                $base->whereYear('instance_date', $year);
            }

            // Top areas by sales volume
            $areas = (clone $base)
                ->select(
                    'area_en',
                    DB::raw('count(*) as sales_count'),
                    DB::raw('sum(trans_value) as total_value'),
                    DB::raw('avg(CASE WHEN procedure_area > 0 THEN trans_value / procedure_area ELSE NULL END) as avg_price_sqft'),
                    DB::raw('avg(CASE WHEN actual_area > 0 THEN trans_value / actual_area ELSE NULL END) as avg_price_actual_sqft')
                )
                ->whereNotNull('area_en')
                ->groupBy('area_en')
                ->orderByDesc('sales_count')
                ->limit($limit)
                ->get();

            $result = $areas->map(function ($area) use ($base, $year) {
                // Most popular room type in this area
                $topRoom = (clone $base)
                    ->where('area_en', $area->area_en)
                    ->select('rooms_en', DB::raw('count(*) as cnt'))
                    ->whereNotNull('rooms_en')
                    ->groupBy('rooms_en')
                    ->orderByDesc('cnt')
                    ->first();

                // Mortgage rate in this area
                $areaTotal    = (clone $base)->where('area_en', $area->area_en)->count();
                $mortgageCount = (clone $base)->where('area_en', $area->area_en)
                    ->where('group_en', 'like', '%Mortgage%')->count();

                // Top projects in this area from our DB
                $topProjects = OffplanProject::whereHas('location', fn($q) =>
                    $q->where('community', 'like', '%' . $area->area_en . '%'))
                    ->with(['developer'])
                    ->orderByDesc('investment_score')
                    ->limit(3)
                    ->get()
                    ->map(fn($p) => [
                        'id'    => $p->id,
                        'name'  => $p->title,
                        'score' => $p->investment_score,
                        'price_min' => $p->price,
                        'developer' => $p->developer?->name,
                    ]);

                return [
                    'area'              => $area->area_en,
                    'sales_count'       => (int) $area->sales_count,
                    'total_value'       => round((float) $area->total_value, 2),
                    'avg_price_sqft'    => round((float) ($area->avg_price_sqft ?? $area->avg_price_actual_sqft ?? 0), 2),
                    'top_room_config'   => $topRoom?->rooms_en,
                    'estimated_rental_yield' => null, // Would require separate rental data feed
                    'mortgage_percent'  => $areaTotal > 0
                        ? round(($mortgageCount / $areaTotal) * 100, 1)
                        : 0,
                    'top_projects'      => $topProjects,
                ];
            });

            return [
                'areas'        => $result,
                'generated_at' => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/hottest-projects
    // ──────────────────────────────────────────────────────────────────────────
    public function hottestProjects(Request $request): JsonResponse
    {
        $year  = $request->query('year');
        $limit = min((int) ($request->query('limit', 15)), 50);
        $cacheKey = "analytics:hottest_projects:{$year}:{$limit}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year, $limit) {
            $base = DldTransaction::query();
            if ($year) {
                $base->whereYear('instance_date', $year);
            }

            // Rank by actual DLD registered transactions per project name
            $ranked = (clone $base)
                ->select(
                    'project_en',
                    'master_project_en',
                    DB::raw('count(*) as transaction_count'),
                    DB::raw('sum(trans_value) as total_value'),
                    DB::raw('avg(trans_value) as avg_price')
                )
                ->whereNotNull('project_en')
                ->groupBy('project_en', 'master_project_en')
                ->orderByDesc('transaction_count')
                ->limit($limit)
                ->get();

            $result = $ranked->map(function ($row) {
                // Match to our offplan projects
                $project = OffplanProject::with(['developer', 'images'])
                    ->where('title', 'like', '%' . $row->project_en . '%')
                    ->first();

                return [
                    'project_name'      => $row->project_en,
                    'master_project'    => $row->master_project_en,
                    'transaction_count' => (int) $row->transaction_count,
                    'total_value'       => round((float) $row->total_value, 2),
                    'avg_price'         => round((float) $row->avg_price, 2),
                    'matched_project'   => $project ? [
                        'id'        => $project->id,
                        'name'      => $project->title,
                        'developer' => $project->developer?->name,
                        'cover'     => $project->images->first()?->url,
                        'score'     => $project->investment_score,
                    ] : null,
                ];
            });

            return [
                'projects'     => $result,
                'generated_at' => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/active-rera-projects
    // ──────────────────────────────────────────────────────────────────────────
    public function activeReraProjects(Request $request): JsonResponse
    {
        $cacheKey = 'analytics:active_rera_projects';

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            // Projects currently off-plan / under construction from our DB
            $projects = OffplanProject::with(['developer', 'images'])
                ->whereIn('completion_status', [
                    'off_plan', 'under-construction', 'under_construction',
                    'off-plan', 'Under Construction', 'Off Plan',
                ])
                ->orWhereNull('completion_status')
                ->orderBy('completion_date')
                ->limit(100)
                ->get();

            $result = $projects->map(fn($p) => [
                'id'                  => $p->id,
                'name'                => $p->title,
                'developer'           => $p->developer?->name,
                'cover'               => $p->images->first()?->url,
                'completion_status'   => $p->completion_status,
                'completion_date'     => $p->completion_date?->format('Y-m-d'),
                // Construction percentage is not available from Bayut; placeholder
                'construction_percent' => null,
                // Escrow status is not available from Bayut without DLD integration
                'escrow_verified'     => false,
                'units_count'         => $p->units_count,
                'price_min'           => $p->price,
                'price_max'           => $p->price_max,
            ]);

            return [
                'projects'     => $result,
                'total'        => $result->count(),
                'note'         => 'Construction percentage and escrow data require direct DLD API integration.',
                'generated_at' => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/analytics/registered-developers
    // ──────────────────────────────────────────────────────────────────────────
    public function registeredDevelopers(Request $request): JsonResponse
    {
        $limit    = min((int) ($request->query('limit', 20)), 100);
        $cacheKey = "analytics:registered_developers:{$limit}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit) {
            $developers = OffplanDeveloper::orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn($d) => [
                    'id'            => $d->source_id,
                    'name'          => $d->name,
                    'logo_url'      => $d->logo,
                    'project_count' => $d->project_count,
                    // License/escrow data requires DLD integration — surfaced as null
                    'license_info'  => [
                        'license_number'  => null,
                        'expiration_date' => null,
                        'escrow_verified' => false,
                    ],
                    'registered_at' => $d->created_at?->toISOString(),
                ]);

            return [
                'developers'   => $developers,
                'total'        => OffplanDeveloper::count(),
                'note'         => 'License numbers and expiry dates require DLD Developer Registry API integration.',
                'generated_at' => now()->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/analytics/cache/clear  (internal utility)
    // ──────────────────────────────────────────────────────────────────────────
    public function clearCache(): JsonResponse
    {
        $keys = [
            'analytics:market_summary:',
            'analytics:distributions:',
            'analytics:top_areas:',
            'analytics:hottest_projects:',
            'analytics:active_rera_projects',
            'analytics:registered_developers:',
        ];

        // Flush all analytics cache keys with a tag-like approach
        // (Using a cache tag group when Redis is available)
        Cache::flush(); // In production, use Cache::tags(['analytics'])->flush()

        return response()->json([
            'message'    => 'Analytics cache cleared successfully.',
            'cleared_at' => now()->toISOString(),
        ]);
    }
}
