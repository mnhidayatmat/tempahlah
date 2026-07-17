<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class PropertyPhotoController extends Controller
{
    /** Hard cap so a single property can't balloon storage (paid tier). */
    public const MAX_PHOTOS_PER_PROPERTY = 20;
    /** Free-tier cap — upgrade to Pro for the full :max. */
    public const MAX_PHOTOS_FREE = 7;
    /** Max input file size, paid tier (Laravel validator uses KB). */
    public const MAX_UPLOAD_KB = 8192; // 8MB raw — gets resized down
    /** Max input file size, free tier. Every upload is downscaled to 2400px +
     *  JPEG q82 anyway, so this only trims oversized raw/DSLR inputs — no
     *  visible quality loss to guests. Most phone photos are 2–5MB. */
    public const MAX_UPLOAD_KB_FREE = 5120; // 5MB

    /** Effective per-property photo cap for the property's tenant tier. */
    public static function photoCapFor(Property $property): int
    {
        return $property->tenant?->isPaid()
            ? self::MAX_PHOTOS_PER_PROPERTY
            : self::MAX_PHOTOS_FREE;
    }

    /** Effective per-image upload size limit (KB) for the property's tier. */
    public static function maxUploadKbFor(Property $property): int
    {
        return $property->tenant?->isPaid()
            ? self::MAX_UPLOAD_KB
            : self::MAX_UPLOAD_KB_FREE;
    }

    public function store(Request $request, Property $property)
    {
        $maxKb = self::maxUploadKbFor($property);
        $isFree = ! $property->tenant?->isPaid();

        $request->validate([
            'photos'   => 'required|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:'.$maxKb,
        ], [
            'photos.*.max' => $isFree
                ? __('Each photo must be under :mb MB on the free plan. Upgrade to Pro for up to :promb MB.', [
                    'mb' => intdiv($maxKb, 1024),
                    'promb' => intdiv(self::MAX_UPLOAD_KB, 1024),
                ])
                : __('Each photo must be under :mb MB.', ['mb' => intdiv($maxKb, 1024)]),
        ]);

        $cap = self::photoCapFor($property);
        $existing = $property->photos()->count();
        $incoming = count($request->file('photos', []));
        if ($existing + $incoming > $cap) {
            $isFree = ! $property->tenant?->isPaid();

            return back()->with('error', $isFree
                ? __(
                    'Photo limit reached — free accounts can upload up to :max photos per homestay (you have :have). Upgrade to Pro for up to :promax.',
                    [
                        'max' => $cap,
                        'have' => $existing,
                        'promax' => self::MAX_PHOTOS_PER_PROPERTY,
                    ],
                )
                : __(
                    'Cap reached — you have :have photo(s), can add :remaining more (max :max per property).',
                    [
                        'have' => $existing,
                        'remaining' => max(0, $cap - $existing),
                        'max' => $cap,
                    ],
                ));
        }

        $disk = config('filesystems.default', 'spaces');
        $manager = new ImageManager(new GdDriver());
        $nextSort = (int) ($property->photos()->max('sort_order') ?? 0) + 1;
        $isFirst = $existing === 0;
        $stored = 0;

        foreach ($request->file('photos') as $file) {
            try {
                // Resize to a sensible max width + recompress as JPEG quality 82
                // so guest pages don't have to load 5MB straight off the camera.
                // Intervention Image v4.1 uses decode*()/encode() — no more
                // ::read() / ::toJpeg() shortcuts from v3.
                $image = $manager->decodeSplFileInfo($file);
                $image->scaleDown(width: 2400);
                $binary = (string) $image->encode(new JpegEncoder(quality: 82));

                $relativePath = sprintf(
                    'properties/%d/%d/%s.jpg',
                    $property->tenant_id,
                    $property->id,
                    Str::ulid(),
                );

                Storage::disk($disk)->put($relativePath, $binary, 'public');

                $property->photos()->create([
                    'tenant_id' => $property->tenant_id,
                    'path' => $relativePath,
                    'disk' => $disk,
                    'sort_order' => $nextSort++,
                    'is_hero' => $isFirst && $stored === 0, // first upload becomes hero
                ]);

                $stored++;
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', __(
                    'Upload failed: :err',
                    ['err' => $e->getMessage()],
                ));
            }
        }

        // First photo of a fresh property — also stamp it as the cover.
        if ($isFirst && $stored > 0) {
            $hero = $property->photos()->where('is_hero', true)->first();
            if ($hero) {
                $property->update(['hero_photo_path' => $hero->path]);
            }
        }

        // Guided onboarding: the cover photo is in, hop back to the dashboard so
        // the host lands on the next setup step (tell guests how to pay you).
        if ($stored > 0 && $request->boolean('onboarding')) {
            return redirect()
                ->route('tenant.dashboard')
                ->with('status', __('Cover photo added. Next: tell guests how to pay you.'));
        }

        return back()->with('status', __(':n photo(s) uploaded.', ['n' => $stored]));
    }

    public function destroy(Property $property, PropertyPhoto $photo)
    {
        abort_unless($photo->property_id === $property->id, 404);

        try {
            Storage::disk($photo->disk)->delete($photo->path);
        } catch (\Throwable) {
            // File may already be gone — proceed with row delete.
        }
        $wasHero = $photo->is_hero;
        $photo->delete();

        if ($wasHero) {
            // Promote next photo (by sort_order) to hero so the property still has a cover.
            $next = $property->photos()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_hero' => true]);
                $property->update(['hero_photo_path' => $next->path]);
            } else {
                $property->update(['hero_photo_path' => null]);
            }
        }

        return back()->with('status', __('Photo removed.'));
    }

    public function setHero(Property $property, PropertyPhoto $photo)
    {
        abort_unless($photo->property_id === $property->id, 404);

        $property->photos()->update(['is_hero' => false]);
        $photo->update(['is_hero' => true]);
        $property->update(['hero_photo_path' => $photo->path]);

        return back()->with('status', __('Cover photo updated.'));
    }

    /**
     * Tag a photo with a category (kitchen / bedroom / bathroom / ...).
     * Submitting category="" clears the tag.
     */
    public function updateCategory(Request $request, Property $property, PropertyPhoto $photo)
    {
        abort_unless($photo->property_id === $property->id, 404);

        $valid = array_keys(PropertyPhoto::categories());
        $validated = $request->validate([
            'category' => 'nullable|string|in:'.implode(',', $valid),
        ]);

        $photo->update(['category' => $validated['category'] ?? null]);

        return back()->with('status', __('Photo tagged.'));
    }
}
