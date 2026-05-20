<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DldAnalyticsController extends Controller
{
    /**
     * Get consolidated DLD analytics dashboard data.
     */
    public function getAnalytics(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        // 1. Overview Stats
            $stats = DB::table('dld_transactions')
                ->whereBetween('instance_date', [$startDate, $endDate])
                ->selectRaw("
                    COUNT(*) as totalTransactions,
                    COUNT(CASE WHEN group_en = 'Sales' THEN 1 END) as sales,
                    COUNT(CASE WHEN group_en = 'Sales' AND is_offplan_en = 'Off-Plan' THEN 1 END) as offPlanSales,
                    COUNT(CASE WHEN group_en = 'Sales' AND is_offplan_en = 'Ready' THEN 1 END) as readySales,
                    COUNT(CASE WHEN group_en = 'Mortgage' THEN 1 END) as mortgages,
                    ROUND(SUM(trans_value) / 1000000000.0, 1) as totalValueB,
                    ROUND(AVG(trans_value) / 1000000.0, 1) as avgTransactionM,
                    ROUND((COUNT(CASE WHEN is_free_hold_en = 'Free Hold' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0)) as freeholdPct,
                    ROUND((COUNT(CASE WHEN usage_en = 'Residential' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0)) as residentialPct,
                    COUNT(DISTINCT area_en) as areasTracked,
                    MAX(instance_date) as dataAsOf
                ")
                ->first();

            // Cast string results from DB to correct types for JSON
            if ($stats) {
                $stats->totalTransactions = (int)$stats->totalTransactions;
                $stats->sales = (int)$stats->sales;
                $stats->offPlanSales = (int)$stats->offPlanSales;
                $stats->readySales = (int)$stats->readySales;
                $stats->mortgages = (int)$stats->mortgages;
                $stats->totalValueB = (float)$stats->totalValueB;
                $stats->avgTransactionM = (float)$stats->avgTransactionM;
                $stats->freeholdPct = (int)$stats->freeholdPct;
                $stats->residentialPct = (int)$stats->residentialPct;
                $stats->areasTracked = (int)$stats->areasTracked;
                // Format max date
                $stats->dataAsOf = $stats->dataAsOf ? Carbon::parse($stats->dataAsOf)->toDateString() : null;
            }

            // 2. Off-Plan Price Distribution
            $priceDistributionRaw = DB::table('dld_transactions')
                ->where('is_offplan_en', 'Off-Plan')
                ->where('group_en', 'Sales')
                ->whereBetween('instance_date', [$startDate, $endDate])
                ->selectRaw("
                    CASE 
                        WHEN trans_value < 1000000 THEN '<1M'
                        WHEN trans_value >= 1000000 AND trans_value < 2000000 THEN '1-2M'
                        WHEN trans_value >= 2000000 AND trans_value < 5000000 THEN '2-5M'
                        WHEN trans_value >= 5000000 AND trans_value < 10000000 THEN '5-10M'
                        WHEN trans_value >= 10000000 AND trans_value < 20000000 THEN '10-20M'
                        ELSE '20M+'
                    END AS price_range,
                    COUNT(*) AS count,
                    MIN(trans_value) as sort_order
                ")
                ->groupBy('price_range')
                ->orderBy('sort_order')
                ->get();

            $priceDistribution = $priceDistributionRaw->map(function ($item) {
                return [
                    'range' => $item->price_range,
                    'count' => (int)$item->count
                ];
            });

            // 3. Room Type Demand
            $roomDemandRaw = DB::table('dld_transactions')
                ->where('is_offplan_en', 'Off-Plan')
                ->where('group_en', 'Sales')
                ->whereBetween('instance_date', [$startDate, $endDate])
                ->selectRaw("
                    COALESCE(rooms_en, 'Unknown') as room,
                    COUNT(*) as count
                ")
                ->groupBy('room')
                ->orderByDesc('count')
                ->get();

            $roomDemand = $roomDemandRaw->map(function ($item) {
                return [
                    'room' => $item->room,
                    'count' => (int)$item->count
                ];
            });

            // 4. Top 20 Areas by Sales Volume
            $topAreasRaw = DB::table('dld_transactions')
                ->whereBetween('instance_date', [$startDate, $endDate])
                ->selectRaw("
                    area_en as area,
                    COUNT(CASE WHEN group_en = 'Sales' THEN 1 END) as sales,
                    COUNT(CASE WHEN group_en = 'Sales' AND is_offplan_en = 'Off-Plan' THEN 1 END) as offPlan,
                    ROUND(AVG(CASE WHEN actual_area > 0 THEN trans_value / actual_area ELSE null END)) as avgSqft,
                    ROUND(AVG(trans_value)) as avgPrice,
                    ROUND((COUNT(CASE WHEN group_en = 'Mortgage' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0)) as mortgagePct
                ")
                ->whereNotNull('area_en')
                ->groupBy('area_en')
                ->orderByDesc('sales')
                ->limit(20)
                ->get();

            $topAreas = $topAreasRaw->map(function ($area, $index) use ($startDate, $endDate) {
                // Find top room for this area (ignoring nulls)
                $topRoom = DB::table('dld_transactions')
                    ->where('area_en', $area->area)
                    ->whereBetween('instance_date', [$startDate, $endDate])
                    ->whereNotNull('rooms_en')
                    ->where('rooms_en', '!=', '')
                    ->select('rooms_en')
                    ->groupBy('rooms_en')
                    ->orderByRaw('COUNT(*) DESC')
                    ->value('rooms_en');

                return [
                    'rank' => $index + 1,
                    'area' => $area->area,
                    'sales' => (int)$area->sales,
                    'offPlan' => (int)$area->offPlan,
                    'avgSqft' => (int)$area->avgSqft,
                    'avgPrice' => (int)$area->avgPrice,
                    'mortgagePct' => (int)$area->mortgagePct,
                    'topRoom' => $topRoom ?? 'Unknown'
                ];
            });

        $data = [
            'stats' => $stats,
            'priceDistribution' => $priceDistribution,
            'roomDemand' => $roomDemand,
            'topAreas' => $topAreas
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => Carbon::now()->toIso8601String()
        ]);
    }
}
