<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks the images shown on the site (announcement photos, officer and
 * profile photos, the logo and page images) before they are stored.
 *
 * Phone photos arrive at 2-8 MB and thousands of pixels wide, then get shown
 * a few hundred pixels wide on the student page - over mobile data. Each is
 * resized to fit the size it is shown at and re-saved, which also drops the
 * photo's metadata, including where it was taken (GPS).
 *
 * Not for officers' files: those are records and are kept exactly as
 * uploaded. Letterheads are left alone too - they are printed into PDFs.
 */
class ImageCompressor
{
    /** Longest edge, in pixels, for each place an image is shown. */
    public const ANNOUNCEMENT_IMAGE = 1600;
    public const ANNOUNCEMENT_ATTACHMENT = 2400; // posters people download - keep small print readable
    public const PHOTO = 600;                     // officer and profile photos
    public const LOGO = 1024;
    public const HERO = 1920;
    public const PAGE_IMAGE = 1600;               // vision / mission / hymn

    private const JPEG_QUALITY = 82;

    /** Larger than this, decoding alone could exhaust memory - stored as uploaded. */
    private const MAX_PIXELS = 60_000_000;

    /**
     * Drop-in for $file->store($directory, $disk) - compresses first when
     * the file is an image this can safely re-save. Returns the stored path.
     */
    public function store(UploadedFile $file, string $directory, int $maxEdge, string $disk = 'public'): string
    {
        $compressed = $this->compress($file->getRealPath(), $maxEdge);

        if ($compressed === null) {
            return $file->store($directory, $disk);
        }

        [$bytes, $extension] = $compressed;
        $path = trim($directory, '/') . '/' . Str::random(40) . '.' . $extension;
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    /**
     * @return array{0: string, 1: string}|null [bytes, 'jpg'|'png'], or null
     *         to keep the original (not an image, GIF, too large to decode,
     *         or nothing would be gained)
     */
    public function compress(string $path, int $maxEdge): ?array
    {
        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        [$width, $height, $type] = $info;

        // GIFs may be animated, and re-saving would freeze them.
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        };

        if (! $image) {
            return null;
        }

        if ($type === IMAGETYPE_JPEG) {
            // Re-saving drops the EXIF orientation tag, so turn the pixels
            // the way the camera meant them to be shown - then always
            // re-save: that is what removes the location data.
            $image = $this->fitWithin($this->applyOrientation($image, $path), $maxEdge, $resized);

            return [$this->encodeJpeg($image), 'jpg'];
        }

        $image = $this->fitWithin($image, $maxEdge, $resized);

        // PNG / WebP: photos come out smaller as JPEGs, but flat-colour
        // graphics (posters, screenshots with text) come out smaller - and
        // sharper - as PNGs. Try both and keep the smaller. Anything with
        // see-through parts has to stay PNG.
        $candidates = ['png' => $this->encodePng($image)];
        if (! $this->hasTransparency($image)) {
            $candidates['jpg'] = $this->encodeJpeg($image);
        }

        uasort($candidates, fn (string $a, string $b) => strlen($a) <=> strlen($b));
        $extension = array_key_first($candidates);
        $bytes     = $candidates[$extension];

        // Kept as uploaded when this gained nothing.
        if (! $resized && strlen($bytes) >= filesize($path)) {
            return null;
        }

        return [$bytes, $extension];
    }

    private function encodeJpeg(GdImage $image): string
    {
        imageinterlace($image, true); // progressive: shows blurry-then-sharp on slow connections
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return ob_get_clean();
    }

    private function encodePng(GdImage $image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);

        return ob_get_clean();
    }

    private function fitWithin(GdImage $image, int $maxEdge, ?bool &$resized): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $scale  = $maxEdge / max($width, $height);

        $resized = $scale < 1;
        if (! $resized) {
            return $image;
        }

        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    /**
     * Whether any pixel is see-through. Many PNGs carry an alpha channel
     * without using it (screenshots); those may do better as JPEGs.
     * Every second pixel each way is enough to catch a logo's background.
     */
    private function hasTransparency(GdImage $image): bool
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function applyOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($image, IMG_FLIP_VERTICAL); $image = imagerotate($image, -90, 0); break;
            case 6: $image = imagerotate($image, -90, 0); break;
            case 7: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, -90, 0); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }

        return $image;
    }
}
