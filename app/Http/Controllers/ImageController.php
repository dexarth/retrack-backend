<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function show(Request $request, string $path)
    {
        // basic hardening
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) abort(400, 'Invalid path.');

        $disk = Storage::disk('s3');
        if (!$disk->exists($path)) abort(404, 'Not found.');

        $mime = $disk->mimeType($path) ?? 'application/octet-stream';
        $size = $disk->size($path);
        $lastMod = $disk->lastModified($path);
        $etag = '"' . md5($path.'|'.$size.'|'.$lastMod) . '"';

        // Conditional GET
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }

        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'   => $mime,
            'Cache-Control'  => 'public, max-age=31536000, immutable',
            'Content-Length' => $size,
            'ETag'           => $etag,
            // inline display
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}