<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\Auth\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $otps,
    ) {}

    // email a one-time reset code (response is intentionally generic)
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated()['email'])->first();

        if ($user !== null) {
            $this->otps->issue($user, $request);
        }

        return response()->json([
            'message' => 'If an account exists for that email address, a password reset code has been sent.',
        ]);
    }

    // verify the emailed one-time code and issue a short-lived reset token
    public function verifyCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired.'],
            ]);
        }

        $result = $this->otps->verify($user, $validated['code']);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'code' => [$result['message']],
            ]);
        }

        return response()->json([
            'message' => 'The code is valid. You may now choose a new password.',
            'reset_token' => $result['reset_token'],
        ]);
    }

    // complete the reset using the token issued after code verification
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'reset_token' => ['This reset session is invalid. Please request a new code.'],
            ]);
        }

        $result = $this->otps->complete($user, $validated['reset_token']);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'reset_token' => [$result['message']],
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => null,
        ])->save();

        // Sign out every existing session/token and forget trusted devices.
        $user->tokens()->delete();
        $user->trustedDevices()->delete();

        $user->notify(new PasswordChangedNotification);

        return response()->json([
            'message' => 'Your password has been reset successfully.',
        ]);
    }
}
