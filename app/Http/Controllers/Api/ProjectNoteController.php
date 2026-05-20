<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectNote;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function index(Request $request, string $id)
    {
        $notes = ProjectNote::where('user_id', $request->user()->id)
            ->where('offplan_project_id', $id)
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, string $id)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note = ProjectNote::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'offplan_project_id' => $id,
            ],
            [
                'content' => $validated['content'],
            ]
        );

        return response()->json(['data' => $note], 200);
    }
}
