<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Models\DisplayOfficer;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\Position;
use App\Models\User;
use App\Support\StudentPageCache;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Folder keeps "is a SuperAdmin?" / "folders given to this user"
        // answers for one request or one job; start each one empty so a
        // long-running queue worker never answers from an old copy.
        Event::listen([RequestHandled::class, JobProcessing::class], fn () => Folder::forgetAccessAnswers());

        // Any change to something the student page shows starts a fresh copy
        // of its cached data. Soft deletes and restores fire these too; bulk
        // query updates don't, so the code using them clears it itself
        // (Folder/Document::moveToTrash, Folder::restoreFromTrash, restore).
        $studentPageModels = [
            File::class, Folder::class, Announcement::class, AnnouncementImage::class,
            OfficerTerm::class, Position::class, DisplayOfficer::class, User::class,
        ];
        foreach ($studentPageModels as $model) {
            $model::saved(fn () => StudentPageCache::forget());
            $model::deleted(fn () => StudentPageCache::forget());
        }

        // A reverse proxy or HTTPS tunnel in front of the app (config/app.php,
        // TRUSTED_PROXIES): believe its X-Forwarded-* headers, so generated
        // URLs are https on the address the visitor actually used.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // In production every generated URL (forms, redirects, assets) is
        // https, so a login form or session cookie is never sent over plain
        // HTTP. Not applied locally, where XAMPP serves http://localhost.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Debug mode renders stack traces, config and .env values on
            // any error page - never acceptable in production.
            if (config('app.debug')) {
                Log::warning('APP_DEBUG is true in production - set APP_DEBUG=false in .env.');
            }
        }
    }
}
