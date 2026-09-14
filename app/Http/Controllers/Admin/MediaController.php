<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Media\MediaService;
use App\Cms\Media\UploadGuard;
use App\Cms\Media\UploadRejected;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The media library: the single place every upload in the CMS goes through,
 * whether it comes from this screen, a settings field, the rich text editor
 * or the visual builder.
 */
class MediaController extends Controller
{
    public function __construct(
        private MediaService $media,
        private UploadGuard $guard,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.media.index', [
            'files' => $this->query($request)->paginate(40)->withQueryString(),
            'filters' => $request->only(['q', 'type', 'alt']),
            'allowed' => $this->guard->allowedExtensions(),
            'maxKb' => (int) config('cms.media.max_upload_kb', 10240),
            'canProcessImages' => $this->media->canProcessImages(),
            'missingAlt' => Media::where('mime_type', 'like', 'image/%')
                ->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))
                ->count(),
        ]);
    }

    /** The picker opened from the editor and the builder; returns JSON. */
    public function browse(Request $request): JsonResponse
    {
        $files = $this->query($request)->paginate(24);

        return response()->json([
            'data' => $files->getCollection()->map(fn (Media $file) => $this->present($file))->values(),
            'next_page' => $files->hasMorePages() ? $files->currentPage() + 1 : null,
        ]);
    }

    private function query(Request $request)
    {
        return Media::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('alt', 'like', $term));
            })
            ->when($request->string('type')->toString() === 'image',
                fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($request->string('type')->toString() === 'file',
                fn ($q) => $q->where('mime_type', 'not like', 'image/%'))
            ->when($request->boolean('alt'), fn ($q) => $q->where('mime_type', 'like', 'image/%')
                ->where(fn ($sub) => $sub->whereNull('alt')->orWhere('alt', '')))
            ->latest();
    }

    /** One file as the pickers see it. */
    private function present(Media $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'alt' => (string) $file->alt,
            'title' => (string) $file->title,
            'path' => $file->path,
            'url' => $file->url,
            'thumb' => $file->isImage() ? $file->conversionUrl('thumb') : null,
            'is_image' => $file->isImage(),
            'extension' => $file->extension,
            'size' => $file->humanSize(),
            'width' => $file->width,
            'height' => $file->height,
        ];
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => $this->media->uploadRules(),
            'alt' => ['nullable', 'string', 'max:255'],
        ], [
            'files.*.extensions' => 'One of those files is not a type this site accepts.',
            'files.*.max' => 'One of those files is larger than '.number_format(config('cms.media.max_upload_kb', 10240) / 1024).' MB.',
        ]);

        $uploaded = [];
        $rejected = [];

        // Each file is judged on its own, so one bad file in a batch of ten
        // does not throw away the nine good ones.
        foreach ($request->file('files') as $file) {
            try {
                $media = $this->media->store($file, null, $request->user()->id);

                if ($request->filled('alt') && $media->isImage()) {
                    $media->update(['alt' => $request->string('alt')->toString()]);
                }

                $uploaded[] = $media;
            } catch (UploadRejected $e) {
                $rejected[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }

        if ($uploaded !== []) {
            activity('media.uploaded', count($uploaded).' file(s) uploaded.');
        }

        if ($rejected !== []) {
            activity('media.rejected', count($rejected).' upload(s) rejected.', properties: ['files' => $rejected]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => collect($uploaded)->map(fn (Media $file) => $this->present($file))->values(),
                'rejected' => $rejected,
            ], $uploaded === [] && $rejected !== [] ? 422 : 200);
        }

        $redirect = back();

        if ($uploaded !== []) {
            $redirect->with('status', count($uploaded).' file(s) uploaded.');
        }

        if ($rejected !== []) {
            $redirect->with('error', implode(' ', $rejected));
        }

        return $redirect;
    }

    public function update(Request $request, Media $medium): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $medium->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->present($medium->fresh())]);
        }

        return back()->with('status', 'File details saved.');
    }

    public function destroy(Media $medium): RedirectResponse
    {
        $name = $medium->name;
        $this->media->delete($medium);

        activity('media.deleted', "Deleted the file \"{$name}\".");

        return back()->with('status', 'File deleted.');
    }
}
