<?php

namespace App\Providers;

use App\Listeners\ActivityEventSubscriber;
use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\SystemAlert;
use App\Models\User;
use App\Policies\PharmacySalePolicy;
use App\Policies\StaffDocumentPolicy;
use App\Policies\StaffPolicy;
use App\Policies\SystemAlertPolicy;
use App\Services\Announcements\AnnouncementPosterStorage;
use App\Services\Auth\TotpService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Files\ClamAvFileScanner;
use App\Services\Files\CloudinaryClient;
use App\Services\Files\CloudinaryStorage;
use App\Services\Files\DiskDocumentStorage;
use App\Services\Files\DocumentStorage;
use App\Services\Files\FileScanner;
use App\Services\Files\NullFileScanner;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Website\WebsiteMediaStorage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(CloudinaryClient::class, function (): CloudinaryClient {
            return new CloudinaryClient((array) config('services.cloudinary', []));
        });

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

        $this->app->singleton(AnnouncementPosterStorage::class, function (): AnnouncementPosterStorage {
            $driver = (string) config('documents.poster_disk', 'cloudinary');

            if ($driver === 'cloudinary') {
                $client = new CloudinaryClient((array) config('services.cloudinary', []));

                if ($client->isConfigured()) {
                    return new AnnouncementPosterStorage(
                        new CloudinaryStorage(
                            $client,
                            (string) config('services.cloudinary.folder', 'clinic-documents'),
                        ),
                    );
                }
            }

            $disk = in_array($driver, ['local', 'public', 's3'], true) ? $driver : 'public';

            return new AnnouncementPosterStorage(new DiskDocumentStorage($disk), $disk);
        });

        $this->app->singleton(WebsiteMediaStorage::class, function (): WebsiteMediaStorage {
            $driver = (string) config('documents.poster_disk', 'cloudinary');

            if ($driver === 'cloudinary') {
                $client = new CloudinaryClient((array) config('services.cloudinary', []));

                if ($client->isConfigured()) {
                    return new WebsiteMediaStorage(
                        new CloudinaryStorage(
                            $client,
                            (string) config('services.cloudinary.folder', 'clinic-documents'),
                        ),
                    );
                }
            }

            $disk = in_array($driver, ['local', 'public', 's3'], true) ? $driver : 'public';

            return new WebsiteMediaStorage(new DiskDocumentStorage($disk), $disk);
        });

        $this->app->singleton(TelegramClient::class, function (): TelegramClient {
            return new TelegramClient((array) config('communication.telegram', []));
        });

        $this->app->singleton(TelegramLinkService::class, function (): TelegramLinkService {
            return new TelegramLinkService((array) config('communication.telegram', []));
        });

        $this->app->singleton(TotpService::class, function (): TotpService {
            return new TotpService(
                (int) config('auth.two_factor.period', 30),
                (int) config('auth.two_factor.digits', 6),
                'sha1',
                (int) config('auth.two_factor.window', 1),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureNotificationUrls();

        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(StaffDocument::class, StaffDocumentPolicy::class);
        Gate::policy(PharmacySale::class, PharmacySalePolicy::class);
        Gate::policy(SystemAlert::class, SystemAlertPolicy::class);

        // Record create/update/delete activity for authenticated users.
        Event::subscribe(ActivityEventSubscriber::class);

        $this->registerDashboardCacheInvalidation();

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

        // Limit two-factor challenge verification attempts per challenge + IP.
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('challenge_token').'|'.$request->ip()
            );
        });

        // Limit invitation acceptance and lookup attempts per token + IP.
        RateLimiter::for('invitation', function (Request $request) {
            return Limit::perMinute(10)->by(
                (string) $request->input('token').'|'.$request->ip()
            );
        });

        // Telegram delivers webhook updates from its own infrastructure; give it
        // a higher ceiling than the shared per-user API limiter so bursts of
        // updates are not rejected (which would trigger Telegram retries).
        RateLimiter::for('telegram-webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Attendance QR scans are short-lived and per-staff; throttle to blunt
        // replay and brute-force attempts on the validation endpoint.
        RateLimiter::for('attendance-scan', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Every write to a model that feeds the dashboard aggregates flushes the
     * cached analytics payload and emits a single realtime refresh signal.
     */
    private function registerDashboardCacheInvalidation(): void
    {
        $models = [
            Appointment::class,
            Patient::class,
            Payment::class,
            Invoice::class,
            Prescription::class,
            Medicine::class,
            InventoryItem::class,
        ];

        foreach ($models as $model) {
            $model::saved(fn () => app(DashboardMetricsService::class)->invalidate());
            $model::deleted(fn () => app(DashboardMetricsService::class)->invalidate());
        }
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

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            return (new MailMessage)
                ->subject('Verify your email address')
                ->view(['html' => 'emails.email-verification', 'text' => 'emails.text.email-verification'], [
                    'name' => $notifiable->name ?? 'there',
                    'url' => $verificationUrl,
                    'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                    'preheader' => 'Confirm your email address to activate your RoyalCare Clinic account.',
                ]);
        });
    }
}
