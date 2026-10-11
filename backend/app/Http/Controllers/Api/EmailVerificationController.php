<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // verify an email address using the signed link sent by email
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        abort_if($user === null, 403, 'Invalid verification link.');

        abort_unless(
            hash_equals($hash, sha1($user->getEmailForVerification())),
            403,
            'Invalid verification link.'
        );

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email address verified successfully.']);
    }

    // resend the verification link to the authenticated user
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'A new verification link has been sent to your email address.']);
    }
}
