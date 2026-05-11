<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * POST /api/media/upload
     *
     * Uploads media files to S3 and returns the public URLs to the frontend.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
            'folder' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $folder = $request->input('folder', 'media');
        
        // Clean folder name to prevent directory traversal
        $folder = preg_replace('/[^a-zA-Z0-9_\-]/', '', $folder);
        if (empty($folder)) {
            $folder = 'media';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $extension;
        $path = "{$folder}/{$filename}";

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType(),
        ]);

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'url' => Storage::disk('s3')->url($path),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'content_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ], 201);
    }
}
