<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Files\ClamAvFileScanner;
use App\Services\Files\CloudinaryClient;
use App\Services\Files\CloudinaryStorage;
use App\Services\Files\DiskDocumentStorage;
use App\Services\Files\DocumentStorage;
use App\Services\Files\FileScanner;
use App\Services\Files\NullFileScanner;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FileScanner::class, function (): FileScanner {
            return match (config('documents.scanner.driver', 'null')) {
                'clamav' => new ClamAvFileScanner((array) config('documents.scanner.clamav', [])),
                default => new NullFileScanner,
            };
        });

        $this->app->singleton(DocumentStorage::class, function (): DocumentStorage {
            $driver = (string) config('documents.disk', 'local');

            if ($driver === 'cloudinary') {
                return new CloudinaryStorage(
                    new CloudinaryClient((array) config('services.cloudinary', [])),
                    (string) config('services.cloudinary.folder', 'clinic-documents'),
                );
            }

            return new DiskDocumentStorage($driver);
        });

        $this->app->singleton(TelegramClient::class, function (): TelegramClient {
            return new TelegramClient((array) config('communication.telegram', []));
        });

        $this->app->singleton(TelegramLinkService::class, function (): TelegramLinkService {
            return new TelegramLinkService((array) config('communication.telegram', []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureNotificationUrls();

        // API reference (Scramble /docs/api) is restricted to the local environment by
        // default; in other environments only authenticated administrators may view it.
        Gate::define('viewApiDocs', function (?User $user): bool {
            return $user !== null && $user->hasRole('Admin');
        });

        // General authenticated API throttle, keyed per user (or IP when guest).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection for the login endpoint, keyed per email + IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('email').'|'.$request->ip()
            );
        });

        // Limit OTP verification/resend attempts per challenge + IP.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('challenge_token').'|'.$request->ip()
            );
        });

        // Limit password reset requests per email + IP.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('email').'|'.$request->ip()
            );
        });
    }

    /**
     * Point the built-in email verification link at the SPA so users land on a
     * friendly frontend route rather than a JSON endpoint.
     */
    private function configureNotificationUrls(): void
    {
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        VerifyEmail::createUrlUsing(function (object $notifiable) use ($frontend): string {
            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);

            return $frontend.'/verify-email?verify_url='.urlencode($url);
        });
    }
}
