<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ImportDldDevelopersJob;
use App\Jobs\ImportDldTransactionsJob;
use App\Jobs\ImportDldProjectsJob;

class DldImportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'type' => 'required|in:developers,projects,transactions',
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        // Store the file temporarily
        $path = $file->storeAs('imports/dld', "{$type}_" . time() . '.csv');

        // Dispatch the corresponding job
        switch ($type) {
            case 'developers':
                ImportDldDevelopersJob::dispatch($path);
                break;
            case 'transactions':
                ImportDldTransactionsJob::dispatch($path);
                break;
            case 'projects':
                ImportDldProjectsJob::dispatch($path);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' import job has been successfully dispatched.',
            'path' => $path
        ]);
    }
}
