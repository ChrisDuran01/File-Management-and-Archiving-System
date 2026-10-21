<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionFunction;

/**
 * The student page's shared data - public files, announcements, officers -
 * is the same for every visitor, so it's built once and reused until
 * something on it changes. AppServiceProvider clears it whenever one of
 * those records is saved or deleted; the Trash code's bulk updates (which
 * skip model events) and a system restore clear it themselves. The expiry
 * is only a safety net for a change path that forgets.
 *
 * Only the shared data is kept here, never the rendered page: a visitor's
 * own contact-form result and CSRF token are still rendered per visit.
 *
 * Each copy is stamped with the version that was current when its build
 * started, and forget() just starts a new version. So a build already
 * running when an officer hides a file can't put the old list back - its
 * copy carries the old version and is ignored.
 *
 * Each copy is also stamped with when the code that built it last changed,
 * so after a deploy (or an edit while developing) a copy missing a field
 * the page now reads is rebuilt instead of breaking the page.
 */
class StudentPageCache
{
    private const DATA_KEY = 'student_page:data';

    private const VERSION_KEY = 'student_page:version';

    private const TTL_SECONDS = 600;

    /** @param  Closure(): array  $build */
    public static function remember(Closure $build): array
    {
        $cached = Cache::many([self::DATA_KEY, self::VERSION_KEY]);
        $version = $cached[self::VERSION_KEY];
        $code = self::codeStamp($build);
        $copy = $cached[self::DATA_KEY];

        if (is_array($copy) && $copy['version'] === $version && ($copy['code'] ?? null) === $code) {
            return $copy['data'];
        }

        $data = $build();
        Cache::put(self::DATA_KEY, ['version' => $version, 'code' => $code, 'data' => $data], self::TTL_SECONDS);

        return $data;
    }

    /** The file the builder lives in (StudentController), and when it last changed. */
    private static function codeStamp(Closure $build): string
    {
        $file = (new ReflectionFunction($build))->getFileName();

        return $file.'@'.@filemtime($file);
    }

    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(16));
    }
}
