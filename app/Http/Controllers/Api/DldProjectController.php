<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\DldTransaction;
use App\Models\OffplanProject;

class DldProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = DldTransaction::query();

        // Optional filtering by project name
        if ($request->has('project')) {
            $query->where('project_en', 'like', '%' . $request->project . '%');
        }

        $transactions = $query->orderBy('instance_date', 'desc')->paginate(20);

        // Match with Bayut projects
        $projectNames = $transactions->pluck('project_en')->unique()->filter()->toArray();
        
        $bayutProjects = OffplanProject::whereIn('title', $projectNames)
            ->with(['location', 'developer'])
            ->get()
            ->keyBy('title');

        $transactions->getCollection()->transform(function ($transaction) use ($bayutProjects) {
            $transaction->bayut_project = $bayutProjects->get($transaction->project_en);
            return $transaction;
        });

        return response()->json($transactions);
    }
}
