<?php

namespace App\Http\Controllers;

use App\Concerns\HasStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    use HasStorage;

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        // $imageName = (string) Str::uuid().'.'.$request->image->extension();

        $url = $this->uploadImageFile(
            visibility: 'public',
            path: 'images',
            input: 'image',
            maxWidth: 1280,
            url: true
        );

        return response()->success(['url' => $url]);
    }

    public function imageProxy($path)
    {
        $disk = config('filesystems.default');

        $path = 'images/'.$path;

        if (! \Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $stream = \Storage::disk($disk)->readStream($path);

        if (! $stream) {
            abort(404);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => \Storage::disk($disk)->mimeType($path),
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
