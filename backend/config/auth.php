<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, routes protected by the "verified" middleware reject users
    | whose email address has not been verified. Disabled by default so that
    | existing development/demo accounts continue to work. Seeded accounts are
    | always marked verified.
    |
    */

    'require_verified_email' => (bool) env('AUTH_REQUIRE_VERIFIED_EMAIL', false),

    /*
    |--------------------------------------------------------------------------
    | OTP / Trusted Device Authentication
    |--------------------------------------------------------------------------
    |
    | When "enabled" is true, a login from a device that is not already trusted
    | requires a one-time passcode delivered by email before an API token is
    | issued. Trusted devices are remembered with an HttpOnly cookie.
    |
    */

    'otp' => [
        'enabled' => (bool) env('AUTH_OTP_ENABLED', false),
        'length' => (int) env('AUTH_OTP_LENGTH', 6),
        'expire_minutes' => (int) env('AUTH_OTP_EXPIRE_MINUTES', 10),
        'max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('AUTH_OTP_RESEND_COOLDOWN', 60),
        'trusted_device_days' => (int) env('AUTH_TRUSTED_DEVICE_DAYS', 30),
        'cookie_name' => env('AUTH_DEVICE_COOKIE_NAME', 'clinic_trusted_device'),
        'enforce_trusted_device' => (bool) env('AUTH_ENFORCE_TRUSTED_DEVICE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Minimum password length enforced on registration and password reset.
    |
    */

    'password_min_length' => (int) env('AUTH_PASSWORD_MIN', 8),

];
