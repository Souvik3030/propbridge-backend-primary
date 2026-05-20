<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HotAreasDealsController extends Controller
{
    public function index(Request $request)
    {
        set_time_limit(0); // Prevent timeout when fetching all records
        
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        // 1. Hottest Projects
        $hottestProjectsQuery = DB::table('dld_active_projects as p')
            ->join('dld_transactions as t', 't.project_id', '=', 'p.id')
            ->where('t.group_en', 'Sales')
            ->whereBetween('t.instance_date', [$startDate, $endDate])
            ->select([
                'p.id',
                'p.project_name as project',
                'p.area_name as area',
                DB::raw('COUNT(t.id) as sales'),
                DB::raw('ROUND(AVG(t.trans_value), 2) as price'),
                DB::raw('ROUND(AVG(t.trans_value / NULLIF(t.actual_area, 0)), 2) as sqft'),
                DB::raw('(SELECT rooms_en FROM dld_transactions sub_t WHERE sub_t.project_id = p.id AND sub_t.rooms_en IS NOT NULL GROUP BY rooms_en ORDER BY COUNT(*) DESC LIMIT 1) as config')
            ])
            ->groupBy('p.id', 'p.project_name', 'p.area_name')
            ->orderByDesc('sales')
            ->get();

        $hottestProjects = $hottestProjectsQuery->map(function ($item, $key) {
            $item->id = $key + 1; // Rank as id
            $item->config = $item->config ?? 'NA';
            return $item;
        });

        // 2. Community Deep Dive
        $communitiesQuery = DB::table('dld_transactions')
            ->whereBetween('instance_date', [$startDate, $endDate])
            ->whereNotNull('area_en')
            ->select([
                'area_en as name',
                DB::raw("COUNT(CASE WHEN group_en = 'Sales' THEN 1 END) as sales"),
                DB::raw("ROUND(AVG(CASE WHEN group_en = 'Sales' THEN trans_value / NULLIF(actual_area, 0) END), 0) as sqft"),
                DB::raw("ROUND((COUNT(CASE WHEN group_en = 'Mortgages' THEN 1 END) * 100.0) / COUNT(*), 0) as mortgage_pct"),
                DB::raw("MIN(trans_value) as min_price"),
                DB::raw("MAX(trans_value) as max_price"),
                DB::raw("AVG(trans_value) as median_price") // Approximated with AVG for MySQL compatibility
            ])
            ->groupBy('area_en')
            ->orderByDesc('sales')
            ->get();

        $rankMap = [1 => '1st', 2 => '2nd', 3 => '3rd'];
        
        $communityDeepDive = $communitiesQuery->map(function ($c, $idx) use ($rankMap, $startDate, $endDate) {
            $rankNum = $idx + 1;
            $rank = $rankMap[$rankNum] ?? '';
            
            // Get top projects for this community
            $topProjects = DB::table('dld_transactions as t')
                ->join('dld_active_projects as p', 't.project_id', '=', 'p.id')
                ->where('p.area_name', $c->name)
                ->where('t.group_en', 'Sales')
                ->select('p.project_name', DB::raw('COUNT(t.id) as txns'))
                ->groupBy('p.project_name')
                ->orderByDesc('txns')
                ->limit(3)
                ->get()
                ->map(fn($p) => "{$p->project_name} ({$p->txns} txns)")
                ->toArray();

            // Get room breakdown
            $breakdown = DB::table('dld_transactions')
                ->where('area_en', $c->name)
                ->where('group_en', 'Sales')
                ->whereNotNull('rooms_en')
                ->select('rooms_en', DB::raw('COUNT(*) as count'))
                ->groupBy('rooms_en')
                ->orderByDesc('count')
                ->limit(4)
                ->get()
                ->map(fn($r) => "{$r->rooms_en}: {$r->count}")
                ->toArray();

            $minM = round((float)$c->min_price / 1000000, 1);
            $maxM = round((float)$c->max_price / 1000000, 1);
            $medM = round((float)$c->median_price / 1000000, 1);

            return [
                'name' => $c->name,
                'rank' => $rank,
                'sales' => $c->sales,
                'sqft' => $c->sqft,
                'mortgage' => $c->mortgage_pct . '%',
                'breakdown' => $breakdown,
                'ranges' => "Min: AED {$minM}M · Max: AED {$maxM}M · Median: AED {$medM}M",
                'locations' => '', // Placeholder as we lack transit_landmarks table
                'projects' => $topProjects
            ];
        });

        // 3. Registered Developers
        $devTotal = DB::table('dld_developers')->count();
        $developers = DB::table('dld_developers')
            ->get()
            ->map(fn($d) => [
                'name' => $d->name,
                'license' => $d->license_number,
                'expires' => $d->expiry_date,
                'phone' => $d->phone_number,
            ]);

        // 4. Active Projects
        $activeTotal = DB::table('dld_active_projects')->count();
        $actives = DB::table('dld_active_projects as p')
            ->join('dld_developers as d', 'p.developer_id', '=', 'd.id')
            ->select('p.*', 'd.name as developer_name')
            ->get()
            ->map(fn($p) => [
                'project' => $p->project_name,
                'developer' => $p->developer_name,
                'area' => $p->area_name,
                'units' => $p->units_count,
                'completed' => (float)$p->completion_percentage,
                'endDate' => $p->estimated_end_date,
                'escrow' => $p->escrow_status,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'hottestProjects' => $hottestProjects,
                'communityDeepDive' => $communityDeepDive,
                'registeredDevelopers' => [
                    'totalCount' => $devTotal,
                    'developers' => $developers
                ],
                'activeProjects' => [
                    'totalCount' => $activeTotal,
                    'projects' => $actives
                ]
            ],
            'timestamp' => Carbon::now()->toIso8601String()
        ]);
    }
}
