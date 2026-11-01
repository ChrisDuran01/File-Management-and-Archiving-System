<?php

namespace App\Models;

use App\Support\SchoolYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Letterhead extends Model
{
    /**
     * Long/Legal (8.5in x 13in), the standard for official PH resolutions,
     * expressed in points for dompdf's custom-paper-size format.
     */
    public const PAGE_WIDTH_PT = 612.0;
    public const PAGE_HEIGHT_PT = 936.0;

    /** Below this many pixels per inch across the page, a print looks blurry. */
    public const MIN_PRINT_DPI = 200;

    protected $fillable = [
        'name',
        'school_year',
        'border_image_path',
        'logo_path',
        'seal_path',
        'footer_image_path',
        'university_name',
        'office_name',
        'address',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    /** The letterhead new documents and proposal PDFs start with, if one is set. */
    public static function defaultOne(): ?self
    {
        return self::where('is_default', true)->first();
    }

    /** Makes this the only default letterhead. */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            self::whereKeyNot($this->id)->where('is_default', true)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }

    /** True when it is labelled with a school year that has already ended. */
    public function isFromPastSchoolYear(): bool
    {
        return SchoolYear::isValid($this->school_year) && $this->school_year < SchoolYear::current();
    }

    /**
     * What will go wrong when the whole-page image is printed on Long
     * paper: too few pixels (blurry), or a different shape (edges trimmed
     * by borderCoverBox()).
     *
     * @return string[]
     */
    public function printWarnings(): array
    {
        $info = $this->border_image_path && Storage::disk('public')->exists($this->border_image_path)
            ? @getimagesize(Storage::disk('public')->path($this->border_image_path))
            : false;

        if (! $info || $info[0] <= 0 || $info[1] <= 0) {
            return [];
        }

        [$width, $height] = $info;
        $warnings = [];

        $dpi = $width / (self::PAGE_WIDTH_PT / 72);
        if ($dpi < self::MIN_PRINT_DPI) {
            $warnings[] = 'Blurry when printed: the page image is '.$width.' px wide (about '.round($dpi).' dpi). Upload it as a PDF, or as an image at least 2550 px wide.';
        }

        $pageRatio = self::PAGE_WIDTH_PT / self::PAGE_HEIGHT_PT;
        if (abs($width / $height - $pageRatio) / $pageRatio > 0.03) {
            $warnings[] = 'Not Long size (8.5 × 13 in), so its edges are cut off to fit the page. In Word, set the paper size to 8.5 × 13 in before saving as PDF.';
        }

        return $warnings;
    }

    /**
     * "Cover" placement for the border image against a page of the given
     * size (both in points): scales the image up just enough to fill the
     * page completely - cropping a sliver off whichever edge doesn't
     * match, never stretching non-uniformly - by comparing the image's own
     * aspect ratio against the page's each time. Adapts to any border image
     * at any resolution/shape without needing to know anything about it in
     * advance. Falls back to a plain full-page box if there's no border
     * image (or it can't be read).
     */
    public function borderCoverBox(float $pageWidthPt, float $pageHeightPt): array
    {
        $default = ['width' => $pageWidthPt, 'height' => $pageHeightPt, 'left' => 0.0, 'top' => 0.0];

        if (! $this->border_image_path || ! Storage::disk('public')->exists($this->border_image_path)) {
            return $default;
        }

        $info = @getimagesizefromstring(Storage::disk('public')->get($this->border_image_path));

        if (! $info || $info[0] <= 0 || $info[1] <= 0) {
            return $default;
        }

        [$imageWidth, $imageHeight] = $info;
        $imageRatio = $imageWidth / $imageHeight;
        $pageRatio = $pageWidthPt / $pageHeightPt;

        if ($pageRatio > $imageRatio) {
            // Page is proportionally wider than the image - match widths,
            // let height overflow (crop top/bottom).
            $width = $pageWidthPt;
            $height = $pageWidthPt / $imageRatio;
        } else {
            // Page is proportionally taller than the image - match heights,
            // let width overflow (crop left/right).
            $height = $pageHeightPt;
            $width = $pageHeightPt * $imageRatio;
        }

        return [
            'width' => $width,
            'height' => $height,
            'left' => ($pageWidthPt - $width) / 2,
            'top' => ($pageHeightPt - $height) / 2,
        ];
    }
}
