<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\AnnouncementRead;
use App\Models\AssignmentAuditLog;
use App\Models\AttendanceAuditLog;
use App\Models\ConversationMessageRead;
use App\Models\ExpertEvaluationAudit;
use App\Models\LoginChallenge;
use App\Models\PasswordResetOtp;
use App\Models\SecurityEvent;
use App\Models\StepUpAuthorization;
use App\Models\TelegramDeliveryLog;
use App\Models\TelegramLinkingToken;
use App\Models\TrustedDevice;
use App\Models\TwoFactorChallenge;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Writes user activity entries for the general "Audit Logs" trail.
 *
 * The log is append-only and best-effort: a failure to record never breaks the
 * request that produced the activity. Sensitive values are redacted before
 * they are stored.
 */
class ActivityLogger
{
    /**
     * Models that are never recorded: other audit/security logs, framework
     * access tokens, transient authentication challenges, and delivery records.
     *
     * @var array<int, class-string>
     */
    public const IGNORED_MODELS = [
        ActivityLog::class,
        AnnouncementRead::class,
        SecurityEvent::class,
        AttendanceAuditLog::class,
        AssignmentAuditLog::class,
        ExpertEvaluationAudit::class,
        TrustedDevice::class,
        LoginChallenge::class,
        TwoFactorChallenge::class,
        TwoFactorRecoveryCode::class,
        PasswordResetOtp::class,
        StepUpAuthorization::class,
        TelegramLinkingToken::class,
        TelegramDeliveryLog::class,
        ConversationMessageRead::class,
        PersonalAccessToken::class,
        DatabaseNotification::class,
    ];

    /**
     * Attribute names that must always be redacted from the log.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_ATTRIBUTES = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'secret',
    ];

    /**
     * Attribute names that are never meaningful on their own.
     *
     * @var array<int, string>
     */
    public const IGNORED_ATTRIBUTES = [
        'created_at',
        'updated_at',
        'remember_token',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
        ?Request $request = null,
    ): ?ActivityLog {
        try {
            $request ??= $this->currentRequest();
            $user ??= auth()->user();

            if ($subject !== null && ! isset($properties['subject_label'])) {
                $properties['subject_label'] = $this->subjectLabel($subject);
            }

            return ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_role' => $user?->getRoleNames()->first(),
                'event' => $event,
                'description' => $description,
                'subject_type' => $subject !== null ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties === [] ? null : $this->sanitize($properties),
                'ip_address' => $request?->ip(),
                'user_agent' => $request !== null
                    ? Str::limit((string) $request->userAgent(), 1000, '')
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function isIgnoredModel(string $class): bool
    {
        foreach (self::IGNORED_MODELS as $ignored) {
            if (is_a($class, $ignored, true)) {
                return true;
            }
        }

        return false;
    }

    public function ignoredAttribute(string $key): bool
    {
        return in_array($key, self::IGNORED_ATTRIBUTES, true)
            || in_array($key, self::SENSITIVE_ATTRIBUTES, true);
    }

    /**
     * Reduce arbitrary attribute values to something JSON-safe.
     */
    public function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
        }

        if (is_scalar($value) || is_null($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function sanitize(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_ATTRIBUTES, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    public function subjectLabel(Model $model): ?string
    {
        foreach (['name', 'title', 'label', 'code', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}
