<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Public intake for messages submitted through the website's Contact page.
 */
class PublicContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::create($validated);

        $recipients = User::query()->permission('website.view')->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ContactMessageReceivedNotification(
                $message->id,
                $message->name,
                $message->email,
                $message->subject,
                Str::limit($message->message, 140),
            ));
        }

        return response()->json([
            'message' => 'Thanks! Your message has been sent — we will get back to you soon.',
        ], 201);
    }
}
