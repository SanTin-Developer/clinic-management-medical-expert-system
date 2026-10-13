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

    /*
    |--------------------------------------------------------------------------
    | Password Reset OTP
    |--------------------------------------------------------------------------
    |
    | Forgot-password issues a one-time passcode delivered by email instead of
    | a reset link. The user submits their email together with the code and a
    | new password to complete the reset.
    |
    */

    'password_reset' => [
        'code_length' => (int) env('AUTH_RESET_CODE_LENGTH', 6),
        'expire_minutes' => (int) env('AUTH_RESET_EXPIRE_MINUTES', 15),
        'max_attempts' => (int) env('AUTH_RESET_MAX_ATTEMPTS', 5),
        'token_expire_minutes' => (int) env('AUTH_RESET_TOKEN_EXPIRE_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication (TOTP)
    |--------------------------------------------------------------------------
    |
    | When "mandatory" is true, staff accounts that have enrolled in TOTP are
    | required to present a valid code before an API token is issued. Accounts
    | that have not yet enrolled are flagged as needing to set it up. Disabled
    | by default so existing deployments and automated flows keep working until
    | the rollout is enabled per environment.
    |
    */

    'two_factor' => [
        'mandatory' => (bool) env('AUTH_2FA_MANDATORY', false),
        'issuer' => env('AUTH_2FA_ISSUER', env('APP_NAME', 'RoyalCare Clinic')),
        'digits' => (int) env('AUTH_2FA_DIGITS', 6),
        'period' => (int) env('AUTH_2FA_PERIOD', 30),
        'window' => (int) env('AUTH_2FA_WINDOW', 1),
        'challenge_expire_minutes' => (int) env('AUTH_2FA_CHALLENGE_EXPIRE_MINUTES', 10),
        'challenge_max_attempts' => (int) env('AUTH_2FA_CHALLENGE_MAX_ATTEMPTS', 5),
        'recovery_codes' => (int) env('AUTH_2FA_RECOVERY_CODES', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout
    |--------------------------------------------------------------------------
    |
    | Optional progressive lockout after repeated failed password attempts for
    | the same account. Disabled by default; the login route is always subject
    | to per-email + per-IP rate limiting regardless of this setting.
    |
    */

    'lockout' => [
        'enabled' => (bool) env('AUTH_LOCKOUT_ENABLED', false),
        'max_attempts' => (int) env('AUTH_LOCKOUT_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_LOCKOUT_DECAY_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff Invitations
    |--------------------------------------------------------------------------
    |
    | Admin-issued invitations to provision a staff account. Only a hash of the
    | token is stored; the plaintext token is delivered by email once.
    |
    */

    'invitation' => [
        'expire_hours' => (int) env('AUTH_INVITATION_EXPIRE_HOURS', 72),
        'frontend_path' => env('AUTH_INVITATION_PATH', '/accept-invitation'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step-Up Authorization
    |--------------------------------------------------------------------------
    |
    | Sensitive actions require the user to prove their identity again. A
    | short-lived grant is bound to the user and the specific action.
    |
    */

    'step_up' => [
        'ttl_minutes' => (int) env('AUTH_STEP_UP_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Change (two-stage)
    |--------------------------------------------------------------------------
    |
    | Changing an account email requires confirming a code sent to the new
    | address. A code is never returned to the client.
    |
    */

    'email_change' => [
        'expire_minutes' => (int) env('AUTH_EMAIL_CHANGE_EXPIRE_MINUTES', 15),
        'max_attempts' => (int) env('AUTH_EMAIL_CHANGE_MAX_ATTEMPTS', 5),
    ],

];
