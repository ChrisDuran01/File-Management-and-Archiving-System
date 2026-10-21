<?php

namespace App\Services;

use App\Models\SiteSetting;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * The picture a shared link shows (Messenger, Facebook, Google) when there
 * is no photo to use: a 1200x630 card in the student page's green and gold
 * with the Site Settings logo and the SG's name.
 *
 * Made once per logo and kept in storage/app/share-card. A new logo gives a
 * new version, and the version is part of the card's address, so Facebook
 * fetches the new card instead of showing its saved copy of the old one.
 */
class ShareCard
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    // Bump when the drawing below changes, so every card is made again.
    private const DESIGN = 2;

    // Drawn at twice the size and scaled down at the end: GD draws circles
    // without smoothing, and the scaling smooths the logo disc's edge.
    private const SCALE = 2;

    private const GREEN = [15, 61, 31];     // --green on the student page

    private const GOLD = [244, 180, 0];     // --gold

    private const MINT = [154, 217, 171];   // --green-light

    private const FONT_DIR = 'vendor/dompdf/dompdf/lib/fonts';

    public function version(SiteSetting $settings): string
    {
        $logo = $this->logoPath($settings);

        return substr(md5(self::DESIGN.'|'.$logo.'|'.($logo ? filemtime($logo) : '')), 0, 12);
    }

    /** The card's file, made first if this logo doesn't have one yet. */
    public function path(SiteSetting $settings): string
    {
        $dir = storage_path('app/share-card');
        $path = $dir.'/card-'.$this->version($settings).'.jpg';

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Written under a temporary name, so a visitor arriving mid-way
        // never gets half a picture.
        $temp = $path.'.'.uniqid().'.tmp';
        imagejpeg($this->draw($this->logoPath($settings)), $temp, 88);
        rename($temp, $path);

        // Cards for an older logo are no longer linked from any page.
        foreach (glob($dir.'/card-*.jpg') ?: [] as $old) {
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

    private function draw(?string $logo): GdImage
    {
        $s = self::SCALE;
        $w = self::WIDTH * $s;
        $h = self::HEIGHT * $s;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        imagefilledrectangle($img, 0, 0, $w, $h, $this->color($img, self::GREEN));

        // The page's dot texture on its green blocks (white at 9%).
        $dot = imagecolorallocatealpha($img, 255, 255, 255, 116);
        for ($y = 15 * $s; $y < $h; $y += 26 * $s) {
            for ($x = 15 * $s; $x < $w; $x += 26 * $s) {
                imagefilledellipse($img, $x, $y, 3 * $s, 3 * $s, $dot);
            }
        }

        $gold = $this->color($img, self::GOLD);
        $band = 14 * $s;
        imagefilledrectangle($img, 0, $h - $band, $w, $h, $gold);

        // The logo as a circle with a white ring, left of centre - the way
        // the page's header shows it.
        $disc = 372 * $s;
        $cx = 300 * $s;
        $cy = (int) (($h - $band) / 2);
        imagefilledellipse($img, $cx, $cy, $disc, $disc, $this->color($img, [255, 255, 255]));
        if ($logo) {
            $this->placeLogo($img, $logo, $cx, $cy, $disc - 2 * 10 * $s);
        }

        $bold = base_path(self::FONT_DIR.'/DejaVuSans-Bold.ttf');
        $regular = base_path(self::FONT_DIR.'/DejaVuSans.ttf');
        if (is_file($bold) && is_file($regular)) {
            $this->drawText($img, $bold, $regular, 540 * $s, $cy, $w - (540 + 70) * $s, $gold);
        }

        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($out, $img, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $w, $h);

        return $out;
    }

    /** The logo cropped to a circle of $size, like the page's object-fit: cover + border-radius: 50%. */
    private function placeLogo(GdImage $img, string $path, int $cx, int $cy, int $size): void
    {
        $logo = @imagecreatefromstring((string) file_get_contents($path));
        if (! $logo) {
            return; // a format GD can't read: the white disc alone still looks fine
        }

        $round = imagecreatetruecolor($size, $size);
        imagealphablending($round, false);
        imagesavealpha($round, true);
        $clear = imagecolorallocatealpha($round, 0, 0, 0, 127);
        imagefilledrectangle($round, 0, 0, $size, $size, $clear);

        // The centre square of the logo, filling the circle.
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $side = min($lw, $lh);
        imagecopyresampled($round, $logo, 0, 0, intdiv($lw - $side, 2), intdiv($lh - $side, 2), $size, $size, $side, $side);

        // Clear each row outside the circle.
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

        imagecopy($img, $round, $cx - intdiv($size, 2), $cy - intdiv($size, 2), 0, 0, $size, $size);
    }

    /** Gold dash, the SG's name (wrapped to fit) and the school, centred on $cy. */
    private function drawText(GdImage $img, string $bold, string $regular, int $x, int $cy, int $maxWidth, int $gold): void
    {
        $s = self::SCALE;
        $white = $this->color($img, [255, 255, 255]);
        $mint = $this->color($img, self::MINT);

        $nameSize = 46 * $s;
        $nameLines = $this->wrap((string) config('seo.site_name'), $bold, $nameSize, $maxWidth);
        $schoolSize = 19 * $s;
        $schoolLines = $this->wrap('Quirino State University · '.config('seo.campus'), $regular, $schoolSize, $maxWidth);

        $dashGap = 34 * $s;
        $nameLine = (int) ($nameSize * 1.42);
        $schoolGap = 22 * $s;
        $schoolLine = (int) ($schoolSize * 1.6);
        $height = $dashGap + count($nameLines) * $nameLine + $schoolGap + count($schoolLines) * $schoolLine;
        $top = $cy - intdiv($height, 2);

        imagefilledrectangle($img, $x, $top, $x + 56 * $s, $top + 6 * $s, $gold);

        $baseline = $top + $dashGap;
        foreach ($nameLines as $line) {
            $baseline += $nameLine;
            imagettftext($img, $nameSize, 0, $x, $baseline - (int) ($nameLine * .22), $white, $bold, $line);
        }

        $baseline += $schoolGap;
        foreach ($schoolLines as $line) {
            $baseline += $schoolLine;
            imagettftext($img, $schoolSize, 0, $x, $baseline - (int) ($schoolLine * .25), $mint, $regular, $line);
        }
    }

    /** @return string[] the words split into lines no wider than $maxWidth */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $try);

            if ($line !== '' && $box[2] - $box[0] > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return $line === '' ? $lines : [...$lines, $line];
    }

    private function color(GdImage $img, array $rgb): int
    {
        return imagecolorallocate($img, ...$rgb);
    }
}
