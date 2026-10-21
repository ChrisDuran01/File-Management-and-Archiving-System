<?php

namespace App\Services;

use App\Models\SiteSetting;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * The browser-tab icon: the Site Settings logo cut round, the way the
 * student page's header shows it, as a favicon.ico holding 16, 32 and 48
 * pixel sizes.
 *
 * Made once per logo and kept in storage/app/favicon, like ShareCard. The
 * version is part of the icon's address on every page, so a new logo
 * reaches browsers that would otherwise keep the old icon for a week.
 */
class Favicon
{
    public const SIZES = [16, 32, 48];

    // Bump when the drawing below changes, so every icon is made again.
    private const DESIGN = 1;

    // The logo is cut round at this size and scaled down to each icon size:
    // GD cuts the circle without smoothing, and the scaling smooths its edge.
    private const MASTER = 192;

    private const GREEN = [15, 61, 31];     // --green on the student page

    public function version(SiteSetting $settings): string
    {
        $logo = $this->logoPath($settings);

        return substr(md5(self::DESIGN.'|'.$logo.'|'.($logo ? filemtime($logo) : '')), 0, 12);
    }

    /** The icon's file, made first if this logo doesn't have one yet. */
    public function path(SiteSetting $settings): string
    {
        $dir = storage_path('app/favicon');
        $path = $dir.'/favicon-'.$this->version($settings).'.ico';

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Written under a temporary name, so a browser asking mid-way never
        // gets half an icon.
        $temp = $path.'.'.uniqid().'.tmp';
        file_put_contents($temp, $this->draw($this->logoPath($settings)));
        rename($temp, $path);

        // Icons for an older logo are no longer linked from any page.
        foreach (glob($dir.'/favicon-*.ico') ?: [] as $old) {
            if ($old !== $path) {
                @unlink($old);
            }
        }

        return $path;
    }

    private function logoPath(SiteSetting $settings): ?string
    {
        $path = $settings->logo_path
            ? Storage::disk('public')->path($settings->logo_path)
            : public_path('images/SG-logo.png');

        return is_file($path) ? $path : null;
    }

    /** The .ico file's bytes. Its images are PNGs, which every current browser reads. */
    private function draw(?string $logo): string
    {
        $round = $this->round($logo);

        $images = [];
        foreach (self::SIZES as $size) {
            $icon = $this->transparent($size);
            imagecopyresampled($icon, $round, 0, 0, 0, 0, $size, $size, self::MASTER, self::MASTER);

            ob_start();
            imagepng($icon);
            $images[$size] = ob_get_clean();
        }

        // Header (reserved, type 1 = icon, count), then one 16-byte entry
        // per image: width, height, palette size, reserved, planes, bits
        // per pixel, byte length and where its bytes start.
        $ico = pack('vvv', 0, 1, count($images));
        $offset = 6 + 16 * count($images);
        foreach ($images as $size => $png) {
            $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), $offset);
            $offset += strlen($png);
        }

        return $ico.implode('', $images);
    }

    /** The logo's centre square cut to a circle, like the page's object-fit: cover + border-radius: 50%. */
    private function round(?string $logo): GdImage
    {
        $size = self::MASTER;
        $round = $this->transparent($size);
        $source = $logo ? @imagecreatefromstring((string) file_get_contents($logo)) : false;

        if ($source) {
            $lw = imagesx($source);
            $lh = imagesy($source);
            $side = min($lw, $lh);
            imagecopyresampled($round, $source, 0, 0, intdiv($lw - $side, 2), intdiv($lh - $side, 2), $size, $size, $side, $side);
        } else {
            // No logo GD can read: a plain green disc still marks the tab.
            imagefilledrectangle($round, 0, 0, $size - 1, $size - 1, imagecolorallocate($round, ...self::GREEN));
        }

        // Clear each row outside the circle.
        $clear = imagecolorallocatealpha($round, 0, 0, 0, 127);
        $r = $size / 2;
        for ($y = 0; $y < $size; $y++) {
            $dy = $y + .5 - $r;
            $half = sqrt(max(0, $r * $r - $dy * $dy));
            $left = (int) floor($r - $half);
            $right = (int) ceil($r + $half);
            if ($left > 0) {
                imageline($round, 0, $y, $left - 1, $y, $clear);
            }
            if ($right < $size) {
                imageline($round, $right, $y, $size - 1, $y, $clear);
            }
        }

        return $round;
    }

    private function transparent(int $size): GdImage
    {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, imagecolorallocatealpha($img, 0, 0, 0, 127));

        return $img;
    }
}
