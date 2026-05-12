<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        try {
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

            // Use Laravel's native file streaming to upload directly to S3 (memory efficient)
            $uploadedPath = $file->storeAs(
                $folder, 
                $filename, 
                ['disk' => 's3']
            );

            if (!$uploadedPath) {
                Log::error('S3 upload returned false', [
                    'path' => $path,
                    'file_size' => $file->getSize(),
                ]);
                return response()->json([
                    'message' => 'The file failed to upload.',
                    'errors' => ['file' => ['S3 upload failed. Check Laravel logs for details.']],
                ], 422);
            }

            Log::info('File uploaded successfully to S3', ['path' => $uploadedPath]);

            return response()->json([
                'message' => 'Media uploaded successfully.',
                'url' => Storage::disk('s3')->url($path),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'content_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ], 201);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $previousException = $e->getPrevious();
            
            // If wrapped exception, try to get the actual error
            if ($previousException) {
                $errorMessage .= ' [Previous: ' . $previousException->getMessage() . ']';
            }
            
            Log::error('Media upload exception', [
                'error' => $errorMessage,
                'exception_class' => get_class($e),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'The file failed to upload.',
                'errors' => ['file' => [$errorMessage]],
            ], 422);
        }
    }
}