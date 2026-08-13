<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Letterhead extends Model
{
    /**
     * Long/Legal (8.5in x 13in), the standard for official PH resolutions,
     * expressed in points for dompdf's custom-paper-size format.
     */
    public const PAGE_WIDTH_PT = 612.0;
    public const PAGE_HEIGHT_PT = 936.0;

    protected $fillable = [
        'name',
        'border_image_path',
        'logo_path',
        'seal_path',
        'footer_image_path',
        'university_name',
        'office_name',
        'address',
        'created_by',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class);
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
